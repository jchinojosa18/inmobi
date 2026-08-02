<?php

namespace Database\Seeders;

use App\Actions\Contracts\RegisterDepositHoldAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Models\Payment;
use App\Models\Plaza;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\OrganizationSettingsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

/**
 * Datos locales para probar renovación de contrato:
 * - Settings con ARRENDADOR
 * - Contrato activo con ends_at vencido, depósito registrado, sin saldo pendiente
 * - Contrato activo con saldo pendiente (renovación bloqueada)
 *
 * Login: renew-admin@inmo.test / password
 */
class ContractRenewalDemoSeeder extends Seeder
{
    public const ORGANIZATION_NAME = 'Demo Renovación';

    public const ADMIN_EMAIL = 'renew-admin@inmo.test';

    public const RENEWABLE_LABEL = 'RENEW_READY';

    public const BLOCKED_LABEL = 'RENEW_BLOCKED_BALANCE';

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->call(SyncRolesAndPermissionsSeeder::class);

        $today = CarbonImmutable::now('America/Tijuana')->startOfDay();

        $organization = Organization::query()->firstOrCreate([
            'name' => self::ORGANIZATION_NAME,
        ]);

        $admin = User::query()->updateOrCreate(
            ['email' => self::ADMIN_EMAIL],
            [
                'organization_id' => $organization->id,
                'name' => 'Admin Renovación',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        if (! $admin->hasRole('Admin')) {
            $admin->assignRole('Admin');
        }

        $organization->ensureDefaultPlaza((int) $admin->id);

        OrganizationSetting::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                ['organization_id' => $organization->id],
                [
                    'landlord_name' => 'Inmobiliaria Demo Renovación S.A. de C.V.',
                    'landlord_rep' => 'Juan Carlos Representante',
                    'contract_email_template' => OrganizationSettingsService::DEFAULT_CONTRACT_EMAIL_TEMPLATE,
                    'contract_whatsapp_template' => OrganizationSettingsService::DEFAULT_CONTRACT_WHATSAPP_TEMPLATE,
                ]
            );

        $plaza = Plaza::query()
            ->withoutOrganizationScope()
            ->where('organization_id', $organization->id)
            ->where('is_default', true)
            ->first()
            ?? $organization->ensureDefaultPlaza((int) $admin->id);

        $property = Property::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'code' => 'RENEW-BLD',
                ],
                [
                    'name' => 'Edificio Renovación',
                    'status' => 'active',
                    'kind' => Property::KIND_BUILDING,
                    'plaza_id' => $plaza->id,
                    'address' => 'Calle Primera 123, Ensenada, B.C.',
                    'notes' => 'Seed para probar renovación',
                ]
            );

        $renewableUnit = Unit::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'property_id' => $property->id,
                    'code' => 'R-101',
                ],
                [
                    'name' => 'Depto R-101',
                    'status' => 'active',
                    'kind' => Unit::KIND_APARTMENT,
                    'floor' => '1',
                    'notes' => null,
                ]
            );

        $blockedUnit = Unit::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'property_id' => $property->id,
                    'code' => 'R-102',
                ],
                [
                    'name' => 'Depto R-102',
                    'status' => 'active',
                    'kind' => Unit::KIND_APARTMENT,
                    'floor' => '1',
                    'notes' => null,
                ]
            );

        $renewableTenant = Tenant::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'email' => 'inquilino-renovable@inmo.test',
                ],
                [
                    'full_name' => 'María Renovable Pérez',
                    'phone' => '6461234567',
                    'ine_clave' => 'ABCD123456HDFRRN09',
                    'status' => 'active',
                    'notes' => 'Listo para renovar (sin saldo)',
                ]
            );

        $blockedTenant = Tenant::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'email' => 'inquilino-bloqueado@inmo.test',
                ],
                [
                    'full_name' => 'Pedro Con Adeudo',
                    'phone' => '6467654321',
                    'ine_clave' => 'EFGH987654HDFRRN01',
                    'status' => 'active',
                    'notes' => 'Renovación bloqueada por saldo',
                ]
            );

        $this->purgeUnitContracts((int) $organization->id, (int) $renewableUnit->id);
        $this->purgeUnitContracts((int) $organization->id, (int) $blockedUnit->id);

        $startsAt = $today->subYear()->startOfMonth();
        $endsAt = $today->subDay();

        $renewable = Contract::query()->withoutOrganizationScope()->create([
            'organization_id' => $organization->id,
            'unit_id' => $renewableUnit->id,
            'tenant_id' => $renewableTenant->id,
            'rent_amount' => 9500,
            'deposit_amount' => 9500,
            'due_day' => 1,
            'grace_days' => 5,
            'penalty_rate_daily' => 0.05,
            'status' => Contract::STATUS_ACTIVE,
            'starts_at' => $startsAt->toDateString(),
            'ends_at' => $endsAt->toDateString(),
            'meta' => [
                'demo_label' => self::RENEWABLE_LABEL,
            ],
        ]);

        $this->clearOpenRentCharges($renewable);

        app(RegisterDepositHoldAction::class)->execute(
            $renewable,
            9500,
            $startsAt->toDateString(),
            'Depósito seed renovación',
            (int) $admin->id,
            Payment::METHOD_TRANSFER,
        );

        $blocked = Contract::query()->withoutOrganizationScope()->create([
            'organization_id' => $organization->id,
            'unit_id' => $blockedUnit->id,
            'tenant_id' => $blockedTenant->id,
            'rent_amount' => 8000,
            'deposit_amount' => 8000,
            'due_day' => 1,
            'grace_days' => 5,
            'penalty_rate_daily' => 0.05,
            'status' => Contract::STATUS_ACTIVE,
            'starts_at' => $startsAt->toDateString(),
            'ends_at' => $endsAt->toDateString(),
            'meta' => [
                'demo_label' => self::BLOCKED_LABEL,
            ],
        ]);

        // Keep the create-hook RENT so outstanding > 0 (blocks renew).
        app(RegisterDepositHoldAction::class)->execute(
            $blocked,
            8000,
            $startsAt->toDateString(),
            'Depósito seed bloqueado',
            (int) $admin->id,
            Payment::METHOD_TRANSFER,
        );

        $this->command?->info('ContractRenewalDemoSeeder listo.');
        $this->command?->info('Login: '.self::ADMIN_EMAIL.' / password');
        $this->command?->info('Renovable (Vencido, sin saldo): contrato #'.$renewable->id.' — '.$renewableTenant->full_name);
        $this->command?->info('Bloqueado (con saldo): contrato #'.$blocked->id.' — '.$blockedTenant->full_name);
    }

    private function purgeUnitContracts(int $organizationId, int $unitId): void
    {
        $contractIds = Contract::query()
            ->withoutOrganizationScope()
            ->withTrashed()
            ->where('organization_id', $organizationId)
            ->where('unit_id', $unitId)
            ->pluck('id');

        if ($contractIds->isEmpty()) {
            return;
        }

        Charge::query()
            ->withoutOrganizationScope()
            ->withTrashed()
            ->whereIn('contract_id', $contractIds)
            ->forceDelete();

        Contract::query()
            ->withoutOrganizationScope()
            ->withTrashed()
            ->whereIn('id', $contractIds)
            ->forceDelete();
    }

    private function clearOpenRentCharges(Contract $contract): void
    {
        Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_RENT)
            ->forceDelete();
    }
}
