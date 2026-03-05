<?php

namespace App\Livewire\Payments;

use App\Actions\Payments\RegisterContractPaymentAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Support\OrganizationSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class QuickRegisterModal extends Component
{
    use WithFileUploads;

    public bool $open = false;

    public string $step = 'search';

    public string $q = '';

    /** @var array<int, array<string, mixed>> */
    public array $searchResults = [];

    public ?int $contractId = null;

    /** @var array<string, mixed>|null */
    public ?array $contractSummary = null;

    public string $paidAt = '';

    public string $amount = '';

    public string $method = Payment::METHOD_TRANSFER;

    public ?string $reference = null;

    public bool $sendEmail = false;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $evidence = null;

    public ?string $receiptFolio = null;

    public ?string $shareUrl = null;

    public ?string $whatsAppUrl = null;

    public ?int $savedPaymentId = null;

    #[On('open-quick-payment')]
    public function open(?int $contractId = null): void
    {
        if (! (auth()->user()?->can('payments.create') ?? false)) {
            abort(403);
        }

        $this->resetForm();
        $this->open = true;

        if ($contractId !== null) {
            $this->selectContract($contractId);
        }

        $this->dispatch('qpm-opened');
    }

    #[On('close-quick-payment')]
    public function close(): void
    {
        $this->open = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->step = 'search';
        $this->q = '';
        $this->searchResults = [];
        $this->contractId = null;
        $this->contractSummary = null;
        $this->paidAt = now()->format('Y-m-d\TH:i');
        $this->amount = '';
        $this->method = Payment::METHOD_TRANSFER;
        $this->reference = null;
        $this->sendEmail = false;
        $this->evidence = null;
        $this->receiptFolio = null;
        $this->shareUrl = null;
        $this->whatsAppUrl = null;
        $this->savedPaymentId = null;
        $this->resetValidation();
    }

    public function updatedQ(): void
    {
        $trimmed = trim($this->q);

        if (mb_strlen($trimmed) < 2) {
            $this->searchResults = [];

            return;
        }

        $this->searchResults = $this->runContractSearch($trimmed);
    }

    public function selectContract(int $contractId): void
    {
        $this->contractId = $contractId;
        $this->contractSummary = $this->loadContractSummary($contractId);
        $this->step = 'form';
        $this->q = '';
        $this->searchResults = [];
    }

    public function backToSearch(): void
    {
        $this->contractId = null;
        $this->contractSummary = null;
        $this->step = 'search';
        $this->resetValidation();
    }

    public function save(RegisterContractPaymentAction $action, OrganizationSettingsService $settingsService): void
    {
        if (! (auth()->user()?->can('payments.create') ?? false)) {
            abort(403);
        }

        $this->validate($this->rules(), $this->messages());

        /** @var Contract $contract */
        $contract = Contract::query()->findOrFail($this->contractId);

        try {
            $payment = $action->execute(
                contract: $contract,
                data: [
                    'amount' => $this->amount,
                    'method' => $this->method,
                    'paid_at' => $this->paidAt,
                    'reference' => $this->reference ?: null,
                ],
                evidence: $this->evidence
            );
        } catch (ValidationException $e) {
            $this->addError('month_close', $e->errors()['month_close'][0] ?? 'No se pudo registrar el pago.');

            return;
        }

        $tenantEmail = $payment->contract?->tenant?->email;
        if ($this->sendEmail && is_string($tenantEmail) && $tenantEmail !== '') {
            Mail::to($tenantEmail)->send(new \App\Mail\PaymentReceiptMail($payment));
        }

        $this->shareUrl = URL::temporarySignedRoute(
            'payments.receipt.share',
            now()->addDays(7),
            ['paymentId' => $payment->id]
        );

        $organizationId = (int) $payment->organization_id;
        $settings = $settingsService->forOrganization($organizationId);
        $unitName = trim((string) ($payment->contract?->unit?->property?->name.' / '.$payment->contract?->unit?->name));
        $whatsAppMessage = $settingsService->renderTemplate(
            (string) $settings['whatsapp_template'],
            [
                'tenant_name' => (string) ($payment->contract?->tenant?->full_name ?? 'cliente'),
                'unit_name' => $unitName !== '' ? $unitName : 'unidad',
                'amount_due' => number_format((float) $payment->amount, 2, '.', ''),
                'shared_receipt_url' => $this->shareUrl,
            ]
        );

        $phone = $payment->contract?->tenant?->phone;
        $normalizedPhone = preg_replace('/\D+/', '', (string) $phone) ?: null;
        $encodedMessage = urlencode($whatsAppMessage);
        $this->whatsAppUrl = $normalizedPhone !== null
            ? "https://wa.me/{$normalizedPhone}?text={$encodedMessage}"
            : "https://wa.me/?text={$encodedMessage}";

        $this->receiptFolio = $payment->receipt_folio;
        $this->savedPaymentId = $payment->id;

        $this->dispatch('payment-registered');
        $this->step = 'done';
    }

    public function render(): View
    {
        return view('livewire.payments.quick-register-modal');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function runContractSearch(string $rawQ): array
    {
        $term = '%'.$rawQ.'%';
        $pendingExpression = $this->contractPendingAmountExpression();

        $allocationSubquery = PaymentAllocation::query()
            ->selectRaw('payment_allocations.charge_id, SUM(payment_allocations.amount) as allocated_total')
            ->groupBy('payment_allocations.charge_id');

        $includedBalanceTypes = [
            Charge::TYPE_RENT,
            Charge::TYPE_PENALTY,
            Charge::TYPE_SERVICE,
            Charge::TYPE_DAMAGE,
            Charge::TYPE_CLEANING,
            Charge::TYPE_OTHER,
            Charge::TYPE_ADJUSTMENT,
            Charge::TYPE_MOVEOUT,
            Charge::TYPE_DEPOSIT_APPLY,
        ];

        $balanceSubquery = Charge::query()
            ->selectRaw("charges.contract_id, {$pendingExpression} as pending_balance")
            ->leftJoinSub($allocationSubquery, 'alloc', function ($join): void {
                $join->on('alloc.charge_id', '=', 'charges.id');
            })
            ->whereIn('charges.type', $includedBalanceTypes)
            ->groupBy('charges.contract_id');

        $query = Contract::query()
            ->select([
                'contracts.id as id',
                'tenants.full_name as tenant_name',
                'units.name as unit_name',
                'units.code as unit_code',
                'properties.name as property_name',
                DB::raw('COALESCE(balance_stats.pending_balance, 0) as pending_balance'),
            ])
            ->where('contracts.status', Contract::STATUS_ACTIVE)
            ->join('units', 'units.id', '=', 'contracts.unit_id')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->join('tenants', 'tenants.id', '=', 'contracts.tenant_id')
            ->leftJoinSub($balanceSubquery, 'balance_stats', function ($join): void {
                $join->on('balance_stats.contract_id', '=', 'contracts.id');
            })
            ->whereColumn('units.organization_id', 'contracts.organization_id')
            ->whereColumn('properties.organization_id', 'contracts.organization_id')
            ->whereColumn('tenants.organization_id', 'contracts.organization_id')
            ->where(function ($q) use ($term, $rawQ): void {
                $q->where('tenants.full_name', 'like', $term)
                    ->orWhere('tenants.email', 'like', $term)
                    ->orWhere('tenants.phone', 'like', $term)
                    ->orWhere('properties.name', 'like', $term)
                    ->orWhere('units.name', 'like', $term)
                    ->orWhere('units.code', 'like', $term);

                if (is_numeric($rawQ)) {
                    $q->orWhere('contracts.id', (int) $rawQ);
                }
            })
            ->limit(8);

        return $query->get()->map(fn ($row): array => [
            'id' => (int) $row->id,
            'tenant_name' => (string) $row->tenant_name,
            'unit_name' => (string) $row->unit_name,
            'unit_code' => (string) $row->unit_code,
            'property_name' => (string) $row->property_name,
            'pending_balance' => (float) $row->pending_balance,
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function loadContractSummary(int $contractId): array
    {
        $contract = Contract::query()
            ->with(['unit.property', 'tenant', 'creditBalance'])
            ->findOrFail($contractId);

        $pendingBalance = $this->computePendingBalance($contractId);
        $overdueStatus = $this->computeOverdueStatus($contractId);

        return [
            'id' => $contract->id,
            'tenant_name' => (string) $contract->tenant?->full_name,
            'tenant_email' => $contract->tenant?->email,
            'tenant_phone' => $contract->tenant?->phone,
            'unit_label' => trim((string) ($contract->unit?->property?->name.' / '.$contract->unit?->name)),
            'pending_balance' => $pendingBalance,
            'credit_balance' => (float) ($contract->creditBalance?->balance ?? 0),
            'overdue_status' => $overdueStatus['status'],
            'overdue_days' => $overdueStatus['days'],
        ];
    }

    private function computePendingBalance(int $contractId): float
    {
        $rawPending = 'charges.amount - COALESCE(alloc.allocated_total, 0)';
        $pendingExpression = $this->databaseDriver() === 'sqlite'
            ? "MAX(SUM({$rawPending}), 0)"
            : "GREATEST(SUM({$rawPending}), 0)";

        $allocationSubquery = PaymentAllocation::query()
            ->selectRaw('payment_allocations.charge_id, SUM(payment_allocations.amount) as allocated_total')
            ->groupBy('payment_allocations.charge_id');

        $includedBalanceTypes = [
            Charge::TYPE_RENT,
            Charge::TYPE_PENALTY,
            Charge::TYPE_SERVICE,
            Charge::TYPE_DAMAGE,
            Charge::TYPE_CLEANING,
            Charge::TYPE_OTHER,
            Charge::TYPE_ADJUSTMENT,
            Charge::TYPE_MOVEOUT,
            Charge::TYPE_DEPOSIT_APPLY,
        ];

        $result = Charge::query()
            ->selectRaw("{$pendingExpression} as pending_balance")
            ->leftJoinSub($allocationSubquery, 'alloc', function ($join): void {
                $join->on('alloc.charge_id', '=', 'charges.id');
            })
            ->where('charges.contract_id', $contractId)
            ->whereIn('charges.type', $includedBalanceTypes)
            ->value('pending_balance');

        return round((float) $result, 2);
    }

    /**
     * @return array{status: string, days: int}
     */
    private function computeOverdueStatus(int $contractId): array
    {
        $today = now('America/Tijuana')->toDateString();

        $allocationSubquery = PaymentAllocation::query()
            ->selectRaw('payment_allocations.charge_id, SUM(payment_allocations.amount) as allocated_total')
            ->groupBy('payment_allocations.charge_id');

        $dueDateExpression = 'COALESCE(charges.due_date, charges.charge_date)';
        $graceUntilExpression = "COALESCE(charges.grace_until, {$dueDateExpression})";
        $rawPending = 'charges.amount - COALESCE(alloc.allocated_total, 0)';
        $pendingExpression = $this->databaseDriver() === 'sqlite'
            ? "MAX({$rawPending}, 0)"
            : "GREATEST({$rawPending}, 0)";

        $oldest = Charge::query()
            ->selectRaw("{$dueDateExpression} as due_date, {$graceUntilExpression} as grace_until")
            ->leftJoinSub($allocationSubquery, 'alloc', function ($join): void {
                $join->on('alloc.charge_id', '=', 'charges.id');
            })
            ->where('charges.contract_id', $contractId)
            ->where('charges.type', Charge::TYPE_RENT)
            ->whereRaw("{$pendingExpression} > 0")
            ->orderByRaw("{$dueDateExpression} asc")
            ->orderBy('charges.id', 'asc')
            ->first();

        if ($oldest === null) {
            return ['status' => 'current', 'days' => 0];
        }

        $graceUntil = $oldest->grace_until;
        $dueDate = $oldest->due_date;

        if ($today > $graceUntil) {
            $days = $this->databaseDriver() === 'sqlite'
                ? (int) DB::selectOne('SELECT CAST(julianday(?) - julianday(?) AS INTEGER) as d', [$today, $graceUntil])?->d
                : (int) DB::selectOne('SELECT DATEDIFF(?, ?) as d', [$today, $graceUntil])?->d;

            return ['status' => 'overdue', 'days' => max(0, $days)];
        }

        if ($today >= $dueDate && $today <= $graceUntil) {
            return ['status' => 'grace', 'days' => 0];
        }

        return ['status' => 'current', 'days' => 0];
    }

    private function contractPendingAmountExpression(): string
    {
        $rawPending = 'charges.amount - COALESCE(alloc.allocated_total, 0)';

        if ($this->databaseDriver() === 'sqlite') {
            return "MAX(SUM({$rawPending}), 0)";
        }

        return "GREATEST(SUM({$rawPending}), 0)";
    }

    private function databaseDriver(): string
    {
        return DB::connection()->getDriverName();
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'contractId' => ['required', 'integer'],
            'paidAt' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in([Payment::METHOD_CASH, Payment::METHOD_TRANSFER])],
            'reference' => ['nullable', 'string', 'max:120'],
            'evidence' => ['nullable', 'file', 'max:5120', 'mimes:jpg,jpeg,png,pdf'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'contractId.required' => 'Debes seleccionar un contrato.',
            'paidAt.required' => 'La fecha de pago es obligatoria.',
            'paidAt.date' => 'La fecha de pago no es válida.',
            'amount.required' => 'El monto es obligatorio.',
            'amount.numeric' => 'El monto debe ser numérico.',
            'amount.min' => 'El monto debe ser mayor a cero.',
            'method.required' => 'Selecciona un método de pago.',
            'method.in' => 'El método de pago no es válido.',
            'reference.max' => 'La referencia no debe exceder 120 caracteres.',
            'evidence.max' => 'La evidencia no debe exceder 5 MB.',
            'evidence.mimes' => 'La evidencia debe ser JPG, PNG o PDF.',
        ];
    }
}
