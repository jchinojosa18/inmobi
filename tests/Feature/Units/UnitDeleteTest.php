<?php

namespace Tests\Feature\Units;

use App\Livewire\Units\Index;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Document;
use App\Models\Expense;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UnitDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_soft_deletes_unit_without_operational_history(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-A',
        ]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'code' => 'EDIF-A-101',
            'name' => 'Departamento 101',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->call('deleteUnit', $unit->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('units', ['id' => $unit->id]);
        $this->assertNull(Unit::withTrashed()->find($unit->id)?->code);
    }

    public function test_it_clears_numbering_scheme_when_last_unit_is_deleted(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-SCHEME',
            'unit_numbering_scheme' => 'floor_based',
        ]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'code' => 'EDIF-SCHEME-101',
            'name' => 'Departamento 101',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->call('deleteUnit', $unit->id)
            ->assertHasNoErrors();

        $this->assertNull($property->fresh()->unit_numbering_scheme);
    }

    public function test_it_allows_recreating_unit_with_same_code_after_delete(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-B',
        ]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'code' => 'EDIF-B-202',
            'name' => 'Departamento 202',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->call('deleteUnit', $unit->id);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->set('bulkNumberingScheme', 'floor_based')
            ->set('floorRows', [['floor' => '2', 'units' => '2']])
            ->call('generateBulkUnits')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('units', [
            'property_id' => $property->id,
            'code' => 'EDIF-B-202',
            'deleted_at' => null,
        ]);
    }

    public function test_it_blocks_delete_when_unit_has_contracts(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-C',
        ]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        Contract::factory()->ended()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->call('deleteUnit', $unit->id)
            ->assertHasErrors(['delete']);

        $this->assertNotSoftDeleted('units', ['id' => $unit->id]);
    }

    public function test_it_blocks_delete_when_unit_has_charges(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-D',
        ]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        Charge::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->call('deleteUnit', $unit->id)
            ->assertHasErrors(['delete']);

        $this->assertNotSoftDeleted('units', ['id' => $unit->id]);
    }

    public function test_it_blocks_delete_when_unit_has_expenses(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-E',
        ]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        Expense::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->call('deleteUnit', $unit->id)
            ->assertHasErrors(['delete']);

        $this->assertNotSoftDeleted('units', ['id' => $unit->id]);
    }

    public function test_it_blocks_delete_when_unit_has_documents(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-F',
        ]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        Document::factory()->create([
            'organization_id' => $organization->id,
            'documentable_type' => Unit::class,
            'documentable_id' => $unit->id,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->call('deleteUnit', $unit->id)
            ->assertHasErrors(['delete']);

        $this->assertNotSoftDeleted('units', ['id' => $unit->id]);
    }

    public function test_it_requires_manage_permission_to_delete(): void
    {
        $organization = Organization::factory()->create();
        $viewerRole = Role::findOrCreate('Lectura', 'web');
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $user->syncRoles([$viewerRole]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => 'EDIF-G',
        ]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);

        Livewire::actingAs($user)
            ->test(Index::class, ['property' => $property])
            ->call('deleteUnit', $unit->id)
            ->assertForbidden();

        $this->assertNotSoftDeleted('units', ['id' => $unit->id]);
    }
}
