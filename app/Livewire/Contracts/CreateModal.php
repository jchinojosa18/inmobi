<?php

namespace App\Livewire\Contracts;

use App\Actions\Contracts\GenerateLeaseAgreementPdfAction;
use App\Mail\ContractAgreementMail;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\AuditLogger;
use App\Support\ContractAgreementShareUrl;
use App\Support\ContractDocumentCategory;
use App\Support\DateDisplay;
use App\Support\OrganizationSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class CreateModal extends Component
{
    private const MAX_DAILY_RATE_DECIMAL = 0.5;

    public bool $open = false;

    public string $step = 'form';

    public bool $send_email = false;

    public bool $generate_pdf = true;

    public ?string $pdfUrl = null;

    public ?string $shareUrl = null;

    public ?string $whatsAppUrl = null;

    public ?int $createdContractId = null;

    public ?string $tenantName = null;

    public ?string $unitLabel = null;

    public ?int $contractId = null;

    public ?int $unit_id = null;

    public ?int $tenant_id = null;

    public string $rent_amount = '';

    public string $deposit_amount = '';

    public string $due_day = '';

    public string $grace_days = '';

    public string $penalty_rate_daily = '';

    public string $status = Contract::STATUS_ACTIVE;

    public string $starts_at = '';

    public ?string $ends_at = null;

    public ?string $meta_notes = null;

    #[On('open-contract-create')]
    public function open(?int $unitId = null): void
    {
        if (! (auth()->user()?->can('contracts.manage') ?? false)) {
            abort(403);
        }

        $this->resetForm();
        $this->open = true;

        if ($unitId !== null && $unitId > 0) {
            $unit = Unit::query()
                ->where('status', 'active')
                ->whereDoesntHave('contracts', function ($query): void {
                    $query->where('status', Contract::STATUS_ACTIVE);
                })
                ->find($unitId);

            if ($unit !== null) {
                $this->unit_id = $unit->id;
            }
        }
    }

    #[On('open-contract-edit')]
    public function openEdit(int $contractId): void
    {
        if (! (auth()->user()?->can('contracts.manage') ?? false)) {
            abort(403);
        }

        $contract = Contract::query()->findOrFail($contractId);

        $this->resetForm();
        $this->contractId = $contract->id;
        $this->unit_id = $contract->unit_id;
        $this->tenant_id = $contract->tenant_id;
        $this->rent_amount = (string) $contract->rent_amount;
        $this->deposit_amount = (string) $contract->deposit_amount;
        $this->due_day = (string) $contract->due_day;
        $this->grace_days = (string) $contract->grace_days;
        $this->penalty_rate_daily = $this->toDisplayPenaltyRate((float) $contract->penalty_rate_daily);
        $this->status = $contract->status;
        $this->starts_at = optional($contract->starts_at)->format('Y-m-d') ?: now()->toDateString();
        $this->ends_at = optional($contract->ends_at)->format('Y-m-d');
        $this->meta_notes = data_get($contract->meta, 'notes');
        $this->open = true;
    }

    public function updatedGeneratePdf(bool $value): void
    {
        if (! $value) {
            $this->send_email = false;

            return;
        }

        $this->updatedTenantId($this->tenant_id);
    }

    public function updatedTenantId(?int $value): void
    {
        if (! (auth()->user()?->can('receipts.send') ?? false) || ! $this->generate_pdf) {
            $this->send_email = false;

            return;
        }

        if ($value === null || $value <= 0) {
            $this->send_email = false;

            return;
        }

        $tenant = Tenant::query()->find($value);
        $email = is_string($tenant?->email) ? trim($tenant->email) : '';

        $this->send_email = $email !== '';
    }

    public function cancelForm(): void
    {
        $this->close();
    }

    public function close(): void
    {
        $this->open = false;
        $this->resetForm();
    }

    public function save(
        OrganizationSettingsService $organizationSettingsService,
        GenerateLeaseAgreementPdfAction $generateLeaseAgreementPdfAction,
    ): mixed {
        if (! (auth()->user()?->can('contracts.manage') ?? false)) {
            abort(403);
        }

        $validated = $this->validate($this->rules(), $this->messages());

        $normalizedPenaltyRate = $this->normalizePenaltyRateDaily((float) $validated['penalty_rate_daily']);

        if ($normalizedPenaltyRate <= 0 || $normalizedPenaltyRate > 1) {
            $this->addError('penalty_rate_daily', __('contracts.validation.penalty_rate_normalized'));

            return null;
        }

        if ($normalizedPenaltyRate > self::MAX_DAILY_RATE_DECIMAL) {
            $this->addError('penalty_rate_daily', __('contracts.validation.penalty_rate_security'));

            return null;
        }

        if ($this->generate_pdf && ! $this->assertLandlordNameConfigured($organizationSettingsService)) {
            return null;
        }

        $validated['penalty_rate_daily'] = $normalizedPenaltyRate;
        $this->penalty_rate_daily = $this->toDisplayPenaltyRate($normalizedPenaltyRate);

        try {
            $contract = DB::transaction(function () use ($validated): Contract {
                $unit = Unit::query()->findOrFail((int) $validated['unit_id']);
                $tenant = Tenant::query()->findOrFail((int) $validated['tenant_id']);

                $contract = $this->contractId !== null
                    ? Contract::query()->findOrFail($this->contractId)
                    : new Contract;

                $contract->organization_id = auth()->user()?->organization_id;

                if ($this->contractId === null) {
                    $contract->unit()->associate($unit);
                    $contract->tenant()->associate($tenant);
                }

                $contract->rent_amount = $validated['rent_amount'];
                $contract->deposit_amount = $validated['deposit_amount'];
                $contract->due_day = (int) $validated['due_day'];
                $contract->grace_days = (int) $validated['grace_days'];
                $contract->penalty_rate_daily = $validated['penalty_rate_daily'];
                $contract->status = $validated['status'];
                $contract->starts_at = $validated['starts_at'];
                $contract->ends_at = $validated['ends_at'];
                $contract->meta = [
                    'notes' => $validated['meta_notes'] ?: null,
                ];
                $contract->save();

                return $contract;
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() === '23000') {
                $this->addError('unit_id', __('contracts.validation.unit_active_contract'));

                return null;
            }

            throw $exception;
        }

        $isNew = $this->contractId === null;

        app(AuditLogger::class)->log(
            action: $isNew ? 'contract.created' : 'contract.updated',
            auditable: $contract,
            summary: sprintf(
                'Contrato #%d %s para unidad #%d',
                $contract->id,
                $isNew ? 'creado' : 'actualizado',
                $contract->unit_id,
            ),
            meta: [
                'contract_id' => $contract->id,
                'unit_id' => $contract->unit_id,
                'tenant_id' => $contract->tenant_id,
                'rent_amount' => (float) $contract->rent_amount,
                'status' => $contract->status,
                'starts_at' => $contract->starts_at?->toDateString(),
            ],
        );

        if ($isNew) {
            if ($this->generate_pdf) {
                try {
                    $generateLeaseAgreementPdfAction->execute($contract->fresh(), auth()->id());
                } catch (ValidationException $e) {
                    $this->addError('landlord_name', $e->errors()['landlord_name'][0] ?? __('contracts.validation.renew_failed'));

                    return null;
                }

                $contract->loadMissing('tenant');
                $tenantEmail = is_string($contract->tenant?->email) ? trim($contract->tenant->email) : '';
                $sendEmail = $this->send_email
                    && (auth()->user()?->can('receipts.send') ?? false)
                    && $tenantEmail !== '';

                if ($sendEmail) {
                    try {
                        Mail::to($tenantEmail)->send(new ContractAgreementMail($contract));
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }

                $contract = $contract->fresh(['tenant', 'unit.property']);
                $this->pdfUrl = route('contracts.agreement.pdf', ['contractId' => $contract->id]);
                $this->shareUrl = ContractAgreementShareUrl::make($contract->id);
                $this->whatsAppUrl = $this->buildContractWhatsAppUrl($contract, $this->shareUrl, $organizationSettingsService);
            } else {
                $contract = $contract->fresh(['tenant', 'unit.property']);
                $this->pdfUrl = null;
                $this->shareUrl = null;
                $this->whatsAppUrl = null;
            }

            $this->createdContractId = $contract->id;
            $this->tenantName = (string) ($contract->tenant?->full_name ?? '');
            $this->unitLabel = trim((string) ($contract->unit?->property?->name.' / '.$contract->unit?->name));
            $this->step = 'done';
            session()->flash('success', __('contracts.flash.contract_created'));
            $this->dispatch('contract-updated');

            return null;
        }

        if ($this->generate_pdf) {
            try {
                if (! $this->replaceContractAgreementDocument($contract, $generateLeaseAgreementPdfAction)) {
                    $this->addError('contract_document', __('contracts.validation.manual_contract_document_blocks_regenerate'));

                    return null;
                }
            } catch (ValidationException $e) {
                $this->addError('landlord_name', $e->errors()['landlord_name'][0] ?? __('contracts.validation.renew_failed'));

                return null;
            }
        }

        session()->flash('success', __('contracts.flash.contract_updated'));

        $this->close();

        $this->dispatch('contract-updated');

        return null;
    }

    public function render(): View
    {
        $unitsQuery = Unit::query()
            ->where('status', 'active')
            ->with('property:id,name')
            ->orderBy('property_id')
            ->orderBy('name');

        if ($this->contractId === null) {
            $unitsQuery->whereDoesntHave('contracts', function ($query): void {
                $query->where('status', Contract::STATUS_ACTIVE);
            });
        } else {
            $unitsQuery->whereKey($this->unit_id);
        }

        $units = $unitsQuery->get(['id', 'property_id', 'name', 'code']);

        $tenantsQuery = Tenant::query()->orderBy('full_name');

        if ($this->contractId === null) {
            $tenantsQuery->where('status', 'active');
        } else {
            $tenantsQuery->whereKey($this->tenant_id);
        }

        $tenants = $tenantsQuery->get(['id', 'full_name', 'email']);

        $selectedTenantEmail = null;

        if ($this->tenant_id !== null) {
            $selectedTenant = $tenants->firstWhere('id', $this->tenant_id);
            $email = is_string($selectedTenant?->email) ? trim($selectedTenant->email) : '';

            if ($email !== '') {
                $selectedTenantEmail = $email;
            }
        }

        return view('livewire.contracts.create-modal', [
            'units' => $units,
            'tenants' => $tenants,
            'isEdit' => $this->contractId !== null,
            'canSendReceipts' => auth()->user()?->can('receipts.send') ?? false,
            'selectedTenantEmail' => $selectedTenantEmail,
        ]);
    }

    private function buildContractWhatsAppUrl(
        Contract $contract,
        string $shareUrl,
        OrganizationSettingsService $settingsService,
    ): ?string {
        $phone = preg_replace('/\D+/', '', (string) $contract->tenant?->phone);

        if ($phone === '') {
            return null;
        }

        $settings = $settingsService->forOrganization((int) $contract->organization_id);
        $unitName = trim((string) ($contract->unit?->property?->name.' / '.$contract->unit?->name));

        $text = $settingsService->renderTemplate(
            (string) $settings['contract_whatsapp_template'],
            [
                'tenant_name' => (string) ($contract->tenant?->full_name ?? 'cliente'),
                'unit_name' => $unitName !== '' ? $unitName : 'unidad',
                'shared_contract_url' => $shareUrl,
                'rent_amount' => number_format((float) $contract->rent_amount, 2, '.', ''),
                'starts_at' => DateDisplay::formatDate($contract->starts_at),
                'ends_at' => DateDisplay::formatDate($contract->ends_at),
            ]
        );

        return 'https://wa.me/'.$phone.'?text='.rawurlencode($text);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'unit_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->status !== Contract::STATUS_ACTIVE) {
                        return;
                    }

                    $query = Contract::query()
                        ->where('unit_id', $value)
                        ->where('status', Contract::STATUS_ACTIVE);

                    if ($this->contractId !== null) {
                        $query->whereKeyNot($this->contractId);
                    }

                    if ($query->exists()) {
                        $fail(__('contracts.validation.unit_active_contract'));
                    }
                },
            ],
            'tenant_id' => ['required', 'integer'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['required', 'numeric', 'min:0'],
            'due_day' => ['required', 'integer', 'min:1', 'max:31'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:31'],
            'penalty_rate_daily' => ['required', 'numeric', 'min:0.0001', 'max:100'],
            'status' => ['required', Rule::in([Contract::STATUS_ACTIVE, Contract::STATUS_ENDED])],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'meta_notes' => ['nullable', 'string', 'max:1000'],
            'send_email' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'unit_id.required' => __('contracts.validation.unit_required'),
            'tenant_id.required' => __('contracts.validation.tenant_required'),
            'rent_amount.required' => __('contracts.validation.rent_required'),
            'rent_amount.numeric' => __('contracts.validation.rent_numeric'),
            'rent_amount.min' => __('contracts.validation.rent_min'),
            'deposit_amount.required' => __('contracts.validation.deposit_required'),
            'deposit_amount.numeric' => __('contracts.validation.deposit_numeric'),
            'deposit_amount.min' => __('contracts.validation.deposit_min'),
            'due_day.required' => __('contracts.validation.due_day_required'),
            'due_day.integer' => __('contracts.validation.due_day_integer'),
            'due_day.min' => __('contracts.validation.due_day_min'),
            'due_day.max' => __('contracts.validation.due_day_max'),
            'grace_days.required' => __('contracts.validation.grace_days_required'),
            'grace_days.integer' => __('contracts.validation.grace_days_integer'),
            'grace_days.min' => __('contracts.validation.grace_days_min'),
            'grace_days.max' => __('contracts.validation.grace_days_max'),
            'penalty_rate_daily.required' => __('contracts.validation.penalty_rate_required'),
            'penalty_rate_daily.numeric' => __('contracts.validation.penalty_rate_numeric'),
            'penalty_rate_daily.min' => __('contracts.validation.penalty_rate_min'),
            'penalty_rate_daily.max' => __('contracts.validation.penalty_rate_max'),
            'status.required' => __('contracts.validation.status_required'),
            'status.in' => __('contracts.validation.status_invalid'),
            'starts_at.required' => __('contracts.validation.starts_at_required'),
            'starts_at.date' => __('contracts.validation.starts_at_invalid'),
            'ends_at.required' => __('contracts.validation.ends_at_required'),
            'ends_at.date' => __('contracts.validation.ends_at_invalid'),
            'ends_at.after_or_equal' => __('contracts.validation.ends_at_after_start'),
            'meta_notes.max' => __('contracts.validation.notes_max'),
        ];
    }

    private function normalizePenaltyRateDaily(float $value): float
    {
        if ($value > 1) {
            return round(round($value, 2) / 100, 4);
        }

        return round($value, 4);
    }

    private function toDisplayPenaltyRate(float $storedDecimalRate): string
    {
        return number_format($storedDecimalRate * 100, 2, '.', '');
    }

    private function replaceContractAgreementDocument(
        Contract $contract,
        GenerateLeaseAgreementPdfAction $generateLeaseAgreementPdfAction,
    ): bool {
        $existing = Document::query()
            ->where('documentable_type', Contract::class)
            ->where('documentable_id', $contract->id)
            ->where('category', ContractDocumentCategory::Contract)
            ->get();

        $generated = $existing->filter(fn (Document $document): bool => $document->isGeneratedLeaseAgreement());
        $manual = $existing->reject(fn (Document $document): bool => $document->isGeneratedLeaseAgreement());

        if ($manual->isNotEmpty()) {
            return false;
        }

        if ($generated->isEmpty()) {
            $generateLeaseAgreementPdfAction->execute($contract->fresh(), auth()->id());

            return true;
        }

        foreach ($generated as $document) {
            $document->update(['category' => null]);
        }

        try {
            $generateLeaseAgreementPdfAction->execute($contract->fresh(), auth()->id());
        } catch (\Throwable $exception) {
            foreach ($generated as $document) {
                $document->update(['category' => ContractDocumentCategory::Contract]);
            }

            throw $exception;
        }

        foreach ($generated as $document) {
            $this->deleteGeneratedLeaseAgreementDocument($document);
        }

        return true;
    }

    private function deleteGeneratedLeaseAgreementDocument(Document $document): void
    {
        $disk = (string) data_get($document->meta, 'disk', config('filesystems.documents_disk', 'local'));

        if (Storage::disk($disk)->exists($document->path)) {
            Storage::disk($disk)->delete($document->path);
        }

        $document->update(['category' => null]);
        $document->delete();
    }

    private function assertLandlordNameConfigured(OrganizationSettingsService $organizationSettingsService): bool
    {
        $settings = $organizationSettingsService->forOrganization((int) auth()->user()?->organization_id);
        $landlordName = is_string($settings['landlord_name'] ?? null) ? trim($settings['landlord_name']) : '';

        if ($landlordName === '') {
            $this->addError('landlord_name', 'Configure el nombre del arrendador en Configuración antes de generar el contrato.');

            return false;
        }

        return true;
    }

    private function resetForm(): void
    {
        $this->reset([
            'step',
            'send_email',
            'generate_pdf',
            'pdfUrl',
            'shareUrl',
            'whatsAppUrl',
            'createdContractId',
            'tenantName',
            'unitLabel',
            'contractId',
            'unit_id',
            'tenant_id',
            'rent_amount',
            'deposit_amount',
            'due_day',
            'grace_days',
            'penalty_rate_daily',
            'status',
            'starts_at',
            'ends_at',
            'meta_notes',
        ]);

        $this->step = 'form';
        $this->send_email = false;
        $this->generate_pdf = true;
        $this->deposit_amount = '';
        $this->due_day = '';
        $this->grace_days = '';
        $this->penalty_rate_daily = '';
        $this->status = Contract::STATUS_ACTIVE;
        $this->starts_at = now()->toDateString();
        $this->resetValidation();
    }
}
