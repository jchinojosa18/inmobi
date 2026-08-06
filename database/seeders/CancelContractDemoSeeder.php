<?php

namespace Database\Seeders;

use App\Actions\Contracts\RegisterDepositHoldAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Document;
use App\Models\Expense;
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

/**
 * Datos locales para probar anulación de contrato (error de captura):
 * - Contrato limpio (anulable): solo renta abierta del mes
 * - Bloqueado por pago
 * - Bloqueado por depósito
 * - Bloqueado por saldo a favor
 * - Ya anulado (filtro Anulados en index)
 *
 * Login: cancel-admin@inmo.test / password
 */
class CancelContractDemoSeeder extends Seeder
{
    public const ORGANIZATION_NAME = 'Demo Anulación';

    public const ADMIN_EMAIL = 'cancel-admin@inmo.test';

    public const CLEAN_LABEL = 'CANCEL_CLEAN';

    public const PAYMENT_LABEL = 'CANCEL_BLOCKED_PAYMENT';

    public const DEPOSIT_LABEL = 'CANCEL_BLOCKED_DEPOSIT';

    public const CREDIT_LABEL = 'CANCEL_BLOCKED_CREDIT';

    public const ALREADY_LABEL = 'CANCEL_ALREADY';

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
                'name' => 'Admin Anulación',
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );

        if (! $admin->hasRole('Admin')) {
            $admin->assignRole('Admin');
        }

        $organization->ensureDefaultPlaza((int) $admin->id);

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
                    'code' => 'CANCEL-BLD',
                ],
                [
                    'name' => 'Edificio Anulación',
                    'status' => 'active',
                    'kind' => Property::KIND_BUILDING,
                    'plaza_id' => $plaza->id,
                    'address' => 'Calle Segunda 45, Ensenada, B.C.',
                    'notes' => 'Seed para probar anulación de contratos',
                ]
            );

        $units = [];
        foreach ([
            'C-101' => 'Depto C-101 (limpio)',
            'C-102' => 'Depto C-102 (con pago)',
            'C-103' => 'Depto C-103 (con depósito)',
            'C-104' => 'Depto C-104 (con crédito)',
            'C-105' => 'Depto C-105 (ya anulado)',
        ] as $code => $name) {
            $units[$code] = Unit::query()
                ->withoutOrganizationScope()
                ->updateOrCreate(
                    [
                        'organization_id' => $organization->id,
                        'property_id' => $property->id,
                        'code' => $code,
                    ],
                    [
                        'name' => $name,
                        'status' => 'active',
                        'kind' => Unit::KIND_APARTMENT,
                        'floor' => '1',
                        'notes' => null,
                    ]
                );
        }

        $tenants = [
            'clean' => $this->upsertTenant(
                $organization->id,
                'inquilino-equivocado@inmo.test',
                'Ana Equivocada Demo',
                'Captura incorrecta — contrato limpio anulable',
            ),
            'payment' => $this->upsertTenant(
                $organization->id,
                'inquilino-con-pago@inmo.test',
                'Luis Con Pago',
                'Anulación bloqueada por pago',
            ),
            'deposit' => $this->upsertTenant(
                $organization->id,
                'inquilino-con-deposito@inmo.test',
                'Sofía Con Depósito',
                'Anulación bloqueada por depósito',
            ),
            'credit' => $this->upsertTenant(
                $organization->id,
                'inquilino-con-credito@inmo.test',
                'Carlos Con Crédito',
                'Anulación bloqueada por saldo a favor',
            ),
            'already' => $this->upsertTenant(
                $organization->id,
                'inquilino-ya-anulado@inmo.test',
                'Elena Ya Anulada',
                'Contrato ya anulado (filtro Anulados)',
            ),
        ];

        foreach ($units as $unit) {
            $this->purgeUnitContracts((int) $organization->id, (int) $unit->id);
        }

        $startsAt = $today->startOfMonth();
        $endsAt = $today->addYear()->subDay();

        $clean = $this->createActiveContract(
            organizationId: (int) $organization->id,
            unitId: (int) $units['C-101']->id,
            tenantId: (int) $tenants['clean']->id,
            rent: 7500,
            deposit: 7500,
            startsAt: $startsAt,
            endsAt: $endsAt,
            demoLabel: self::CLEAN_LABEL,
        );
        // Keep auto-generated open RENT — still "clean" for cancel.

        $withPayment = $this->createActiveContract(
            organizationId: (int) $organization->id,
            unitId: (int) $units['C-102']->id,
            tenantId: (int) $tenants['payment']->id,
            rent: 8000,
            deposit: 8000,
            startsAt: $startsAt,
            endsAt: $endsAt,
            demoLabel: self::PAYMENT_LABEL,
        );
        $rent = Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $withPayment->id)
            ->where('type', Charge::TYPE_RENT)
            ->first();
        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $withPayment->id,
            'amount' => 500,
            'method' => Payment::METHOD_CASH,
            'paid_at' => $today->toDateTimeString(),
            'meta' => ['demo' => 'cancel_seed'],
        ]);
        if ($rent !== null) {
            PaymentAllocation::factory()->create([
                'organization_id' => $organization->id,
                'payment_id' => $payment->id,
                'charge_id' => $rent->id,
                'amount' => 500,
            ]);
        }

        $withDeposit = $this->createActiveContract(
            organizationId: (int) $organization->id,
            unitId: (int) $units['C-103']->id,
            tenantId: (int) $tenants['deposit']->id,
            rent: 9000,
            deposit: 9000,
            startsAt: $startsAt,
            endsAt: $endsAt,
            demoLabel: self::DEPOSIT_LABEL,
        );
        app(RegisterDepositHoldAction::class)->execute(
            $withDeposit,
            9000,
            $startsAt->toDateString(),
            'Depósito seed anulación',
            (int) $admin->id,
            Payment::METHOD_TRANSFER,
        );

        $withCredit = $this->createActiveContract(
            organizationId: (int) $organization->id,
            unitId: (int) $units['C-104']->id,
            tenantId: (int) $tenants['credit']->id,
            rent: 7000,
            deposit: 7000,
            startsAt: $startsAt,
            endsAt: $endsAt,
            demoLabel: self::CREDIT_LABEL,
        );
        CreditBalance::query()->withoutOrganizationScope()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'contract_id' => $withCredit->id,
            ],
            [
                'balance' => 250,
                'last_payment_id' => null,
                'meta' => ['source' => 'cancel_demo_seed'],
            ]
        );

        $already = $this->createActiveContract(
            organizationId: (int) $organization->id,
            unitId: (int) $units['C-105']->id,
            tenantId: (int) $tenants['already']->id,
            rent: 6000,
            deposit: 6000,
            startsAt: $startsAt,
            endsAt: $endsAt,
            demoLabel: self::ALREADY_LABEL,
        );
        Charge::query()
            ->withoutOrganizationScope()
            ->where('contract_id', $already->id)
            ->forceDelete();
        $already->forceFill([
            'status' => Contract::STATUS_CANCELLED,
            'meta' => array_merge($already->meta ?? [], [
                'demo_label' => self::ALREADY_LABEL,
                'cancelled_at' => $today->toIso8601String(),
                'cancellation_reason' => 'Seed: contrato de ejemplo ya anulado',
                'cancelled_by_user_id' => $admin->id,
            ]),
        ])->save();

        $this->command?->info('CancelContractDemoSeeder listo.');
        $this->command?->info('Login: '.self::ADMIN_EMAIL.' / password');
        $this->command?->info('Org: '.self::ORGANIZATION_NAME);
        $this->command?->info('Limpio (anulable): #'.$clean->id.' — '.$tenants['clean']->full_name);
        $this->command?->info('Bloqueado pago: #'.$withPayment->id.' — '.$tenants['payment']->full_name);
        $this->command?->info('Bloqueado depósito: #'.$withDeposit->id.' — '.$tenants['deposit']->full_name);
        $this->command?->info('Bloqueado crédito: #'.$withCredit->id.' — '.$tenants['credit']->full_name);
        $this->command?->info('Ya anulado (filtro): #'.$already->id.' — '.$tenants['already']->full_name);
    }

    private function upsertTenant(int $organizationId, string $email, string $fullName, string $notes): Tenant
    {
        return Tenant::query()
            ->withoutOrganizationScope()
            ->updateOrCreate(
                [
                    'organization_id' => $organizationId,
                    'email' => $email,
                ],
                [
                    'full_name' => $fullName,
                    'phone' => '6460000000',
                    'ine_clave' => strtoupper(substr(md5($email), 0, 18)),
                    'status' => 'active',
                    'notes' => $notes,
                ]
            );
    }

    private function createActiveContract(
        int $organizationId,
        int $unitId,
        int $tenantId,
        float $rent,
        float $deposit,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        string $demoLabel,
    ): Contract {
        return Contract::query()->withoutOrganizationScope()->create([
            'organization_id' => $organizationId,
            'unit_id' => $unitId,
            'tenant_id' => $tenantId,
            'rent_amount' => $rent,
            'deposit_amount' => $deposit,
            'due_day' => min(28, (int) $startsAt->day),
            'grace_days' => 5,
            'penalty_rate_daily' => 0.05,
            'status' => Contract::STATUS_ACTIVE,
            'starts_at' => $startsAt->toDateString(),
            'ends_at' => $endsAt->toDateString(),
            'meta' => [
                'demo_label' => $demoLabel,
            ],
        ]);
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

        $chargeIds = Charge::query()
            ->withoutOrganizationScope()
            ->withTrashed()
            ->whereIn('contract_id', $contractIds)
            ->pluck('id');

        $paymentIds = Payment::query()
            ->withoutOrganizationScope()
            ->withTrashed()
            ->whereIn('contract_id', $contractIds)
            ->pluck('id');

        PaymentAllocation::query()
            ->withoutOrganizationScope()
            ->withTrashed()
            ->where(function ($query) use ($chargeIds, $paymentIds): void {
                if ($chargeIds->isNotEmpty()) {
                    $query->whereIn('charge_id', $chargeIds);
                }
                if ($paymentIds->isNotEmpty()) {
                    $query->orWhereIn('payment_id', $paymentIds);
                }
                if ($chargeIds->isEmpty() && $paymentIds->isEmpty()) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->forceDelete();

        CreditBalance::query()
            ->withoutOrganizationScope()
            ->withTrashed()
            ->whereIn('contract_id', $contractIds)
            ->forceDelete();

        Payment::query()
            ->withoutOrganizationScope()
            ->withTrashed()
            ->whereIn('contract_id', $contractIds)
            ->forceDelete();

        Expense::query()
            ->withoutOrganizationScope()
            ->withTrashed()
            ->whereIn('contract_id', $contractIds)
            ->forceDelete();

        Document::query()
            ->withoutOrganizationScope()
            ->withTrashed()
            ->where('documentable_type', Contract::class)
            ->whereIn('documentable_id', $contractIds)
            ->forceDelete();

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
}
