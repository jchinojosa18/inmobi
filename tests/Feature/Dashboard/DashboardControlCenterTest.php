<?php

namespace Tests\Feature\Dashboard;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControlCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_operational_sections_for_authenticated_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeText('Dashboard operativo');
        $response->assertSeeText('Centro de control operativo');
        $response->assertSeeText('Vencidos (top 10)');
        $response->assertSeeText('En gracia (top 10)');
        $response->assertSeeText('Pagos recientes (top 10)');
    }

    public function test_dashboard_displays_overdue_grace_and_recent_payment_rows(): void
    {
        $user = User::factory()->create();
        $organizationId = (int) $user->organization_id;
        $today = CarbonImmutable::now('America/Tijuana')->startOfDay();

        $property = Property::factory()->create([
            'organization_id' => $organizationId,
            'name' => 'Torre Norte',
        ]);

        $unitOverdue = Unit::factory()->create([
            'organization_id' => $organizationId,
            'property_id' => $property->id,
            'name' => 'Unidad 101',
            'code' => '101',
            'status' => 'active',
        ]);

        $unitGrace = Unit::factory()->create([
            'organization_id' => $organizationId,
            'property_id' => $property->id,
            'name' => 'Unidad 102',
            'code' => '102',
            'status' => 'active',
        ]);

        Unit::factory()->create([
            'organization_id' => $organizationId,
            'property_id' => $property->id,
            'name' => 'Unidad 103',
            'code' => '103',
            'status' => 'active',
        ]);

        $tenantOverdue = Tenant::factory()->create([
            'organization_id' => $organizationId,
            'full_name' => 'Inquilino Vencido',
        ]);

        $tenantGrace = Tenant::factory()->create([
            'organization_id' => $organizationId,
            'full_name' => 'Inquilino Gracia',
        ]);

        $contractOverdue = Contract::withoutEvents(function () use ($organizationId, $unitOverdue, $tenantOverdue, $today): Contract {
            return Contract::query()->create([
                'organization_id' => $organizationId,
                'unit_id' => $unitOverdue->id,
                'tenant_id' => $tenantOverdue->id,
                'rent_amount' => 10000,
                'deposit_amount' => 10000,
                'due_day' => 1,
                'grace_days' => 5,
                'penalty_rate_daily' => 0.05,
                'status' => Contract::STATUS_ACTIVE,
                'active_lock' => 1,
                'starts_at' => $today->subMonths(2)->startOfMonth()->toDateString(),
                'ends_at' => null,
                'meta' => [],
            ]);
        });

        $contractGrace = Contract::withoutEvents(function () use ($organizationId, $unitGrace, $tenantGrace, $today): Contract {
            return Contract::query()->create([
                'organization_id' => $organizationId,
                'unit_id' => $unitGrace->id,
                'tenant_id' => $tenantGrace->id,
                'rent_amount' => 8000,
                'deposit_amount' => 8000,
                'due_day' => 15,
                'grace_days' => 5,
                'penalty_rate_daily' => 0.03,
                'status' => Contract::STATUS_ACTIVE,
                'active_lock' => 1,
                'starts_at' => $today->subMonths(2)->startOfMonth()->toDateString(),
                'ends_at' => null,
                'meta' => [],
            ]);
        });

        $overdueCharge = Charge::query()->create([
            'organization_id' => $organizationId,
            'contract_id' => $contractOverdue->id,
            'unit_id' => $unitOverdue->id,
            'type' => Charge::TYPE_RENT,
            'period' => $today->format('Y-m'),
            'charge_date' => $today->subDays(8)->toDateString(),
            'due_date' => $today->subDays(7)->toDateString(),
            'grace_until' => $today->subDays(3)->toDateString(),
            'amount' => 1000,
            'meta' => [],
        ]);

        Charge::query()->create([
            'organization_id' => $organizationId,
            'contract_id' => $contractGrace->id,
            'unit_id' => $unitGrace->id,
            'type' => Charge::TYPE_RENT,
            'period' => $today->format('Y-m'),
            'charge_date' => $today->subDays(2)->toDateString(),
            'due_date' => $today->subDay()->toDateString(),
            'grace_until' => $today->addDays(2)->toDateString(),
            'amount' => 800,
            'meta' => [],
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $organizationId,
            'contract_id' => $contractOverdue->id,
            'paid_at' => $today->setTime(9, 30)->toDateTimeString(),
            'amount' => 300,
            'method' => Payment::METHOD_TRANSFER,
            'reference' => 'PAY-DASH',
            'receipt_folio' => 'REC-DASH-0001',
            'meta' => [],
        ]);

        PaymentAllocation::query()->create([
            'organization_id' => $organizationId,
            'payment_id' => $payment->id,
            'charge_id' => $overdueCharge->id,
            'amount' => 300,
            'meta' => [],
        ]);

        Expense::query()->create([
            'organization_id' => $organizationId,
            'unit_id' => $unitOverdue->id,
            'category' => 'MANTENIMIENTO',
            'amount' => 120,
            'spent_at' => $today->toDateString(),
            'vendor' => 'Proveedor dashboard',
            'notes' => null,
            'meta' => [],
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSeeText('2 ocupadas / 1 disponibles');
        $response->assertSeeText('Inquilino Vencido');
        $response->assertSeeText('#'.$contractGrace->id);
        $response->assertSeeText('REC-DASH-0001');
        $response->assertSeeHtml('open-quick-payment');
        $response->assertSee(route('payments.receipt.pdf', ['paymentId' => $payment->id]), false);
    }
}
