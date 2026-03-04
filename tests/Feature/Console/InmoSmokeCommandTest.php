<?php

namespace Tests\Feature\Console;

use App\Models\Charge;
use App\Models\Organization;
use App\Models\PaymentAllocation;
use App\Support\OperatingIncomeService;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InmoSmokeCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_smoke_command_is_penalty_idempotent_and_excludes_deposit_from_operating_income(): void
    {
        CarbonImmutable::setTestNow('2026-03-10 10:00:00');

        try {
            $this->seed(DemoDataSeeder::class);

            $target = CarbonImmutable::createFromFormat('Y-m-d', '2026-03-10', 'America/Tijuana')->startOfDay();

            $this->artisan('inmo:smoke', [
                '--date' => $target->toDateString(),
            ])->assertExitCode(0);

            $organization = Organization::query()
                ->where('name', DemoDataSeeder::ORGANIZATION_NAME)
                ->firstOrFail();

            $firstPenaltyCount = Charge::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $organization->id)
                ->where('type', Charge::TYPE_PENALTY)
                ->count();

            $this->assertGreaterThanOrEqual(12, $firstPenaltyCount);
            $this->assertLessThanOrEqual(14, $firstPenaltyCount);

            $this->artisan('inmo:smoke', [
                '--date' => $target->toDateString(),
            ])->assertExitCode(0);

            $secondPenaltyCount = Charge::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $organization->id)
                ->where('type', Charge::TYPE_PENALTY)
                ->count();

            $this->assertSame($firstPenaltyCount, $secondPenaltyCount);

            $duplicatePenaltyRows = Charge::query()
                ->withoutOrganizationScope()
                ->selectRaw('contract_id, penalty_date, COUNT(*) as total')
                ->where('organization_id', $organization->id)
                ->where('type', Charge::TYPE_PENALTY)
                ->whereNotNull('penalty_date')
                ->groupBy('contract_id', 'penalty_date')
                ->havingRaw('COUNT(*) > 1')
                ->get();

            $this->assertCount(0, $duplicatePenaltyRows);

            $dateFrom = $target->subMonthNoOverflow()->startOfMonth()->startOfDay();
            $dateTo = $target->addDay()->endOfDay();

            $operatingIncomeService = app(OperatingIncomeService::class);
            $operatingIncome = $operatingIncomeService->sumForRange(
                organizationId: $organization->id,
                dateFrom: $dateFrom,
                dateTo: $dateTo,
            );

            $depositAllocations = (float) PaymentAllocation::query()
                ->withoutOrganizationScope()
                ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
                ->join('charges', 'charges.id', '=', 'payment_allocations.charge_id')
                ->where('payment_allocations.organization_id', $organization->id)
                ->where('charges.type', Charge::TYPE_DEPOSIT_HOLD)
                ->whereBetween('payments.paid_at', [
                    $dateFrom->toDateTimeString(),
                    $dateTo->toDateTimeString(),
                ])
                ->sum('payment_allocations.amount');

            $this->assertGreaterThan(0, $depositAllocations);

            $allAllocations = (float) PaymentAllocation::query()
                ->withoutOrganizationScope()
                ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
                ->where('payment_allocations.organization_id', $organization->id)
                ->whereBetween('payments.paid_at', [
                    $dateFrom->toDateTimeString(),
                    $dateTo->toDateTimeString(),
                ])
                ->sum('payment_allocations.amount');

            $this->assertGreaterThan($operatingIncome, $allAllocations);

            $includedAllocations = (float) PaymentAllocation::query()
                ->withoutOrganizationScope()
                ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
                ->join('charges', 'charges.id', '=', 'payment_allocations.charge_id')
                ->where('payment_allocations.organization_id', $organization->id)
                ->whereIn('charges.type', $operatingIncomeService->operatingChargeTypes())
                ->whereBetween('payments.paid_at', [
                    $dateFrom->toDateTimeString(),
                    $dateTo->toDateTimeString(),
                ])
                ->sum('payment_allocations.amount');

            $this->assertEqualsWithDelta($includedAllocations, $operatingIncome, 0.01);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
