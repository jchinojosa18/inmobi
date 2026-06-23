<?php

namespace Tests\Feature\Units;

use App\Livewire\Units\Index;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnitBulkDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_multiple_selected_units(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-A',
        ]);

        $units = Unit::factory()->count(3)->sequence(
            ['code' => 'EDIF-A-101', 'name' => 'Departamento 101', 'floor' => '1'],
            ['code' => 'EDIF-A-102', 'name' => 'Departamento 102', 'floor' => '1'],
            ['code' => 'EDIF-A-201', 'name' => 'Departamento 201', 'floor' => '2'],
        )->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->set('selectedUnitIds', $units->pluck('id')->map(fn (int $id): string => (string) $id)->all())
            ->call('deleteSelectedUnits')
            ->assertHasNoErrors()
            ->assertSet('selectedUnitIds', []);

        foreach ($units as $unit) {
            $this->assertSoftDeleted('units', ['id' => $unit->id]);
        }
    }

    public function test_it_selects_all_deletable_units_in_property(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-B',
        ]);

        $deletableUnits = Unit::factory()->count(2)->sequence(
            ['code' => 'EDIF-B-101', 'name' => 'Departamento 101'],
            ['code' => 'EDIF-B-102', 'name' => 'Departamento 102'],
        )->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);

        $blockedUnit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'code' => 'EDIF-B-201',
            'name' => 'Departamento 201',
        ]);

        Contract::factory()->ended()->create([
            'organization_id' => $organization->id,
            'unit_id' => $blockedUnit->id,
        ]);

        $component = Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->call('selectAllDeletableInProperty');

        $selectedIds = collect($component->get('selectedUnitIds'))
            ->map(fn (string|int $id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            $deletableUnits->pluck('id')->sort()->values()->all(),
            $selectedIds,
        );
    }

    public function test_it_blocks_bulk_delete_when_any_selected_unit_has_history(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-C',
        ]);

        $emptyUnit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'code' => 'EDIF-C-101',
        ]);

        $blockedUnit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'code' => 'EDIF-C-102',
        ]);

        Contract::factory()->ended()->create([
            'organization_id' => $organization->id,
            'unit_id' => $blockedUnit->id,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->set('selectedUnitIds', [(string) $emptyUnit->id, (string) $blockedUnit->id])
            ->call('deleteSelectedUnits')
            ->assertHasErrors(['delete']);

        $this->assertNotSoftDeleted('units', ['id' => $emptyUnit->id]);
        $this->assertNotSoftDeleted('units', ['id' => $blockedUnit->id]);
    }

    public function test_it_respects_filters_when_selecting_all_deletable(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-D',
        ]);

        $floorOneUnit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'code' => 'EDIF-D-101',
            'floor' => '1',
            'name' => 'Departamento 101',
        ]);

        Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'code' => 'EDIF-D-201',
            'floor' => '2',
            'name' => 'Departamento 201',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->set('search', '101')
            ->call('selectAllDeletableInProperty')
            ->assertSet('selectedUnitIds', [(string) $floorOneUnit->id]);
    }
}
