<?php

namespace App\Livewire\Houses;

use App\Models\Property;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public Property $property;

    public Unit $unit;

    public function mount(Property $property): void
    {
        if (! (auth()->user()?->can('properties.view') ?? false)) {
            abort(403);
        }

        $property->loadMissing('units');

        if (! $property->isStandaloneEntity()) {
            abort(404);
        }

        $unit = $property->units->first();

        if (! $unit instanceof Unit) {
            abort(404);
        }

        $this->property = $property;
        $this->unit = $unit;
    }

    public function render(): View
    {
        $entityLabel = $this->property->kindLabel();

        return view('livewire.houses.show', [
            'property' => $this->property,
            'unit' => $this->unit,
            'entityLabel' => $entityLabel,
            'canManageContracts' => auth()->user()?->can('contracts.manage') ?? false,
        ])->layout('layouts.app', [
            'title' => 'Detalle de '.strtolower($entityLabel),
        ]);
    }
}
