<?php

namespace App\Livewire\Contracts;

use App\Actions\Contracts\RenewContractAction;
use App\Models\Contract;
use App\Support\ContractAgreementShareUrl;
use App\Support\DateDisplay;
use App\Support\DepositBalanceService;
use App\Support\OrganizationSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class RenewWizard extends Component
{
    public bool $open = false;

    public string $step = 'form';

    public ?int $contractId = null;

    public string $starts_at = '';

    public string $ends_at = '';

    public string $rent_amount = '';

    public string $deposit_amount = '';

    public string $due_day = '';

    public string $grace_days = '';

    public bool $register_difference = false;

    public bool $generate_pdf = true;

    public bool $send_email = false;

    public ?string $pdfUrl = null;

    public ?string $shareUrl = null;

    public ?string $whatsAppUrl = null;

    public ?int $newContractId = null;

    public ?string $tenantName = null;

    public ?string $unitLabel = null;

    #[On('open-contract-renew')]
    public function open(int $contractId): void
    {
        if (! (auth()->user()?->can('contracts.manage') ?? false)) {
            abort(403);
        }

        $contract = Contract::query()
            ->with(['tenant:id,full_name,email,phone', 'unit.property:id,name'])
            ->findOrFail($contractId);

        $this->resetForm();
        $this->open = true;
        $this->contractId = $contract->id;
        $this->tenantName = $contract->tenant?->full_name;
        $unitName = trim((string) ($contract->unit?->property?->name.' / '.$contract->unit?->name));
        $this->unitLabel = $unitName !== '' ? $unitName : null;

        $availableDeposit = app(DepositBalanceService::class)
            ->availableDepositAmount($contract);

        $this->rent_amount = (string) $contract->rent_amount;
        // Keep transferred/current deposit; do not auto-match new rent.
        $this->deposit_amount = number_format($availableDeposit, 2, '.', '');
        $this->due_day = (string) $contract->due_day;
        $this->grace_days = (string) $contract->grace_days;

        $suggestedStart = $this->suggestedStartDate($contract);
        $this->starts_at = $suggestedStart->toDateString();
        $this->ends_at = $suggestedStart->addYear()->subDay()->toDateString();

        $tenantEmail = trim((string) ($contract->tenant?->email ?? ''));
        $this->send_email = $tenantEmail !== '' && (auth()->user()?->can('receipts.send') ?? false);
    }

    public function updatedGeneratePdf(bool $value): void
    {
        if (! $value) {
            $this->send_email = false;
        }
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

    public function renew(
        RenewContractAction $action,
        OrganizationSettingsService $settingsService,
    ): void {
        if (! (auth()->user()?->can('contracts.manage') ?? false)) {
            abort(403);
        }

        $this->validate($this->rules(), $this->messages());

        $contract = Contract::query()->findOrFail((int) $this->contractId);

        $generatePdf = $this->generate_pdf;
        $sendEmail = $generatePdf
            && $this->send_email
            && (auth()->user()?->can('receipts.send') ?? false);

        try {
            $result = $action->execute(
                source: $contract,
                input: [
                    'starts_at' => $this->starts_at,
                    'ends_at' => $this->ends_at,
                    'rent_amount' => $this->rent_amount,
                    'deposit_amount' => $this->deposit_amount,
                    'due_day' => (int) $this->due_day,
                    'grace_days' => (int) $this->grace_days,
                    'register_difference' => $this->register_difference,
                    'generate_pdf' => $generatePdf,
                    'send_email' => $sendEmail,
                ],
                userId: auth()->id(),
            );
        } catch (ValidationException $exception) {
            $errors = $exception->errors();

            if (isset($errors['landlord_name'][0])) {
                $this->addError('renew_general', $errors['landlord_name'][0]);

                return;
            }

            if (isset($errors['contract'][0])) {
                $this->addError('renew_general', $errors['contract'][0]);

                return;
            }

            $this->addError('renew_general', __('contracts.validation.renew_failed'));

            return;
        }

        $newContract = $result->newContract->fresh(['tenant', 'unit.property']);
        $this->newContractId = $newContract->id;

        if ($generatePdf) {
            $this->pdfUrl = route('contracts.agreement.pdf', ['contractId' => $newContract->id]);
            $this->shareUrl = ContractAgreementShareUrl::make($newContract->id);
            $this->whatsAppUrl = $this->buildContractWhatsAppUrl($newContract, $this->shareUrl, $settingsService);
        } else {
            $this->pdfUrl = null;
            $this->shareUrl = null;
            $this->whatsAppUrl = null;
        }

        session()->flash('success', __('contracts.flash.renewed'));
        $this->step = 'done';
        $this->dispatch('contract-renewed');
    }

    public function render(
        OrganizationSettingsService $settingsService,
        DepositBalanceService $depositBalanceService,
    ): View {
        $contract = $this->contractId !== null
            ? Contract::query()->find($this->contractId)
            : null;

        $availableDeposit = $contract !== null
            ? $depositBalanceService->availableDepositAmount($contract)
            : 0.0;

        $depositAmount = is_numeric($this->deposit_amount) ? (float) $this->deposit_amount : 0.0;
        $differenceAmount = round(max($depositAmount - $availableDeposit, 0), 2);

        $settings = $settingsService->current();
        $landlordName = is_string($settings['landlord_name'] ?? null) ? trim($settings['landlord_name']) : '';
        $landlordConfigured = $landlordName !== '';

        $tenantEmail = $contract?->tenant?->email;
        $canSendEmail = auth()->user()?->can('receipts.send') ?? false;

        return view('livewire.contracts.renew-wizard', [
            'availableDeposit' => $availableDeposit,
            'differenceAmount' => $differenceAmount,
            'landlordConfigured' => $landlordConfigured,
            'tenantEmail' => is_string($tenantEmail) && trim($tenantEmail) !== '' ? trim($tenantEmail) : null,
            'canSendEmail' => $canSendEmail,
        ]);
    }

    private function suggestedStartDate(Contract $contract): CarbonImmutable
    {
        $today = CarbonImmutable::now('America/Tijuana')->startOfDay();

        if ($contract->ends_at !== null) {
            $dayAfterEnd = CarbonImmutable::parse($contract->ends_at, 'America/Tijuana')->addDay()->startOfDay();

            if ($dayAfterEnd->greaterThanOrEqualTo($today)) {
                return $dayAfterEnd;
            }
        }

        return $today;
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
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'rent_amount' => ['required', 'numeric', 'min:0'],
            'deposit_amount' => ['required', 'numeric', 'min:0'],
            'due_day' => ['required', 'integer', 'min:1', 'max:31'],
            'grace_days' => ['required', 'integer', 'min:0', 'max:31'],
            'register_difference' => ['boolean'],
            'generate_pdf' => ['boolean'],
            'send_email' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'starts_at.required' => __('contracts.validation.starts_at_required'),
            'starts_at.date' => __('contracts.validation.starts_at_invalid'),
            'ends_at.required' => __('contracts.validation.ends_at_required'),
            'ends_at.date' => __('contracts.validation.ends_at_invalid'),
            'ends_at.after_or_equal' => __('contracts.validation.ends_at_after_start'),
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
        ];
    }

    private function resetForm(): void
    {
        $this->reset([
            'step',
            'contractId',
            'starts_at',
            'ends_at',
            'rent_amount',
            'deposit_amount',
            'due_day',
            'grace_days',
            'register_difference',
            'generate_pdf',
            'send_email',
            'pdfUrl',
            'shareUrl',
            'whatsAppUrl',
            'newContractId',
            'tenantName',
            'unitLabel',
        ]);

        $this->step = 'form';
        $this->register_difference = false;
        $this->send_email = false;
        $this->resetValidation();
    }
}
