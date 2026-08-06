<?php

namespace App\Livewire\Contracts;

use App\Actions\Charges\RegisterContractAdjustmentAction;
use App\Actions\Contracts\CancelContractAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\DateDisplay;
use App\Support\DepositBalanceService;
use App\Support\NavigationReturn;
use App\Support\PaymentReceiptShareUrl;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Show extends Component
{
    #[On('payment-registered')]
    public function onPaymentRegistered(): void {}

    #[On('contract-updated')]
    public function onContractUpdated(): void {}

    #[On('contract-renewed')]
    public function onContractRenewed(): void {}

    #[On('deposit-hold-registered')]
    #[On('deposit-hold-voided')]
    public function onDepositHoldChanged(): void {}

    public Contract $contract;

    #[Url(as: 'return', except: '')]
    public string $returnUrl = '';

    #[Url(as: 'return_label', except: '')]
    public string $returnLabel = '';

    public string $adjustment_amount = '';

    public string $adjustment_charge_date = '';

    public string $adjustment_reason = '';

    public ?string $adjustment_comment = null;

    public ?string $adjustment_linked_to = null;

    public bool $showCancelConfirm = false;

    public string $cancellation_reason = '';

    /** @var list<array{code: string, message: string, action_url: ?string, action_label: ?string}> */
    public array $cancelBlockers = [];

    public function mount(Contract $contract): void
    {
        if (! (auth()->user()?->can('contracts.view') ?? false)) {
            abort(403);
        }

        $this->contract = $contract;
        $this->adjustment_charge_date = now('America/Tijuana')->toDateString();
        $this->returnUrl = NavigationReturn::sanitizeUrl($this->returnUrl) ?? '';
        $this->returnLabel = NavigationReturn::sanitizeLabel($this->returnLabel) ?? '';
    }

    public function createAdjustment(): void
    {
        if (! (auth()->user()?->can('charges.manage') ?? false)) {
            abort(403);
        }

        $this->contract->refresh();
        if (! $this->contract->allowsLedgerMutations()) {
            abort(403);
        }

        $validated = $this->validate([
            'adjustment_amount' => ['required', 'numeric', 'not_in:0'],
            'adjustment_charge_date' => ['required', 'date'],
            'adjustment_reason' => ['required', 'string', 'max:200'],
            'adjustment_comment' => ['nullable', 'string', 'max:500'],
            'adjustment_linked_to' => ['nullable', 'string', 'max:120'],
        ], [
            'adjustment_amount.required' => __('contracts.validation.adjustment_amount_required'),
            'adjustment_amount.numeric' => __('contracts.validation.adjustment_amount_numeric'),
            'adjustment_amount.not_in' => __('contracts.validation.adjustment_amount_not_zero'),
            'adjustment_charge_date.required' => __('contracts.validation.adjustment_date_required'),
            'adjustment_charge_date.date' => __('contracts.validation.adjustment_date_invalid'),
            'adjustment_reason.required' => __('contracts.validation.adjustment_reason_required'),
            'adjustment_reason.max' => __('contracts.validation.adjustment_reason_max'),
            'adjustment_comment.max' => __('contracts.validation.adjustment_comment_max'),
            'adjustment_linked_to.max' => __('contracts.validation.adjustment_linked_max'),
        ]);

        $chargeDate = CarbonImmutable::parse($validated['adjustment_charge_date'], 'America/Tijuana')->startOfDay();

        try {
            app(RegisterContractAdjustmentAction::class)->execute(
                contract: $this->contract,
                amount: (float) $validated['adjustment_amount'],
                chargeDate: $chargeDate,
                reason: trim((string) $validated['adjustment_reason']),
                comment: $validated['adjustment_comment'] ?? null,
                linkedTo: $validated['adjustment_linked_to'] ?? null,
                createdByUserId: auth()->id(),
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            if (isset($errors['adjustment_amount'][0])) {
                $this->addError('adjustment_amount', $errors['adjustment_amount'][0]);

                return;
            }

            $message = $errors['month_close'][0] ?? __('contracts.validation.adjustment_failed');
            $this->addError('adjustment_month_close', $message);

            return;
        }

        $this->reset([
            'adjustment_amount',
            'adjustment_reason',
            'adjustment_comment',
            'adjustment_linked_to',
        ]);
        $this->adjustment_charge_date = now('America/Tijuana')->toDateString();
        session()->flash('success', __('contracts.flash.adjustment_created'));
    }

    public function confirmCancelContract(): void
    {
        if (! (auth()->user()?->can('contracts.manage') ?? false)) {
            abort(403);
        }

        $this->contract->refresh();
        $eligibility = app(CancelContractAction::class)->evaluate($this->contract);
        $this->cancelBlockers = $eligibility->blockers;
        $this->cancellation_reason = '';
        $this->showCancelConfirm = true;
        $this->resetErrorBag();
    }

    public function cancelCancelConfirm(): void
    {
        $this->showCancelConfirm = false;
        $this->cancellation_reason = '';
        $this->cancelBlockers = [];
        $this->resetErrorBag();
    }

    public function followCancelShortcut(string $code): void
    {
        $this->cancelCancelConfirm();

        $anchor = match ($code) {
            'has_deposit_hold' => 'deposit-hold',
            'has_payments', 'has_allocations' => 'recent-payments',
            default => null,
        };

        if ($anchor === null) {
            return;
        }

        $openDeposit = $code === 'has_deposit_hold'
            ? "window.dispatchEvent(new CustomEvent('open-deposit-hold-panel'));"
            : '';

        $this->js(sprintf(
            'queueMicrotask(() => { %s document.getElementById(%s)?.scrollIntoView({behavior: \'smooth\', block: \'start\'}); })',
            $openDeposit,
            json_encode($anchor, JSON_THROW_ON_ERROR)
        ));
    }

    public function executeCancelContract(CancelContractAction $action): void
    {
        if (! (auth()->user()?->can('contracts.manage') ?? false)) {
            abort(403);
        }

        if ($this->cancelBlockers !== []) {
            return;
        }

        $this->validate([
            'cancellation_reason' => ['required', 'string', 'max:500'],
        ], [
            'cancellation_reason.required' => __('contracts.validation.cancel_reason_required'),
        ]);

        try {
            $action->execute(
                contract: $this->contract,
                reason: $this->cancellation_reason,
                userId: auth()->id(),
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();
            $message = $errors['cancel'][0]
                ?? $errors['reason'][0]
                ?? $errors['month_close'][0]
                ?? __('contracts.validation.cancel_blocked');
            $this->addError('cancellation_reason', $message);
            $this->contract->refresh();
            $this->cancelBlockers = $action->evaluate($this->contract)->blockers;

            return;
        }

        session()->flash('success', __('contracts.flash.contract_cancelled'));
        $this->redirect(route('contracts.index'), navigate: true);
    }

    public function render(DepositBalanceService $depositBalanceService): View
    {
        $contract = Contract::query()
            ->with(['unit.property', 'tenant', 'creditBalance'])
            ->findOrFail($this->contract->id);

        $contractDepositAmount = round((float) $contract->deposit_amount, 2);
        $registeredDeposit = $depositBalanceService->registeredDepositHoldAmount($contract);
        $remainingDeposit = $depositBalanceService->remainingDepositHoldAmount($contract);
        $depositIsComplete = $remainingDeposit <= 0;

        $ledgerRows = $this->buildLedgerRows($contract);
        $groupedLedger = $this->groupLedgerRows($ledgerRows);
        $operationalRows = $ledgerRows->flatMap(function (array $row): Collection {
            return collect([$row])->merge($row['children'] ?? []);
        })->reject(
            fn (array $row): bool => $this->isDepositLedgerType($row['type'])
        );

        $chargesTotal = round((float) $operationalRows->sum('amount'), 2);
        $allocatedTotal = round((float) $operationalRows->sum('paid'), 2);
        $creditTotal = (float) ($contract->creditBalance?->balance ?? 0);
        $pendingBalance = max(0, round((float) $operationalRows->sum('balance'), 2));
        $outstandingForRenew = $depositBalanceService->outstandingBalanceExcludingDepositHold($contract);
        $canRenew = $contract->status === Contract::STATUS_ACTIVE
            && ! data_get($contract->meta, 'settlement_batch_id')
            && $outstandingForRenew <= 0;

        $back = NavigationReturn::resolveContractShowBack(
            $this->returnUrl !== '' ? $this->returnUrl : null,
            $this->returnLabel !== '' ? $this->returnLabel : null,
            route('contracts.index', absolute: false),
            __('common.back_to_contracts'),
            (string) ($contract->tenant?->full_name ?? ''),
        );

        $contractReturnLabel = __('common.back_to_contract');
        $contractReturnUrl = route('contracts.show', $contract, false);

        $payments = Payment::query()
            ->where('contract_id', $contract->id)
            ->withSum('allocations as allocated_amount', 'amount')
            ->latest('paid_at')
            ->limit(10)
            ->get()
            ->map(function (Payment $payment) use ($contractReturnUrl, $contractReturnLabel): array {
                $shareUrl = PaymentReceiptShareUrl::make($payment->id);

                return [
                    'id' => $payment->id,
                    'folio' => $payment->receipt_folio,
                    'paid_at' => $payment->paid_at,
                    'method' => $payment->method,
                    'amount' => (float) $payment->amount,
                    'allocated_amount' => (float) ($payment->allocated_amount ?? 0),
                    'show_url' => NavigationReturn::append(
                        route('payments.show', $payment),
                        $contractReturnUrl,
                        $contractReturnLabel,
                    ),
                    'receipt_url' => route('payments.receipt.pdf', ['paymentId' => $payment->id]),
                    'share_url' => $shareUrl,
                ];
            });

        return view('livewire.contracts.show', [
            'contract' => $contract,
            'backUrl' => $back['primary']['url'],
            'backLabel' => $back['primary']['label'],
            'secondaryBackUrl' => $back['secondary']['url'] ?? null,
            'secondaryBackLabel' => $back['secondary']['label'] ?? null,
            'chargesTotal' => $chargesTotal,
            'allocatedTotal' => $allocatedTotal,
            'creditTotal' => $creditTotal,
            'pendingBalance' => $pendingBalance,
            'ledgerGroups' => $groupedLedger,
            'payments' => $payments,
            'canManageContracts' => auth()->user()?->can('contracts.manage') ?? false,
            'canCreatePayments' => $contract->isOperable()
                && (auth()->user()?->can('payments.create') ?? false),
            'canManageCharges' => $contract->isOperable()
                && (auth()->user()?->can('charges.manage') ?? false),
            'canViewPayments' => auth()->user()?->can('payments.view') ?? false,
            'canSettleContracts' => $contract->isOperable()
                && (auth()->user()?->can('contracts.settle') ?? false),
            'canRenew' => $canRenew,
            'contractDepositAmount' => $contractDepositAmount,
            'registeredDeposit' => $registeredDeposit,
            'remainingDeposit' => $remainingDeposit,
            'depositIsComplete' => $depositIsComplete,
        ])->layout('layouts.app', [
            'title' => __('contracts.show_page_title'),
        ]);
    }

    /**
     * @return Collection<int, array{
     *     id:int,
     *     period_key:string,
     *     period_label:string,
     *     type:string,
     *     charge_date:string,
     *     due_date:string,
     *     amount:float,
     *     paid:float,
     *     balance:float,
     *     status_label:string,
     *     status_tone:string,
     *     is_penalty:bool,
     *     children:list<array{
     *         id:int,
     *         period_key:string,
     *         period_label:string,
     *         type:string,
     *         charge_date:string,
     *         due_date:string,
     *         amount:float,
     *         paid:float,
     *         balance:float,
     *         status_label:string,
     *         status_tone:string,
     *         is_penalty:bool
     *     }>
     * }>
     */
    private function buildLedgerRows(Contract $contract): Collection
    {
        $allocationSubquery = PaymentAllocation::query()
            ->selectRaw('payment_allocations.charge_id, SUM(payment_allocations.amount) as allocated_total')
            ->groupBy('payment_allocations.charge_id');

        $charges = Charge::query()
            ->where('charges.contract_id', $contract->id)
            ->leftJoinSub($allocationSubquery, 'alloc', function ($join): void {
                $join->on('alloc.charge_id', '=', 'charges.id');
            })
            ->select('charges.*')
            ->selectRaw('COALESCE(alloc.allocated_total, 0) as allocated_amount')
            ->orderByRaw('CASE WHEN charges.period IS NULL THEN 1 ELSE 0 END')
            ->orderBy('charges.period')
            ->orderBy('charges.charge_date')
            ->orderBy('charges.id')
            ->get();

        $rentChargesByPeriod = $charges
            ->where('type', Charge::TYPE_RENT)
            ->keyBy(fn (Charge $charge): string => (string) $charge->period);

        $rowsById = [];
        $orderedParentIds = [];

        foreach ($charges as $charge) {
            if ($charge->type === Charge::TYPE_PENALTY) {
                continue;
            }

            $row = $this->mapChargeToLedgerRow($contract, $charge);
            $row['is_penalty'] = false;
            $row['children'] = [];
            $rowsById[$charge->id] = $row;
            $orderedParentIds[] = $charge->id;
        }

        foreach ($charges as $charge) {
            if ($charge->type !== Charge::TYPE_PENALTY) {
                continue;
            }

            $sourcePeriod = $this->resolvePenaltySourcePeriod($contract, $charge);
            $parentId = $this->resolvePenaltyParentChargeId($charge, $sourcePeriod, $rentChargesByPeriod);
            $parentRow = $parentId !== null ? ($rowsById[$parentId] ?? null) : null;

            if ($parentRow !== null && $parentRow['period_key'] !== 'sin-periodo') {
                $sourcePeriod = $parentRow['period_key'];
            }

            $childRow = $this->mapChargeToLedgerRow($contract, $charge, $sourcePeriod);
            $childRow['is_penalty'] = true;

            if ($parentId !== null && isset($rowsById[$parentId])) {
                $rowsById[$parentId]['children'][] = $childRow;

                continue;
            }

            $childRow['children'] = [];
            $rowsById['penalty-'.$charge->id] = $childRow;
            $orderedParentIds[] = 'penalty-'.$charge->id;
        }

        return collect($orderedParentIds)
            ->map(function (int|string $rowId) use ($rowsById): ?array {
                $row = $rowsById[$rowId] ?? null;

                if ($row === null) {
                    return null;
                }

                usort(
                    $row['children'],
                    fn (array $left, array $right): int => [$left['charge_date'], $left['id']] <=> [$right['charge_date'], $right['id']],
                );

                return $row;
            })
            ->filter()
            ->values();
    }

    /**
     * @param Collection<int, array{
     *     period_key:string,
     *     period_label:string,
     *     amount:float,
     *     paid:float,
     *     balance:float
     * }> $ledgerRows
     * @return Collection<int, array{
     *     period_key:string,
     *     period_label:string,
     *     charges_total:float,
     *     paid_total:float,
     *     balance_total:float,
     *     rows:Collection<int, array{
     *         id:int,
     *         period_key:string,
     *         period_label:string,
     *         type:string,
     *         charge_date:string,
     *         due_date:string,
     *         amount:float,
     *         paid:float,
     *         balance:float,
     *         status_label:string,
     *         status_tone:string
     *     }>
     * }>
     */
    private function groupLedgerRows(Collection $ledgerRows): Collection
    {
        return $ledgerRows
            ->groupBy('period_key')
            ->map(function (Collection $rows): array {
                $flattenedRows = $rows->flatMap(function (array $row): Collection {
                    return collect([$row])->merge($row['children'] ?? []);
                });

                $operationalRows = $flattenedRows->reject(
                    fn (array $row): bool => $this->isDepositLedgerType($row['type'])
                );

                $periodLabel = (string) $rows->first()['period_label'];

                return [
                    'period_key' => (string) $rows->first()['period_key'],
                    'period_label' => $periodLabel,
                    'charges_total' => round((float) $operationalRows->sum('amount'), 2),
                    'paid_total' => round((float) $operationalRows->sum('paid'), 2),
                    'balance_total' => round((float) $operationalRows->sum('balance'), 2),
                    'rows' => $rows->values(),
                ];
            })
            ->values();
    }

    private function isDepositLedgerType(string $type): bool
    {
        return in_array($type, [
            Charge::TYPE_DEPOSIT_HOLD,
            Charge::TYPE_DEPOSIT_APPLY,
            Charge::TYPE_DEPOSIT_TRANSFER_OUT,
        ], true);
    }

    private function chargeTypeLabel(string $type): string
    {
        $key = 'contracts.charge_types.'.$type;
        $translated = __($key);

        return $translated !== $key ? $translated : $type;
    }

    /**
     * @return array{
     *     id:int,
     *     period_key:string,
     *     period_label:string,
     *     type:string,
     *     charge_date:string,
     *     due_date:string,
     *     amount:float,
     *     paid:float,
     *     balance:float,
     *     status_label:string,
     *     status_tone:string,
     *     is_penalty:bool,
     *     children:list<array<string, mixed>>
     * }
     */
    private function mapChargeToLedgerRow(Contract $contract, Charge $charge, ?string $periodOverride = null): array
    {
        $amount = round((float) $charge->amount, 2);

        if ($this->isDepositLedgerType($charge->type) && $amount < 0) {
            $amount = abs($amount);
        }

        if (
            $charge->type === Charge::TYPE_ADJUSTMENT
            && $amount < 0
            && (bool) data_get($charge->meta, 'settled_as_credit')
        ) {
            $paid = $amount;
            $balance = 0.0;
        } elseif ($this->isDepositLedgerType($charge->type)) {
            // Guarantee received at registration — not cobranza balance.
            $paid = $amount;
            $balance = 0.0;
        } else {
            $paid = round((float) max(min((float) $charge->allocated_amount, $amount), 0), 2);
            $balance = round($amount - $paid, 2);
        }

        $dueDate = $this->resolveDueDate($charge);
        $graceUntil = $this->resolveGraceUntil($charge, $contract, $dueDate);
        $status = $this->resolveChargeStatus($charge, $balance, $paid, $dueDate, $graceUntil);

        $periodValue = $periodOverride ?? (string) ($charge->period ?? '');
        if ($charge->type === Charge::TYPE_PENALTY && $periodValue === '') {
            $periodValue = $this->resolvePenaltySourcePeriod($contract, $charge) ?? '';
        }

        $periodKey = $periodValue !== '' ? $periodValue : 'sin-periodo';
        $periodLabel = $this->formatPeriodLabel($periodValue !== '' ? $periodValue : null);

        return [
            'id' => $charge->id,
            'period_key' => $periodKey,
            'period_label' => $periodLabel,
            'type' => $charge->type,
            'type_label' => $this->chargeTypeLabel($charge->type),
            'charge_date' => DateDisplay::formatDate($charge->charge_date, ''),
            'due_date' => $dueDate !== null ? DateDisplay::formatDate($dueDate) : '-',
            'amount' => $amount,
            'paid' => $paid,
            'balance' => $balance,
            'status_label' => $status['label'],
            'status_tone' => $status['tone'],
            'is_penalty' => $charge->type === Charge::TYPE_PENALTY,
        ];
    }

    /**
     * @param  Collection<string, Charge>  $rentChargesByPeriod
     */
    private function resolvePenaltyParentChargeId(
        Charge $penalty,
        ?string $sourcePeriod,
        Collection $rentChargesByPeriod,
    ): ?int {
        if (! is_string($sourcePeriod) || $sourcePeriod === '') {
            return null;
        }

        $rentCharge = $rentChargesByPeriod->get($sourcePeriod);

        return $rentCharge?->id;
    }

    private function resolvePenaltySourcePeriod(Contract $contract, Charge $penalty): ?string
    {
        $metaPeriod = data_get($penalty->meta, 'source_rent_period');

        if (is_string($metaPeriod) && preg_match('/^\d{4}-\d{2}$/', $metaPeriod) === 1) {
            return $metaPeriod;
        }

        if ($penalty->penalty_date !== null) {
            return CarbonImmutable::parse($penalty->penalty_date)->format('Y-m');
        }

        if ($penalty->charge_date !== null) {
            return CarbonImmutable::parse($penalty->charge_date)->format('Y-m');
        }

        return null;
    }

    private function formatPeriodLabel(?string $period): string
    {
        if ($period === null || $period === '' || preg_match('/^\d{4}-\d{2}$/', $period) !== 1) {
            return __('contracts.no_period');
        }

        return ucfirst(
            CarbonImmutable::createFromFormat('!Y-m', $period)
                ->locale(app()->getLocale())
                ->translatedFormat('F Y')
        );
    }

    private function resolveDueDate(Charge $charge): ?CarbonImmutable
    {
        if ($charge->due_date !== null) {
            return CarbonImmutable::parse($charge->due_date)->startOfDay();
        }

        $metaDueDate = data_get($charge->meta, 'due_date');

        if (is_string($metaDueDate) && $metaDueDate !== '') {
            try {
                return CarbonImmutable::parse($metaDueDate)->startOfDay();
            } catch (\Throwable) {
                // Fallback below.
            }
        }

        if ($charge->type === Charge::TYPE_RENT && $charge->charge_date !== null) {
            return CarbonImmutable::parse($charge->charge_date)->startOfDay();
        }

        return null;
    }

    private function resolveGraceUntil(Charge $charge, Contract $contract, ?CarbonImmutable $dueDate): ?CarbonImmutable
    {
        if ($charge->grace_until !== null) {
            return CarbonImmutable::parse($charge->grace_until)->startOfDay();
        }

        if ($dueDate === null) {
            return null;
        }

        $graceDays = (int) data_get($charge->meta, 'grace_days', $contract->grace_days ?? 0);

        return $dueDate->addDays(max($graceDays, 0));
    }

    /**
     * @return array{label:string, tone:string}
     */
    private function resolveChargeStatus(
        Charge $charge,
        float $balance,
        float $paid,
        ?CarbonImmutable $dueDate,
        ?CarbonImmutable $graceUntil
    ): array {
        if (in_array($charge->type, [
            Charge::TYPE_DEPOSIT_HOLD,
            Charge::TYPE_DEPOSIT_APPLY,
            Charge::TYPE_DEPOSIT_TRANSFER_OUT,
        ], true)) {
            return ['label' => __('contracts.charge_statuses.guarantee'), 'tone' => 'blue'];
        }

        if (
            $charge->type === Charge::TYPE_ADJUSTMENT
            && (float) $charge->amount < 0
            && (bool) data_get($charge->meta, 'settled_as_credit')
        ) {
            return ['label' => __('contracts.charge_statuses.applied'), 'tone' => 'blue'];
        }

        if ($balance <= 0) {
            return ['label' => __('contracts.charge_statuses.paid'), 'tone' => 'emerald'];
        }

        if ($charge->type === Charge::TYPE_RENT && $dueDate !== null && $graceUntil !== null) {
            $today = now()->startOfDay();

            if ($today->gt($graceUntil)) {
                return ['label' => __('contracts.charge_statuses.overdue'), 'tone' => 'red'];
            }

            if ($today->betweenIncluded($dueDate, $graceUntil)) {
                return ['label' => __('contracts.charge_statuses.grace'), 'tone' => 'amber'];
            }

            return ['label' => __('contracts.charge_statuses.upcoming'), 'tone' => 'blue'];
        }

        if ($paid > 0) {
            return ['label' => __('contracts.charge_statuses.partial'), 'tone' => 'amber'];
        }

        return ['label' => __('contracts.charge_statuses.pending'), 'tone' => 'slate'];
    }
}
