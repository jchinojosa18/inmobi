<?php

namespace App\Livewire\Settings;

use App\Models\ExpenseCategory;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Support\AuditLogger;
use App\Support\OrganizationSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Index extends Component
{
    public string $organizationName = '';

    public string $receiptFolioMode = OrganizationSetting::RECEIPT_MODE_ANNUAL;

    public string $receiptFolioPrefix = '';

    public string $receiptFolioPadding = '6';

    public string $whatsAppTemplate = '';

    public string $emailTemplate = '';

    public string $newExpenseCategory = '';

    public ?int $editingExpenseCategoryId = null;

    public string $editingExpenseCategoryName = '';

    public bool $showDeleteConfirm = false;

    public ?int $pendingDeleteCategoryId = null;

    public ?string $pendingDeleteCategoryName = null;

    public function mount(OrganizationSettingsService $settingsService): void
    {
        if (! (auth()->user()?->can('settings.manage') ?? false)) {
            abort(403);
        }

        $settings = $settingsService->current();

        $this->organizationName = (string) (auth()->user()?->organization?->name ?? '');
        $this->receiptFolioMode = (string) $settings['receipt_folio_mode'];
        $this->receiptFolioPrefix = (string) $settings['receipt_folio_prefix'];
        $this->receiptFolioPadding = (string) $settings['receipt_folio_padding'];
        $this->whatsAppTemplate = (string) $settings['whatsapp_template'];
        $this->emailTemplate = (string) $settings['email_template'];
    }

    public function saveSettings(): void
    {
        $this->assertCanManageSettings();

        $organizationId = (int) auth()->user()?->organization_id;

        $validated = $this->validate([
            'organizationName' => [
                'required',
                'string',
                'max:160',
                Rule::unique('organizations', 'name')->ignore($organizationId),
            ],
            'receiptFolioMode' => ['required', Rule::in(OrganizationSetting::RECEIPT_MODES)],
            'receiptFolioPrefix' => ['nullable', 'string', 'max:20'],
            'receiptFolioPadding' => ['required', 'integer', 'min:3', 'max:10'],
            'whatsAppTemplate' => ['required', 'string', 'max:2000'],
            'emailTemplate' => ['required', 'string', 'max:4000'],
        ], [
            'organizationName.required' => __('settings.validation.organization_name_required'),
            'organizationName.max' => __('settings.validation.organization_name_max'),
            'organizationName.unique' => __('settings.validation.organization_name_unique'),
            'receiptFolioMode.required' => __('settings.validation.folio_mode_required'),
            'receiptFolioMode.in' => __('settings.validation.folio_mode_invalid'),
            'receiptFolioPrefix.max' => __('settings.validation.folio_prefix_max'),
            'receiptFolioPadding.required' => __('settings.validation.folio_padding_required'),
            'receiptFolioPadding.integer' => __('settings.validation.folio_padding_integer'),
            'receiptFolioPadding.min' => __('settings.validation.folio_padding_min'),
            'receiptFolioPadding.max' => __('settings.validation.folio_padding_max'),
            'whatsAppTemplate.required' => __('settings.validation.whatsapp_required'),
            'whatsAppTemplate.max' => __('settings.validation.whatsapp_max'),
            'emailTemplate.required' => __('settings.validation.email_required'),
            'emailTemplate.max' => __('settings.validation.email_max'),
        ]);

        Organization::query()
            ->whereKey($organizationId)
            ->update([
                'name' => trim((string) $validated['organizationName']),
            ]);

        OrganizationSetting::query()->updateOrCreate(
            ['organization_id' => (int) auth()->user()?->organization_id],
            [
                'receipt_folio_mode' => $validated['receiptFolioMode'],
                'receipt_folio_prefix' => $this->nullableTrimmed($validated['receiptFolioPrefix'] ?? null),
                'receipt_folio_padding' => (int) $validated['receiptFolioPadding'],
                'penalty_rounding_scale' => OrganizationSettingsService::DEFAULT_PENALTY_ROUNDING_SCALE,
                'penalty_calculation_policy' => OrganizationSettingsService::DEFAULT_PENALTY_CALCULATION_POLICY,
                'whatsapp_template' => trim((string) $validated['whatsAppTemplate']),
                'email_template' => trim((string) $validated['emailTemplate']),
            ]
        );

        app(AuditLogger::class)->log(
            action: 'settings.updated',
            auditable: null,
            summary: __('settings.audit_summary.settings_updated'),
            meta: [
                'organization_name' => trim((string) $validated['organizationName']),
                'receipt_folio_mode' => $validated['receiptFolioMode'],
                'receipt_folio_prefix' => $validated['receiptFolioPrefix'] ?? null,
                'receipt_folio_padding' => $validated['receiptFolioPadding'],
            ],
        );

        session()->flash('success', __('settings.flash.settings_updated'));
    }

    public function createExpenseCategory(): void
    {
        $this->assertCanManageExpenseCategories();

        $organizationId = (int) auth()->user()?->organization_id;

        $validated = $this->validate([
            'newExpenseCategory' => [
                'required',
                'string',
                'max:100',
                Rule::unique('expense_categories', 'name')
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->whereNull('deleted_at')),
            ],
        ], [
            'newExpenseCategory.required' => __('settings.validation.category_required'),
            'newExpenseCategory.max' => __('settings.validation.category_max'),
            'newExpenseCategory.unique' => __('settings.validation.category_unique'),
        ]);

        ExpenseCategory::query()->create([
            'organization_id' => $organizationId,
            'name' => strtoupper(trim($validated['newExpenseCategory'])),
            'is_active' => true,
        ]);

        $this->reset('newExpenseCategory');
        session()->flash('success', __('settings.flash.category_created'));
    }

    public function startEditingExpenseCategory(int $categoryId): void
    {
        $category = ExpenseCategory::query()->findOrFail($categoryId);

        $this->editingExpenseCategoryId = $category->id;
        $this->editingExpenseCategoryName = $category->name;
    }

    public function updateExpenseCategory(): void
    {
        $this->assertCanManageExpenseCategories();

        if (! is_int($this->editingExpenseCategoryId)) {
            return;
        }

        $organizationId = (int) auth()->user()?->organization_id;

        $validated = $this->validate([
            'editingExpenseCategoryName' => [
                'required',
                'string',
                'max:100',
                Rule::unique('expense_categories', 'name')
                    ->ignore($this->editingExpenseCategoryId)
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->whereNull('deleted_at')),
            ],
        ], [
            'editingExpenseCategoryName.required' => __('settings.validation.category_required'),
            'editingExpenseCategoryName.max' => __('settings.validation.category_max'),
            'editingExpenseCategoryName.unique' => __('settings.validation.category_unique'),
        ]);

        ExpenseCategory::query()
            ->whereKey($this->editingExpenseCategoryId)
            ->update([
                'name' => strtoupper(trim($validated['editingExpenseCategoryName'])),
            ]);

        $this->cancelEditingExpenseCategory();
        session()->flash('success', __('settings.flash.category_updated'));
    }

    public function deleteExpenseCategory(int $categoryId): void
    {
        $this->assertCanManageExpenseCategories();

        $category = ExpenseCategory::query()->findOrFail($categoryId);
        $category->delete();

        if ($this->editingExpenseCategoryId === $categoryId) {
            $this->cancelEditingExpenseCategory();
        }

        session()->flash('success', __('settings.flash.category_deleted'));
    }

    public function confirmDeleteExpenseCategory(int $categoryId): void
    {
        $this->assertCanManageExpenseCategories();

        $category = ExpenseCategory::query()->findOrFail($categoryId);
        $this->pendingDeleteCategoryId = $category->id;
        $this->pendingDeleteCategoryName = $category->name;
        $this->showDeleteConfirm = true;
    }

    public function cancelDeleteConfirm(): void
    {
        $this->showDeleteConfirm = false;
        $this->pendingDeleteCategoryId = null;
        $this->pendingDeleteCategoryName = null;
    }

    public function executeDeleteConfirm(): void
    {
        if ($this->pendingDeleteCategoryId === null) {
            return;
        }

        $this->deleteExpenseCategory($this->pendingDeleteCategoryId);
        $this->cancelDeleteConfirm();
    }

    public function cancelEditingExpenseCategory(): void
    {
        $this->reset([
            'editingExpenseCategoryId',
            'editingExpenseCategoryName',
        ]);
    }

    public function render(OrganizationSettingsService $settingsService): View
    {
        $categories = ExpenseCategory::query()
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        return view('livewire.settings.index', [
            'categories' => $categories,
            'canManageSettings' => $this->canManageSettings(),
            'canManageExpenseCategories' => $this->canManageExpenseCategories(),
            'templateVariables' => $settingsService->templateVariables(),
            'penaltyRoundingScale' => OrganizationSettingsService::DEFAULT_PENALTY_ROUNDING_SCALE,
            'penaltyPolicy' => OrganizationSettingsService::DEFAULT_PENALTY_CALCULATION_POLICY,
        ])->layout('layouts.app', [
            'title' => __('settings.title'),
        ]);
    }

    private function assertCanManageSettings(): void
    {
        if (! $this->canManageSettings()) {
            abort(403);
        }
    }

    private function assertCanManageExpenseCategories(): void
    {
        if (! $this->canManageExpenseCategories()) {
            abort(403);
        }
    }

    private function canManageSettings(): bool
    {
        return auth()->user()?->can('settings.manage') ?? false;
    }

    private function canManageExpenseCategories(): bool
    {
        return auth()->user()?->can('expense_categories.manage') ?? false;
    }

    private function nullableTrimmed(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
