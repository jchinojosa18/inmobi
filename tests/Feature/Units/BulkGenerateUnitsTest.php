<?php

namespace Tests\Feature\Units;

use App\Livewire\Units\Index;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BulkGenerateUnitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_units_with_floor_numbering_pattern(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-A',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->set('bulkNumberingScheme', 'floor_based')
            ->set('floorRows', [
                ['floor' => '1', 'units' => '4'],
                ['floor' => '2', 'units' => '3'],
            ])
            ->call('generateBulkUnits')
            ->assertHasNoErrors();

        $codes = Unit::query()
            ->where('property_id', $property->id)
            ->orderBy('code')
            ->pluck('code')
            ->all();

        $this->assertSame([
            'EDIF-A-101',
            'EDIF-A-102',
            'EDIF-A-103',
            'EDIF-A-104',
            'EDIF-A-201',
            'EDIF-A-202',
            'EDIF-A-203',
        ], $codes);
    }

    public function test_it_generates_units_with_sequential_numbering_pattern(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-A',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->set('bulkNumberingScheme', 'sequential')
            ->set('floorRows', [
                ['floor' => '1', 'units' => '4'],
                ['floor' => '2', 'units' => '3'],
            ])
            ->call('generateBulkUnits')
            ->assertHasNoErrors();

        $units = Unit::query()
            ->where('property_id', $property->id)
            ->orderBy('code')
            ->get(['code', 'floor']);

        $this->assertSame([
            ['code' => 'EDIF-A-1', 'floor' => '1'],
            ['code' => 'EDIF-A-2', 'floor' => '1'],
            ['code' => 'EDIF-A-3', 'floor' => '1'],
            ['code' => 'EDIF-A-4', 'floor' => '1'],
            ['code' => 'EDIF-A-5', 'floor' => '2'],
            ['code' => 'EDIF-A-6', 'floor' => '2'],
            ['code' => 'EDIF-A-7', 'floor' => '2'],
        ], $units->map(fn (Unit $unit): array => [
            'code' => $unit->code,
            'floor' => $unit->floor,
        ])->all());
    }

    public function test_it_skips_existing_units_when_completing_a_floor(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-C',
        ]);

        foreach (['101', '102', '103'] as $number) {
            Unit::factory()->create([
                'organization_id' => $organization->id,
                'property_id' => $property->id,
                'code' => 'EDIF-C-'.$number,
                'name' => 'Departamento '.$number,
                'floor' => '1',
            ]);
        }

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->set('bulkNumberingScheme', 'floor_based')
            ->set('floorRows', [
                ['floor' => '1', 'units' => '4'],
            ])
            ->call('generateBulkUnits')
            ->assertHasNoErrors();

        $this->assertSame(4, Unit::query()->where('property_id', $property->id)->count());
        $this->assertDatabaseHas('units', [
            'property_id' => $property->id,
            'code' => 'EDIF-C-104',
            'floor' => '1',
        ]);
    }

    public function test_it_skips_existing_units_with_sequential_numbering(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-D',
        ]);

        foreach (['1', '2', '3'] as $number) {
            Unit::factory()->create([
                'organization_id' => $organization->id,
                'property_id' => $property->id,
                'code' => 'EDIF-D-'.$number,
                'name' => 'Departamento '.$number,
                'floor' => '1',
            ]);
        }

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->set('bulkNumberingScheme', 'sequential')
            ->set('floorRows', [
                ['floor' => '1', 'units' => '4'],
            ])
            ->call('generateBulkUnits')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('units', [
            'property_id' => $property->id,
            'code' => 'EDIF-D-4',
            'floor' => '1',
        ]);
    }

    public function test_it_rejects_bulk_when_all_units_already_exist(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-E',
        ]);

        foreach (['101', '102', '103', '104'] as $number) {
            Unit::factory()->create([
                'organization_id' => $organization->id,
                'property_id' => $property->id,
                'code' => 'EDIF-E-'.$number,
                'floor' => '1',
            ]);
        }

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->set('bulkNumberingScheme', 'floor_based')
            ->set('floorRows', [
                ['floor' => '1', 'units' => '4'],
            ])
            ->call('generateBulkUnits')
            ->assertHasErrors(['floorRows']);

        $this->assertSame(4, Unit::query()->where('property_id', $property->id)->count());
    }

    public function test_it_prefills_bulk_form_with_next_floor_and_unit_totals(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-F',
        ]);

        foreach (['101', '102', '103'] as $number) {
            Unit::factory()->create([
                'organization_id' => $organization->id,
                'property_id' => $property->id,
                'code' => 'EDIF-F-'.$number,
                'name' => 'Departamento '.$number,
                'floor' => '1',
            ]);
        }

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->call('startBulkGenerate')
            ->assertSet('floorRows', [
                ['floor' => '2', 'units' => '1'],
            ]);
    }

    public function test_it_prefills_next_floor_row_when_adding_bulk_row(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-G',
        ]);

        Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'code' => 'EDIF-G-101',
            'floor' => '1',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->call('startBulkGenerate')
            ->call('addFloorRow')
            ->assertSet('floorRows', [
                ['floor' => '2', 'units' => '1'],
                ['floor' => '3', 'units' => '1'],
            ]);
    }

    public function test_it_locks_numbering_scheme_on_first_bulk_generate(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-H',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->set('bulkNumberingScheme', 'floor_based')
            ->set('floorRows', [['floor' => '1', 'units' => '2']])
            ->call('generateBulkUnits')
            ->assertHasNoErrors();

        $this->assertSame('floor_based', $property->fresh()->unit_numbering_scheme);
    }

    public function test_it_allows_choosing_numbering_scheme_when_property_has_no_units(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-EMPTY',
            'unit_numbering_scheme' => 'floor_based',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->call('startBulkGenerate')
            ->assertSet('bulkNumberingScheme', 'floor_based')
            ->set('bulkNumberingScheme', 'sequential')
            ->set('floorRows', [['floor' => '1', 'units' => '2']])
            ->call('generateBulkUnits')
            ->assertHasNoErrors();

        $this->assertSame('sequential', $property->fresh()->unit_numbering_scheme);
        $this->assertSame([
            'EDIF-EMPTY-1',
            'EDIF-EMPTY-2',
        ], Unit::query()
            ->where('property_id', $property->id)
            ->orderBy('code')
            ->pluck('code')
            ->all());
    }

    public function test_it_allows_new_numbering_scheme_after_deleting_all_units(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-RESET',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->set('bulkNumberingScheme', 'floor_based')
            ->set('floorRows', [['floor' => '1', 'units' => '1']])
            ->call('generateBulkUnits')
            ->assertHasNoErrors();

        $unit = Unit::query()->where('property_id', $property->id)->sole();

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property->fresh()])
            ->call('deleteUnit', $unit->id)
            ->assertHasNoErrors();

        $this->assertNull($property->fresh()->unit_numbering_scheme);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property->fresh()])
            ->call('startBulkGenerate')
            ->set('bulkNumberingScheme', 'sequential')
            ->set('floorRows', [['floor' => '1', 'units' => '2']])
            ->call('generateBulkUnits')
            ->assertHasNoErrors();

        $this->assertSame('sequential', $property->fresh()->unit_numbering_scheme);
        $this->assertSame([
            'EDIF-RESET-1',
            'EDIF-RESET-2',
        ], Unit::query()
            ->where('property_id', $property->id)
            ->orderBy('code')
            ->pluck('code')
            ->all());
    }

    public function test_it_blocks_bulk_generate_with_different_numbering_scheme(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-I',
            'unit_numbering_scheme' => 'floor_based',
        ]);

        Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'code' => 'EDIF-I-101',
            'floor' => '1',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->set('bulkNumberingScheme', 'sequential')
            ->set('floorRows', [['floor' => '1', 'units' => '2']])
            ->call('generateBulkUnits')
            ->assertHasErrors(['bulkNumberingScheme']);
    }

    public function test_it_converts_all_units_when_changing_building_numbering_scheme(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-J',
            'unit_numbering_scheme' => 'floor_based',
        ]);

        Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'code' => 'EDIF-J-101',
            'name' => 'Departamento 101',
            'floor' => '1',
        ]);
        Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'code' => 'EDIF-J-201',
            'name' => 'Departamento 201',
            'floor' => '2',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->call('startBulkGenerate')
            ->call('startEditingBuildingNumberingScheme')
            ->assertSet('bulkNumberingScheme', 'sequential')
            ->call('applyBuildingNumberingScheme')
            ->assertHasNoErrors()
            ->assertSet('showBulkForm', false)
            ->assertSet('editingBuildingNumberingScheme', false);

        $this->assertSame('sequential', $property->fresh()->unit_numbering_scheme);
        $this->assertSame([
            'EDIF-J-1',
            'EDIF-J-2',
        ], Unit::query()
            ->where('property_id', $property->id)
            ->orderBy('code')
            ->pluck('code')
            ->all());
    }

    public function test_it_opens_bulk_modal_when_bulk_query_param_is_present(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-B',
        ]);

        $this->actingAs($user)
            ->get(route('properties.units.index', ['property' => $property, 'bulk' => 1]))
            ->assertOk()
            ->assertSeeLivewire(Index::class);

        Livewire::actingAs($user)
            ->withQueryParams(['bulk' => '1'])
            ->test(Index::class, ['property' => $property])
            ->assertSet('showBulkForm', true);
    }

    public function test_it_redirects_standalone_local_to_detail_page(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $property = Property::factory()->standaloneLocal()->create([
            'organization_id' => $organization->id,
            'name' => 'Local comercial',
        ]);

        Unit::factory()->local()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);

        $response = $this
            ->actingAs($user)
            ->get(route('properties.units.index', $property));

        $response->assertRedirect(route('houses.show', $property));
    }

    public function test_it_stores_unit_code_in_uppercase_when_bulk_generating(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'edif-low',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->set('floorRows', [['floor' => '1', 'units' => '1']])
            ->call('generateBulkUnits')
            ->assertHasNoErrors();

        $this->assertSame('EDIF-LOW-101', Unit::query()->where('property_id', $property->id)->value('code'));
    }
}
