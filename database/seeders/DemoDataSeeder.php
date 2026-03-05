<?php

namespace Database\Seeders;

use App\Actions\Charges\GenerateMonthlyRentChargesAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\MonthClose;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Plaza;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class DemoDataSeeder extends Seeder
{
    public const ORGANIZATION_NAME = 'Demo Smoke Organization';

    public const ADMIN_EMAIL = 'admin-smoke@inmo.test';

    public const CONTRACT_A_LABEL = 'SMOKE_CONTRACT_A';

    public const CONTRACT_B_LABEL = 'SMOKE_CONTRACT_B';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->call(SyncRolesAndPermissionsSeeder::class);

        $organization = Organization::query()->firstOrCreate([
            'name' => self::ORGANIZATION_NAME,
        ]);

        $this->resetDemoFinancialData((int) $organization->id);

        $admin = User::query()->updateOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'organization_id' => $organization->id,
                'name' => 'Smoke Admin',
                'password' => 'password',
            ]
        );

        if (! $admin->hasRole('Admin')) {
            $admin->assignRole('Admin');
        }

        $organization->ensureDefaultPlaza((int) $admin->id);

        $tijuanaPlaza = Plaza::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'nombre' => 'Tijuana',
                ],
                [
                    'ciudad' => 'Tijuana',
                    'timezone' => 'America/Tijuana',
                    'is_default' => false,
                    'created_by_user_id' => $admin->id,
                    'deleted_at' => null,
                ]
            );

        $ensenadaPlaza = Plaza::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'nombre' => 'Ensenada',
                ],
                [
                    'ciudad' => 'Ensenada',
                    'timezone' => 'America/Tijuana',
                    'is_default' => false,
                    'created_by_user_id' => $admin->id,
                    'deleted_at' => null,
                ]
            );

        $building = Property::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'code' => 'SMOKE-BLD',
                ],
                [
                    'name' => 'Edificio Smoke',
                    'status' => 'active',
                    'kind' => Property::KIND_BUILDING,
                    'plaza_id' => $tijuanaPlaza->id,
                    'address' => 'Av. Demo 100',
                    'notes' => 'Datos de prueba para smoke test',
                ]
            );

        $unit101 = Unit::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'property_id' => $building->id,
                    'code' => '101',
                ],
                [
                    'name' => 'Unidad 101',
                    'status' => 'active',
                    'kind' => Unit::KIND_APARTMENT,
                    'floor' => '1',
                    'notes' => null,
                ]
            );

        Unit::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'property_id' => $building->id,
                    'code' => '102',
                ],
                [
                    'name' => 'Unidad 102',
                    'status' => 'active',
                    'kind' => Unit::KIND_APARTMENT,
                    'floor' => '1',
                    'notes' => null,
                ]
            );

        $houseProperty = Property::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'code' => 'SMOKE-HOUSE',
                ],
                [
                    'name' => 'Casa Smoke',
                    'status' => 'active',
                    'kind' => Property::KIND_STANDALONE_HOUSE,
                    'plaza_id' => $ensenadaPlaza->id,
                    'address' => 'Calle Casa 12',
                    'notes' => 'Casa standalone demo',
                ]
            );

        $houseUnit = Unit::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'property_id' => $houseProperty->id,
                    'code' => 'CASA',
                ],
                [
                    'name' => 'Casa',
                    'status' => 'active',
                    'kind' => Unit::KIND_HOUSE,
                    'floor' => null,
                    'notes' => null,
                ]
            );

        $tenantA = Tenant::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'email' => 'tenant-a@inmo.test',
                ],
                [
                    'full_name' => 'Inquilino A',
                    'phone' => '6640000001',
                    'status' => 'active',
                    'notes' => null,
                ]
            );

        $tenantB = Tenant::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'email' => 'tenant-b@inmo.test',
                ],
                [
                    'full_name' => 'Inquilino B',
                    'phone' => '6640000002',
                    'status' => 'active',
                    'notes' => null,
                ]
            );

        $startsAt = CarbonImmutable::now('America/Tijuana')
            ->subMonthNoOverflow()
            ->startOfMonth()
            ->toDateString();

        Contract::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'unit_id' => $unit101->id,
                    'tenant_id' => $tenantA->id,
                ],
                [
                    'rent_amount' => 10000,
                    'deposit_amount' => 10000,
                    'due_day' => 1,
                    'grace_days' => 5,
                    'penalty_rate_daily' => 0.05,
                    'status' => Contract::STATUS_ACTIVE,
                    'active_lock' => 1,
                    'starts_at' => $startsAt,
                    'ends_at' => null,
                    'meta' => [
                        'smoke_label' => self::CONTRACT_A_LABEL,
                    ],
                ]
            );

        Contract::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'unit_id' => $houseUnit->id,
                    'tenant_id' => $tenantB->id,
                ],
                [
                    'rent_amount' => 8000,
                    'deposit_amount' => 8000,
                    'due_day' => 15,
                    'grace_days' => 5,
                    'penalty_rate_daily' => 0.03,
                    'status' => Contract::STATUS_ACTIVE,
                    'active_lock' => 1,
                    'starts_at' => $startsAt,
                    'ends_at' => null,
                    'meta' => [
                        'smoke_label' => self::CONTRACT_B_LABEL,
                    ],
                ]
            );

        $rentGenerator = app(GenerateMonthlyRentChargesAction::class);
        $currentMonth = CarbonImmutable::now('America/Tijuana')->startOfMonth();

        $rentGenerator->execute($currentMonth->subMonth()->format('Y-m'));
        $rentGenerator->execute($currentMonth->format('Y-m'));
    }

    private function resetDemoFinancialData(int $organizationId): void
    {
        PaymentAllocation::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->forceDelete();

        Payment::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->forceDelete();

        Charge::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->forceDelete();

        Expense::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->forceDelete();

        MonthClose::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organizationId)
            ->forceDelete();
    }
}
