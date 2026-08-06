<?php

namespace App\Actions\Contracts;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\AuditLogger;
use App\Support\MonthCloseGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelContractAction
{
    /**
     * @var list<string>
     */
    private const DEPOSIT_LEDGER_TYPES = [
        Charge::TYPE_DEPOSIT_HOLD,
        Charge::TYPE_DEPOSIT_APPLY,
        Charge::TYPE_DEPOSIT_TRANSFER_OUT,
        Charge::TYPE_MOVEOUT,
    ];

    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function evaluate(Contract $contract): CancelContractEligibility
    {
        $blockers = [];

        if ($contract->status !== Contract::STATUS_ACTIVE) {
            $blockers[] = [
                'code' => 'wrong_status',
                'message' => __('contracts.validation.cancel_wrong_status'),
                'action_url' => null,
                'action_label' => null,
            ];

            return new CancelContractEligibility(allowed: false, blockers: $blockers);
        }

        if (data_get($contract->meta, 'renewed_to_contract_id') !== null) {
            $blockers[] = [
                'code' => 'renewed',
                'message' => __('contracts.validation.cancel_renewed'),
                'action_url' => null,
                'action_label' => null,
            ];
        }

        $hasPayments = Payment::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->exists();
        if ($hasPayments) {
            $blockers[] = [
                'code' => 'has_payments',
                'message' => __('contracts.validation.cancel_has_payments'),
                'action_url' => route('contracts.show', $contract),
                'action_label' => __('contracts.cancel_shortcut_payments'),
            ];
        }

        $hasDepositLedger = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->whereIn('type', self::DEPOSIT_LEDGER_TYPES)
            ->exists();
        if ($hasDepositLedger) {
            $blockers[] = [
                'code' => 'has_deposit_hold',
                'message' => __('contracts.validation.cancel_has_deposit'),
                'action_url' => route('contracts.show', $contract).'#deposit-hold',
                'action_label' => __('contracts.cancel_shortcut_deposit'),
            ];
        }

        $hasAllocations = PaymentAllocation::query()
            ->withoutOrganizationScope()
            ->whereIn('charge_id', Charge::query()
                ->withoutOrganizationScope()
                ->where('contract_id', $contract->id)
                ->select('id'))
            ->exists();
        if ($hasAllocations) {
            $blockers[] = [
                'code' => 'has_allocations',
                'message' => __('contracts.validation.cancel_has_allocations'),
                'action_url' => route('contracts.show', $contract),
                'action_label' => __('contracts.cancel_shortcut_payments'),
            ];
        }

        $credit = (float) (CreditBalance::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->value('balance') ?? 0);
        if ($credit > 0) {
            $blockers[] = [
                'code' => 'has_credit',
                'message' => __('contracts.validation.cancel_has_credit'),
                'action_url' => null,
                'action_label' => null,
            ];
        }

        $charges = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->get();

        foreach ($charges as $charge) {
            $month = MonthCloseGuard::chargeMonth($charge);
            if ($month !== null && MonthCloseGuard::isMonthClosed((int) $contract->organization_id, $month)) {
                $blockers[] = [
                    'code' => 'month_closed',
                    'message' => __('contracts.validation.cancel_month_closed', ['month' => $month]),
                    'action_url' => null,
                    'action_label' => null,
                ];
                break;
            }
        }

        return new CancelContractEligibility(
            allowed: $blockers === [],
            blockers: $blockers,
        );
    }

    public function execute(Contract $contract, string $reason, ?int $userId): void
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'reason' => __('contracts.validation.cancel_reason_required'),
            ]);
        }

        DB::transaction(function () use ($contract, $reason, $userId): void {
            $locked = Contract::query()
                ->withoutOrganizationScope()
                ->lockForUpdate()
                ->findOrFail($contract->id);

            $eligibility = $this->evaluate($locked);
            if (! $eligibility->allowed) {
                throw ValidationException::withMessages([
                    'cancel' => $eligibility->blockers[0]['message'] ?? __('contracts.validation.cancel_blocked'),
                ]);
            }

            $deletedChargeIds = [];
            $charges = Charge::query()
                ->withoutOrganizationScope()
                ->where('contract_id', $locked->id)
                ->lockForUpdate()
                ->get();

            foreach ($charges as $charge) {
                $deletedChargeIds[] = $charge->id;
                $charge->delete();
            }

            $meta = $locked->meta ?? [];
            $meta['cancelled_at'] = now('America/Tijuana')->toIso8601String();
            $meta['cancellation_reason'] = $reason;
            $meta['cancelled_by_user_id'] = $userId;

            $locked->status = Contract::STATUS_CANCELLED;
            $locked->meta = $meta;
            $locked->save();

            $this->auditLogger->log(
                action: 'contract.cancelled',
                auditable: $locked,
                summary: sprintf('Contrato #%d anulado: %s', $locked->id, $reason),
                meta: [
                    'contract_id' => $locked->id,
                    'cancellation_reason' => $reason,
                    'deleted_charge_ids' => $deletedChargeIds,
                ],
                actorUserId: $userId,
            );
        }, 3);
    }
}
