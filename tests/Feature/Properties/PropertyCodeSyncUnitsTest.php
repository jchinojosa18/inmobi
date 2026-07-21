<?php

namespace Tests\Feature\Properties;

use App\Livewire\Properties\Index;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PropertyCodeSyncUnitsTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_property_code_rewrites_prefixed_unit_codes_only(): void
    {
        [$user, $property] = $this->makeBuildingWithUnits('EDIF-A', [
            'EDIF-A-101',
            'PENTHOUSE',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('startEdit', $property->id)
            ->set('code', 'TORRE-B')
            ->call('save')
            ->assertHasNoErrors();

        $property->refresh();
        $this->assertSame('TORRE-B', $property->code);

        $codes = Unit::query()
            ->where('property_id', $property->id)
            ->orderBy('code')
            ->pluck('code')
            ->all();

        $this->assertSame(['PENTHOUSE', 'TORRE-B-101'], $codes);
    }

    public function test_clearing_property_code_is_blocked_when_prefixed_units_exist(): void
    {
        [$user, $property] = $this->makeBuildingWithUnits('EDIF-A', [
            'EDIF-A-101',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('startEdit', $property->id)
            ->set('code', '')
            ->call('save')
            ->assertHasErrors(['code']);

        $property->refresh();
        $this->assertSame('EDIF-A', $property->code);
        $this->assertSame(
            'EDIF-A-101',
            Unit::query()->where('property_id', $property->id)->value('code')
        );
    }

    public function test_updating_other_fields_without_code_change_does_not_touch_units(): void
    {
        [$user, $property] = $this->makeBuildingWithUnits('EDIF-A', [
            'EDIF-A-101',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('startEdit', $property->id)
            ->set('name', 'Edificio Renombrado')
            ->call('save')
            ->assertHasNoErrors();

        $property->refresh();
        $this->assertSame('EDIFICIO RENOMBRADO', $property->name);
        $this->assertSame('EDIF-A', $property->code);
        $this->assertSame(
            'EDIF-A-101',
            Unit::query()->where('property_id', $property->id)->value('code')
        );
    }

    public function test_clearing_property_code_is_allowed_without_prefixed_units(): void
    {
        [$user, $property] = $this->makeBuildingWithUnits('EDIF-A', [
            'PENTHOUSE',
        ]);

        Livewire::actingAs($user)
            ->test(Index::class)
            ->call('startEdit', $property->id)
            ->set('code', '')
            ->call('save')
            ->assertHasNoErrors();

        $property->refresh();
        $this->assertNull($property->code);
        $this->assertSame(
            'PENTHOUSE',
            Unit::query()->where('property_id', $property->id)->value('code')
        );
    }

    /**
     * @param  list<string>  $unitCodes
     * @return array{0: User, 1: Property}
     */
    private function makeBuildingWithUnits(string $propertyCode, array $unitCodes): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);
        $property = Property::factory()->create([
            'organization_id' => $organization->id,
            'code' => $propertyCode,
            'kind' => Property::KIND_BUILDING,
        ]);

        foreach ($unitCodes as $code) {
            Unit::factory()->create([
                'organization_id' => $organization->id,
                'property_id' => $property->id,
                'code' => $code,
                'name' => 'Departamento '.$code,
            ]);
        }

        return [$user, $property];
    }
}
