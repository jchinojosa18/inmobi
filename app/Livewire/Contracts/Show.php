<?php

namespace App\Livewire\Contracts;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Show extends Component
{
    public Contract $contract;

    public string $adjustment_amount = '';

    public string $adjustment_charge_date = '';

    public string $adjustment_reason = '';

    public ?string $adjustment_comment = null;

    public ?string $adjustment_linked_to = null;

    public function mount(Contract $contract): void
    {
        if (! (auth()->user()?->can('contracts.view') ?? false)) {
            abort(403);
        }

        $this->contract = $contract;
        $this->adjustment_charge_date = now('America/Tijuana')->toDateString();
    }

    public function createAdjustment(): void
    {
        if (! (auth()->user()?->can('charges.manage') ?? false)) {
            abort(403);
        }

        $validated = $this->validate([
            'adjustment_amount' => ['required', 'numeric', 'not_in:0'],
            'adjustment_charge_date' => ['required', 'date'],
            'adjustment_reason' => ['required', 'string', 'max:200'],
            'adjustment_comment' => ['nullable', 'string', 'max:500'],
            'adjustment_linked_to' => ['nullable', 'string', 'max:120'],
        ], [
            'adjustment_amount.required' => 'El monto del ajuste es obligatorio.',
            'adjustment_amount.numeric' => 'El monto del ajuste debe ser numérico.',
            'adjustment_amount.not_in' => 'El monto del ajuste no puede ser cero.',
            'adjustment_charge_date.required' => 'La fecha del ajuste es obligatoria.',
            'adjustment_charge_date.date' => 'La fecha del ajuste no es válida.',
            'adjustment_reason.required' => 'La razón del ajuste es obligatoria.',
            'adjustment_reason.max' => 'La razón del ajuste no debe exceder 200 caracteres.',
            'adjustment_comment.max' => 'El comentario no debe exceder 500 caracteres.',
            'adjustment_linked_to.max' => 'La referencia no debe exceder 120 caracteres.',
        ]);

        $chargeDate = CarbonImmutable::parse($validated['adjustment_charge_date'], 'America/Tijuana')->startOfDay();

        try {
            Charge::query()->create([
                'organization_id' => $this->contract->organization_id,
                'contract_id' => $this->contract->id,
                'unit_id' => $this->contract->unit_id,
                'type' => Charge::TYPE_ADJUSTMENT,
                'period' => $chargeDate->format('Y-m'),
                'charge_date' => $chargeDate->toDateString(),
                'amount' => (float) $validated['adjustment_amount'],
                'meta' => [
                    'reason' => trim((string) $validated['adjustment_reason']),
                    'comment' => trim((string) ($validated['adjustment_comment'] ?? '')),
                    'linked_to' => trim((string) ($validated['adjustment_linked_to'] ?? '')),
                    'created_from' => 'contract_show_adjustment',
                    'created_by_user_id' => auth()->id(),
                ],
            ]);
        } catch (ValidationException $exception) {
            $message = $exception->errors()['month_close'][0] ?? 'No se pudo registrar el ajuste.';
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
        session()->flash('success', 'Ajuste registrado correctamente.');
    }

    public function render(): View
    {
        $contract = Contract::query()
            ->with(['unit.property', 'tenant', 'creditBalance'])
            ->findOrFail($this->contract->id);

        $ledgerRows = $this->buildLedgerRows($contract);
        $groupedLedger = $this->groupLedgerRows($ledgerRows);

        $chargesTotal = round((float) $ledgerRows->sum('amount'), 2);
        $allocatedTotal = round((float) $ledgerRows->sum('paid'), 2);
        $creditTotal = (float) ($contract->creditBalance?->balance ?? 0);
        $pendingBalance = max(0, round((float) $ledgerRows->sum('balance'), 2));

        $payments = Payment::query()
            ->where('contract_id', $contract->id)
            ->withSum('allocations as allocated_amount', 'amount')
            ->latest('paid_at')
            ->limit(10)
            ->get()
            ->map(function (Payment $payment): array {
                $shareUrl = URL::temporarySignedRoute(
                    'payments.receipt.share',
                    now()->addDays(7),
                    ['paymentId' => $payment->id]
                );

                return [
                    'id' => $payment->id,
                    'folio' => $payment->receipt_folio,
                    'paid_at' => $payment->paid_at,
                    'method' => $payment->method,
                    'amount' => (float) $payment->amount,
                    'allocated_amount' => (float) ($payment->allocated_amount ?? 0),
                    'show_url' => route('payments.show', $payment),
                    'receipt_url' => route('payments.receipt.pdf', ['paymentId' => $payment->id]),
                    'share_url' => $shareUrl,
                ];
            });

        return view('livewire.contracts.show', [
            'contract' => $contract,
            'chargesTotal' => $chargesTotal,
            'allocatedTotal' => $allocatedTotal,
            'creditTotal' => $creditTotal,
            'pendingBalance' => $pendingBalance,
            'ledgerGroups' => $groupedLedger,
            'payments' => $payments,
            'canManageContracts' => auth()->user()?->can('contracts.manage') ?? false,
            'canCreatePayments' => auth()->user()?->can('payments.create') ?? false,
            'canManageCharges' => auth()->user()?->can('charges.manage') ?? false,
            'canViewPayments' => auth()->user()?->can('payments.view') ?? false,
            'canSettleContracts' => auth()->user()?->can('contracts.settle') ?? false,
        ])->layout('layouts.app', [
            'title' => 'Detalle de contrato',
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
     *     status_tone:string
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

        return $charges->map(fn (Charge $charge): array => $this->mapChargeToLedgerRow($contract, $charge));
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
                return [
                    'period_key' => (string) $rows->first()['period_key'],
                    'period_label' => (string) $rows->first()['period_label'],
                    'charges_total' => round((float) $rows->sum('amount'), 2),
                    'paid_total' => round((float) $rows->sum('paid'), 2),
                    'balance_total' => round((float) $rows->sum('balance'), 2),
                    'rows' => $rows->values(),
                ];
            })
            ->values();
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
     *     status_tone:string
     * }
     */
    private function mapChargeToLedgerRow(Contract $contract, Charge $charge): array
    {
        $amount = round((float) $charge->amount, 2);
        $paid = round((float) max(min((float) $charge->allocated_amount, $amount), 0), 2);
        $balance = round($amount - $paid, 2);

        $dueDate = $this->resolveDueDate($charge);
        $graceUntil = $this->resolveGraceUntil($charge, $contract, $dueDate);
        $status = $this->resolveChargeStatus($charge, $balance, $paid, $dueDate, $graceUntil);

        $periodValue = (string) ($charge->period ?? '');
        $periodLabel = $periodValue !== '' ? $periodValue : 'Sin periodo';

        return [
            'id' => $charge->id,
            'period_key' => $periodValue !== '' ? $periodValue : 'sin-periodo',
            'period_label' => $periodLabel,
            'type' => $charge->type,
            'charge_date' => optional($charge->charge_date)->format('Y-m-d') ?? '',
            'due_date' => $dueDate?->format('Y-m-d') ?? '-',
            'amount' => $amount,
            'paid' => $paid,
            'balance' => $balance,
            'status_label' => $status['label'],
            'status_tone' => $status['tone'],
        ];
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
        if ($balance <= 0) {
            return ['label' => 'Pagado', 'tone' => 'emerald'];
        }

        if ($charge->type === Charge::TYPE_RENT && $dueDate !== null && $graceUntil !== null) {
            $today = now()->startOfDay();

            if ($today->gt($graceUntil)) {
                return ['label' => 'Vencido', 'tone' => 'red'];
            }

            if ($today->betweenIncluded($dueDate, $graceUntil)) {
                return ['label' => 'En gracia', 'tone' => 'amber'];
            }

            return ['label' => 'Por vencer', 'tone' => 'blue'];
        }

        if ($paid > 0) {
            return ['label' => 'Parcial', 'tone' => 'amber'];
        }

        return ['label' => 'Pendiente', 'tone' => 'slate'];
    }
}
