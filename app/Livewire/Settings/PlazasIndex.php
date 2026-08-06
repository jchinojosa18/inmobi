<?php

namespace App\Livewire\Settings;

use App\Models\Organization;
use App\Models\Plaza;
use App\Models\Property;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PlazasIndex extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $nombre = '';

    public ?string $ciudad = null;

    public string $timezone = 'America/Tijuana';

    public bool $isDefault = false;

    public bool $showDeleteConfirm = false;

    public ?int $pendingDeletePlazaId = null;

    public ?string $pendingDeletePlazaName = null;

    public function mount(): void
    {
        if (! (auth()->user()?->can('plazas.manage') ?? false)) {
            abort(403);
        }

        $this->timezone = (string) config('app.timezone', 'America/Tijuana');
    }

    public function startCreate(): void
    {
        $this->assertAdminCanEdit();
        $this->resetForm();
        $this->showForm = true;
    }

    public function startEdit(int $plazaId): void
    {
        $this->assertAdminCanEdit();

        $plaza = Plaza::query()->findOrFail($plazaId);
        $this->editingId = $plaza->id;
        $this->nombre = $plaza->nombre;
        $this->ciudad = $plaza->ciudad;
        $this->timezone = $plaza->timezone;
        $this->isDefault = (bool) $plaza->is_default;
        $this->showForm = true;
    }

    public function cancelForm(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $this->assertAdminCanEdit();

        $organizationId = (int) auth()->user()?->organization_id;
        $organization = Organization::query()->findOrFail($organizationId);

        $validated = $this->validate($this->rules(), $this->messages());

        $payload = [
            'organization_id' => $organizationId,
            'nombre' => trim((string) $validated['nombre']),
            'ciudad' => $this->nullableTrimmed($validated['ciudad'] ?? null),
            'timezone' => trim((string) $validated['timezone']),
            'is_default' => (bool) $validated['isDefault'],
            'created_by_user_id' => $this->editingId === null ? auth()->id() : null,
        ];

        if ($this->editingId !== null) {
            $plaza = Plaza::query()->findOrFail($this->editingId);

            if ($plaza->is_default && ! $payload['is_default']) {
                $hasOtherDefault = Plaza::query()
                    ->where('id', '!=', $plaza->id)
                    ->where('is_default', true)
                    ->exists();

                if (! $hasOtherDefault) {
                    $this->addError('isDefault', __('settings.validation.default_plaza_required'));

                    return;
                }
            }

            unset($payload['created_by_user_id']);
            $plaza->update($payload);
            $message = __('settings.flash.plaza_updated');
        } else {
            $existingCount = Plaza::query()->count();
            if ($existingCount === 0) {
                $payload['is_default'] = true;
            }

            Plaza::query()->create($payload);
            $message = __('settings.flash.plaza_created');
        }

        $this->normalizeSingleDefault($organization);
        $this->resetForm();
        session()->flash('success', $message);
    }

    public function markAsDefault(int $plazaId): void
    {
        $this->assertAdminCanEdit();

        $plaza = Plaza::query()->findOrFail($plazaId);

        if ($plaza->is_default) {
            return;
        }

        Plaza::query()
            ->where('is_default', true)
            ->update(['is_default' => false]);

        $plaza->is_default = true;
        $plaza->save();

        $organization = Organization::query()->findOrFail((int) auth()->user()?->organization_id);
        $this->normalizeSingleDefault($organization);

        session()->flash('success', __('settings.flash.plaza_marked_default', ['name' => $plaza->nombre]));
    }

    public function delete(int $plazaId): void
    {
        $this->assertAdminCanEdit();

        $organizationId = (int) auth()->user()?->organization_id;
        $organization = Organization::query()->findOrFail($organizationId);

        $plaza = Plaza::query()->findOrFail($plazaId);
        $plazaCount = Plaza::query()->count();

        if ($plaza->is_default && $plazaCount <= 1) {
            $this->addError('delete', __('settings.validation.cannot_delete_only_default'));

            return;
        }

        $propertiesCount = Property::query()
            ->where('plaza_id', $plaza->id)
            ->count();

        if ($propertiesCount > 0) {
            $this->addError('delete', __('settings.validation.cannot_delete_plaza_with_properties', [
                'count' => $propertiesCount,
            ]));

            return;
        }

        $plaza->delete();

        if ($this->editingId === $plazaId) {
            $this->resetForm();
        }

        $this->normalizeSingleDefault($organization);
        session()->flash('success', __('settings.flash.plaza_deleted'));
    }

    public function confirmDelete(int $plazaId): void
    {
        $this->assertAdminCanEdit();

        $plaza = Plaza::query()->findOrFail($plazaId);
        $this->pendingDeletePlazaId = $plaza->id;
        $this->pendingDeletePlazaName = $plaza->nombre;
        $this->showDeleteConfirm = true;
    }

    public function cancelDeleteConfirm(): void
    {
        $this->showDeleteConfirm = false;
        $this->pendingDeletePlazaId = null;
        $this->pendingDeletePlazaName = null;
    }

    public function executeDeleteConfirm(): void
    {
        if ($this->pendingDeletePlazaId === null) {
            return;
        }

        $this->delete($this->pendingDeletePlazaId);
        $this->cancelDeleteConfirm();
    }

    public function render(): View
    {
        $plazas = Plaza::query()
            ->orderByDesc('is_default')
            ->orderBy('nombre')
            ->get();

        return view('livewire.settings.plazas-index', [
            'plazas' => $plazas,
            'canManagePlazas' => $this->canManagePlazas(),
            'singlePlaza' => $plazas->count() === 1,
        ])->layout('layouts.app', [
            'title' => __('settings.plazas_title'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        $organizationId = (int) auth()->user()?->organization_id;

        return [
            'nombre' => [
                'required',
                'string',
                'max:120',
                Rule::unique('plazas', 'nombre')
                    ->ignore($this->editingId)
                    ->where(fn ($query) => $query
                        ->where('organization_id', $organizationId)
                        ->whereNull('deleted_at')
                    ),
            ],
            'ciudad' => ['nullable', 'string', 'max:120'],
            'timezone' => ['required', 'string', 'timezone'],
            'isDefault' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'nombre.required' => __('settings.validation.plaza_name_required'),
            'nombre.max' => __('settings.validation.plaza_name_max'),
            'nombre.unique' => __('settings.validation.plaza_name_unique'),
            'ciudad.max' => __('settings.validation.city_max'),
            'timezone.required' => __('settings.validation.timezone_required'),
            'timezone.timezone' => __('settings.validation.timezone_invalid'),
        ];
    }

    private function normalizeSingleDefault(Organization $organization): void
    {
        $organization->ensureDefaultPlaza(auth()->id() !== null ? (int) auth()->id() : null);
    }

    private function resetForm(): void
    {
        $this->reset([
            'showForm',
            'editingId',
            'nombre',
            'ciudad',
            'isDefault',
        ]);

        $this->timezone = (string) config('app.timezone', 'America/Tijuana');
        $this->resetValidation();
    }

    private function assertAdminCanEdit(): void
    {
        if (! $this->canManagePlazas()) {
            abort(403);
        }
    }

    private function canManagePlazas(): bool
    {
        return auth()->user()?->can('plazas.manage') ?? false;
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
