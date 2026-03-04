<?php

namespace App\Livewire\Settings;

use App\Models\ExpenseCategory;
use App\Models\OrganizationSetting;
use App\Support\OrganizationSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Index extends Component
{
    public string $receiptFolioMode = OrganizationSetting::RECEIPT_MODE_ANNUAL;

    public string $receiptFolioPrefix = '';

    public string $receiptFolioPadding = '6';

    public string $whatsAppTemplate = '';

    public string $emailTemplate = '';

    public string $newExpenseCategory = '';

    public ?int $editingExpenseCategoryId = null;

    public string $editingExpenseCategoryName = '';

    public function mount(OrganizationSettingsService $settingsService): void
    {
        $settings = $settingsService->current();

        $this->receiptFolioMode = (string) $settings['receipt_folio_mode'];
        $this->receiptFolioPrefix = (string) $settings['receipt_folio_prefix'];
        $this->receiptFolioPadding = (string) $settings['receipt_folio_padding'];
        $this->whatsAppTemplate = (string) $settings['whatsapp_template'];
        $this->emailTemplate = (string) $settings['email_template'];
    }

    public function saveSettings(): void
    {
        $this->assertAdminCanEdit();

        $validated = $this->validate([
            'receiptFolioMode' => ['required', Rule::in(OrganizationSetting::RECEIPT_MODES)],
            'receiptFolioPrefix' => ['nullable', 'string', 'max:20'],
            'receiptFolioPadding' => ['required', 'integer', 'min:3', 'max:10'],
            'whatsAppTemplate' => ['required', 'string', 'max:2000'],
            'emailTemplate' => ['required', 'string', 'max:4000'],
        ], [
            'receiptFolioMode.required' => 'Selecciona un modo de folio.',
            'receiptFolioMode.in' => 'El modo de folio no es válido.',
            'receiptFolioPrefix.max' => 'El prefijo no debe exceder 20 caracteres.',
            'receiptFolioPadding.required' => 'Define el padding del folio.',
            'receiptFolioPadding.integer' => 'El padding debe ser un número entero.',
            'receiptFolioPadding.min' => 'El padding mínimo es 3.',
            'receiptFolioPadding.max' => 'El padding máximo es 10.',
            'whatsAppTemplate.required' => 'La plantilla de WhatsApp es obligatoria.',
            'whatsAppTemplate.max' => 'La plantilla de WhatsApp no debe exceder 2000 caracteres.',
            'emailTemplate.required' => 'La plantilla de email es obligatoria.',
            'emailTemplate.max' => 'La plantilla de email no debe exceder 4000 caracteres.',
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

        session()->flash('success', 'Configuración actualizada correctamente.');
    }

    public function createExpenseCategory(): void
    {
        $this->assertAdminCanEdit();

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
            'newExpenseCategory.required' => 'La categoría es obligatoria.',
            'newExpenseCategory.max' => 'La categoría no debe exceder 100 caracteres.',
            'newExpenseCategory.unique' => 'La categoría ya existe en esta organización.',
        ]);

        ExpenseCategory::query()->create([
            'organization_id' => $organizationId,
            'name' => strtoupper(trim($validated['newExpenseCategory'])),
            'is_active' => true,
        ]);

        $this->reset('newExpenseCategory');
        session()->flash('success', 'Categoría registrada correctamente.');
    }

    public function startEditingExpenseCategory(int $categoryId): void
    {
        $category = ExpenseCategory::query()->findOrFail($categoryId);

        $this->editingExpenseCategoryId = $category->id;
        $this->editingExpenseCategoryName = $category->name;
    }

    public function updateExpenseCategory(): void
    {
        $this->assertAdminCanEdit();

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
            'editingExpenseCategoryName.required' => 'La categoría es obligatoria.',
            'editingExpenseCategoryName.max' => 'La categoría no debe exceder 100 caracteres.',
            'editingExpenseCategoryName.unique' => 'La categoría ya existe en esta organización.',
        ]);

        ExpenseCategory::query()
            ->whereKey($this->editingExpenseCategoryId)
            ->update([
                'name' => strtoupper(trim($validated['editingExpenseCategoryName'])),
            ]);

        $this->cancelEditingExpenseCategory();
        session()->flash('success', 'Categoría actualizada.');
    }

    public function deleteExpenseCategory(int $categoryId): void
    {
        $this->assertAdminCanEdit();

        $category = ExpenseCategory::query()->findOrFail($categoryId);
        $category->delete();

        if ($this->editingExpenseCategoryId === $categoryId) {
            $this->cancelEditingExpenseCategory();
        }

        session()->flash('success', 'Categoría eliminada.');
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
            'isAdmin' => $this->isAdmin(),
            'templateVariables' => $settingsService->templateVariables(),
            'penaltyRoundingScale' => OrganizationSettingsService::DEFAULT_PENALTY_ROUNDING_SCALE,
            'penaltyPolicy' => OrganizationSettingsService::DEFAULT_PENALTY_CALCULATION_POLICY,
        ])->layout('layouts.app', [
            'title' => 'Configuración',
        ]);
    }

    private function assertAdminCanEdit(): void
    {
        if (! $this->isAdmin()) {
            abort(403);
        }
    }

    private function isAdmin(): bool
    {
        return auth()->user()?->hasRole('Admin') ?? false;
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
