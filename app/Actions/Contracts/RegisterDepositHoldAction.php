<?php

namespace App\Actions\Contracts;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Payment;
use App\Support\AuditLogger;
use App\Support\DepositBalanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterDepositHoldAction
{
    public const META_SOURCE = 'deposit_hold';

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly DepositBalanceService $depositBalanceService,
        private readonly GenerateDepositReceiptFolioAction $folioAction,
    ) {}

    public function execute(
        Contract $contract,
        float $amount,
        string $receivedAt,
        ?string $notes,
        ?int $userId,
        string $method = Payment::METHOD_TRANSFER,
    ): Charge {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'deposit_amount' => 'El depósito debe ser mayor a cero.',
            ]);
        }

        if (! in_array($method, [Payment::METHOD_CASH, Payment::METHOD_TRANSFER], true)) {
            throw ValidationException::withMessages([
                'deposit_method' => __('contracts.validation.deposit_method_invalid'),
            ]);
        }

        $receivedDate = CarbonImmutable::parse($receivedAt, 'America/Tijuana')->startOfDay();
        $roundedAmount = round($amount, 2);

        $charge = DB::transaction(function () use ($contract, $roundedAmount, $receivedDate, $notes, $userId, $method): Charge {
            $lockedContract = Contract::query()
                ->withoutOrganizationScope()
                ->lockForUpdate()
                ->findOrFail($contract->id);

            if (! $lockedContract->isOperable()) {
                throw ValidationException::withMessages([
                    'deposit_amount' => __('contracts.validation.deposit_contract_closed'),
                ]);
            }

            $remaining = $this->depositBalanceService->remainingDepositHoldAmount($lockedContract);

            if ($remaining <= 0) {
                throw ValidationException::withMessages([
                    'deposit_amount' => __('contracts.validation.deposit_already_complete'),
                ]);
            }

            if ($roundedAmount > $remaining) {
                throw ValidationException::withMessages([
                    'deposit_amount' => __('contracts.validation.deposit_exceeds_remaining', [
                        'remaining' => number_format($remaining, 2, '.', ''),
                    ]),
                ]);
            }

            $folio = $this->folioAction->execute($lockedContract->organization_id, $receivedDate);

            return Charge::query()
                ->withoutOrganizationScope()
                ->create([
                    'organization_id' => $lockedContract->organization_id,
                    'contract_id' => $lockedContract->id,
                    'unit_id' => $lockedContract->unit_id,
                    'type' => Charge::TYPE_DEPOSIT_HOLD,
                    'period' => $receivedDate->format('Y-m'),
                    'charge_date' => $receivedDate->toDateString(),
                    'amount' => $roundedAmount,
                    'meta' => [
                        'subtype' => 'RECEIVED',
                        'notes' => $notes,
                        'received_at' => $receivedDate->toDateString(),
                        'method' => $method,
                        'deposit_receipt_folio' => $folio,
                        'created_by_user_id' => $userId,
                    ],
                ]);
        }, 3);

        $this->auditLogger->log(
            action: 'deposit.hold',
            auditable: $charge,
            summary: sprintf('Depósito registrado $%s en contrato #%d', number_format($roundedAmount, 2), $contract->id),
            meta: [
                'amount' => $roundedAmount,
                'contract_id' => $contract->id,
                'received_at' => $receivedDate->toDateString(),
                'notes' => $notes,
                'method' => $method,
                'deposit_receipt_folio' => data_get($charge->meta, 'deposit_receipt_folio'),
            ],
            actorUserId: $userId,
        );

        return $charge;
    }
}
