<?php

namespace App\Livewire\Units;

use App\Models\Property;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class Show extends Component
{
    public Property $property;

    public Unit $unit;

    public function mount(Property $property, Unit $unit): void
    {
        if (! (auth()->user()?->can('units.view') ?? false)) {
            abort(403);
        }

        if ($property->isStandaloneEntity()) {
            $this->redirectRoute('houses.show', ['property' => $property->id], navigate: false);
        }

        if ((int) $unit->property_id !== (int) $property->id) {
            abort(404);
        }

        if ((int) $unit->organization_id !== (int) auth()->user()?->organization_id) {
            abort(403);
        }

        $this->property = $property;
        $this->unit = $unit;
    }

    public function render(): View
    {
        return view('livewire.units.show', [
            'canManageUnits' => auth()->user()?->can('units.manage') ?? false,
        ])->layout('layouts.app', [
            'title' => __('inventory.unit_show_title', [
                'code' => $this->unit->code ?: $this->unit->name,
                'property' => $this->property->name,
            ]),
        ]);
    }
}
