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
        $property->loadMissing('units');

        if (! $property->isStandaloneHouse()) {
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
        return view('livewire.houses.show', [
            'property' => $this->property,
            'unit' => $this->unit,
        ])->layout('layouts.app', [
            'title' => 'Detalle de casa',
        ]);
    }
}
