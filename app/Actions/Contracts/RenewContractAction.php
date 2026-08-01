<?php

namespace App\Actions\Contracts;

use App\Mail\ContractAgreementMail;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Payment;
use App\Support\DepositBalanceService;
use App\Support\OrganizationSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class RenewContractAction
{
    public function __construct(
        private readonly DepositBalanceService $depositBalanceService,
        private readonly RegisterDepositHoldAction $registerDepositHoldAction,
        private readonly GenerateLeaseAgreementPdfAction $generateLeaseAgreementPdfAction,
        private readonly OrganizationSettingsService $organizationSettingsService,
    ) {}

    /**
     * @param  array{
     *   starts_at: string,
     *   ends_at: string,
     *   rent_amount: float|int|string,
     *   deposit_amount: float|int|string,
     *   due_day?: int,
     *   grace_days?: int,
     *   penalty_rate_daily?: float|int|string,
     *   register_difference?: bool,
     *   difference_received_at?: string,
     *   difference_method?: string,
     *   notes?: string|null,
     *   send_email?: bool,
     * }  $input
     */
    public function execute(Contract $source, array $input, ?int $userId): RenewContractResult
    {
        $startsAt = CarbonImmutable::parse($input['starts_at'], 'America/Tijuana')->startOfDay();
        $endsAt = CarbonImmutable::parse($input['ends_at'], 'America/Tijuana')->startOfDay();
        $rentAmount = round((float) $input['rent_amount'], 2);
        $depositAmount = round((float) $input['deposit_amount'], 2);
        $registerDifference = (bool) ($input['register_difference'] ?? false);

        $this->assertLandlordNameConfigured((int) $source->organization_id);

        $transactionResult = DB::transaction(function () use (
            $source,
            $input,
            $userId,
            $startsAt,
            $endsAt,
            $rentAmount,
            $depositAmount,
            $registerDifference,
        ): array {
            $lockedSource = Contract::query()
                ->withoutOrganizationScope()
                ->lockForUpdate()
                ->findOrFail($source->id);

            $this->assertRenewable($lockedSource);

            $available = $this->depositBalanceService->availableDepositAmount($lockedSource);
            $transferAmount = round(min($available, $depositAmount), 2);

            $sourceMeta = is_array($lockedSource->meta) ? $lockedSource->meta : [];

            $lockedSource->status = Contract::STATUS_ENDED;

            $currentEndsAt = $lockedSource->ends_at
                ? CarbonImmutable::parse($lockedSource->ends_at, 'America/Tijuana')->startOfDay()
                : null;

            if ($currentEndsAt === null || $currentEndsAt->greaterThanOrEqualTo($startsAt)) {
                $lockedSource->ends_at = $startsAt->subDay()->toDateString();
            }

            $lockedSource->save();

            $newContract = Contract::query()
                ->withoutOrganizationScope()
                ->create([
                    'organization_id' => $lockedSource->organization_id,
                    'unit_id' => $lockedSource->unit_id,
                    'tenant_id' => $lockedSource->tenant_id,
                    'rent_amount' => $rentAmount,
                    'deposit_amount' => $depositAmount,
                    'due_day' => (int) ($input['due_day'] ?? $lockedSource->due_day),
                    'grace_days' => (int) ($input['grace_days'] ?? $lockedSource->grace_days),
                    'penalty_rate_daily' => round((float) ($input['penalty_rate_daily'] ?? $lockedSource->penalty_rate_daily), 4),
                    'status' => Contract::STATUS_ACTIVE,
                    'starts_at' => $startsAt->toDateString(),
                    'ends_at' => $endsAt->toDateString(),
                    'meta' => [
                        'renewed_from_contract_id' => $lockedSource->id,
                    ],
                ]);

            $sourceMeta['renewed_to_contract_id'] = $newContract->id;
            $lockedSource->meta = $sourceMeta;
            $lockedSource->save();

            $transferOutCharge = null;
            $transferredHoldCharge = null;

            if ($transferAmount > 0) {
                $transferOutCharge = Charge::query()
                    ->withoutOrganizationScope()
                    ->create([
                        'organization_id' => $lockedSource->organization_id,
                        'contract_id' => $lockedSource->id,
                        'unit_id' => $lockedSource->unit_id,
                        'type' => Charge::TYPE_DEPOSIT_TRANSFER_OUT,
                        'period' => $startsAt->format('Y-m'),
                        'charge_date' => $startsAt->toDateString(),
                        'amount' => -$transferAmount,
                        'meta' => [
                            'renewed_to_contract_id' => $newContract->id,
                            'created_by_user_id' => $userId,
                        ],
                    ]);

                $transferredHoldCharge = Charge::query()
                    ->withoutOrganizationScope()
                    ->create([
                        'organization_id' => $newContract->organization_id,
                        'contract_id' => $newContract->id,
                        'unit_id' => $newContract->unit_id,
                        'type' => Charge::TYPE_DEPOSIT_HOLD,
                        'period' => $startsAt->format('Y-m'),
                        'charge_date' => $startsAt->toDateString(),
                        'amount' => $transferAmount,
                        'meta' => [
                            'source' => 'deposit_transfer',
                            'transferred_from_contract_id' => $lockedSource->id,
                            'transfer_out_charge_id' => $transferOutCharge->id,
                            'created_by_user_id' => $userId,
                        ],
                    ]);
            }

            $differenceAmount = round(max($depositAmount - $transferAmount, 0), 2);
            $differenceHoldCharge = null;

            if ($registerDifference && $differenceAmount > 0) {
                $receivedAt = (string) ($input['difference_received_at'] ?? $startsAt->toDateString());
                $method = (string) ($input['difference_method'] ?? Payment::METHOD_TRANSFER);

                $differenceHoldCharge = $this->registerDepositHoldAction->execute(
                    contract: $newContract,
                    amount: $differenceAmount,
                    receivedAt: $receivedAt,
                    notes: $input['notes'] ?? null,
                    userId: $userId,
                    method: $method,
                );
            }

            return [
                'newContract' => $newContract,
                'oldContract' => $lockedSource,
                'transferOutCharge' => $transferOutCharge,
                'transferredHoldCharge' => $transferredHoldCharge,
                'differenceHoldCharge' => $differenceHoldCharge,
                'transferredAmount' => $transferAmount,
                'differenceAmount' => $differenceAmount,
            ];
        }, 3);

        $newContract = $transactionResult['newContract']->fresh();

        $document = $this->generateLeaseAgreementPdfAction->execute(
            $newContract,
            $userId,
        );

        if ((bool) ($input['send_email'] ?? false)) {
            $this->sendContractAgreementEmail($newContract);
        }

        return new RenewContractResult(
            newContract: $transactionResult['newContract'],
            oldContract: $transactionResult['oldContract'],
            transferOutCharge: $transactionResult['transferOutCharge'],
            transferredHoldCharge: $transactionResult['transferredHoldCharge'],
            differenceHoldCharge: $transactionResult['differenceHoldCharge'],
            transferredAmount: $transactionResult['transferredAmount'],
            differenceAmount: $transactionResult['differenceAmount'],
            document: $document,
        );
    }

    private function sendContractAgreementEmail(Contract $contract): void
    {
        $contract->loadMissing('tenant');
        $recipient = $contract->tenant?->email;

        if (! is_string($recipient) || trim($recipient) === '') {
            return;
        }

        Mail::to($recipient)->send(new ContractAgreementMail($contract));
    }

    private function assertLandlordNameConfigured(int $organizationId): void
    {
        $settings = $this->organizationSettingsService->forOrganization($organizationId);
        $landlordName = is_string($settings['landlord_name'] ?? null) ? trim($settings['landlord_name']) : '';

        if ($landlordName === '') {
            throw ValidationException::withMessages([
                'landlord_name' => 'Configure el nombre del arrendador en Configuración antes de generar el contrato.',
            ]);
        }
    }

    private function assertRenewable(Contract $source): void
    {
        if ($source->status !== Contract::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'contract' => 'Solo se puede renovar un contrato activo.',
            ]);
        }

        if (data_get($source->meta, 'settlement_batch_id')) {
            throw ValidationException::withMessages([
                'contract' => 'No se puede renovar un contrato con finiquito registrado.',
            ]);
        }

        $outstanding = $this->depositBalanceService->outstandingBalanceExcludingDepositHold($source);
        if ($outstanding > 0) {
            throw ValidationException::withMessages([
                'contract' => 'No se puede renovar un contrato con saldo operativo pendiente.',
            ]);
        }

        $otherActiveExists = Contract::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $source->organization_id)
            ->where('unit_id', $source->unit_id)
            ->where('status', Contract::STATUS_ACTIVE)
            ->where('id', '!=', $source->id)
            ->exists();

        if ($otherActiveExists) {
            throw ValidationException::withMessages([
                'contract' => 'Ya existe otro contrato activo en la unidad.',
            ]);
        }
    }
}
