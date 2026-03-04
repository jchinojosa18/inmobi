<?php

namespace App\Livewire\Houses;

use App\Models\Property;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Create extends Component
{
    public string $name = '';

    public ?string $address = null;

    public ?string $notes = null;

    public function save(): mixed
    {
        $validated = $this->validate($this->rules(), $this->messages());

        DB::transaction(function () use ($validated): void {
            $organizationId = (int) auth()->user()?->organization_id;

            $property = Property::query()->create([
                'organization_id' => $organizationId,
                'name' => $validated['name'],
                'code' => null,
                'status' => 'active',
                'kind' => Property::KIND_STANDALONE_HOUSE,
                'address' => $validated['address'] ?: null,
                'notes' => $validated['notes'] ?: null,
            ]);

            Unit::query()->create([
                'organization_id' => $organizationId,
                'property_id' => $property->id,
                'name' => 'Casa',
                'code' => null,
                'status' => 'active',
                'kind' => Unit::KIND_HOUSE,
                'floor' => null,
                'notes' => null,
            ]);
        });

        session()->flash('success', 'Casa creada correctamente.');

        return redirect()->route('properties.index');
    }

    public function render(): View
    {
        return view('livewire.houses.create')->layout('layouts.app', [
            'title' => 'Nueva casa',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'name.required' => 'El nombre de la casa es obligatorio.',
            'name.max' => 'El nombre no debe exceder 150 caracteres.',
            'address.max' => 'La dirección no debe exceder 255 caracteres.',
            'notes.max' => 'Las notas no deben exceder 1000 caracteres.',
        ];
    }
}
