<?php

namespace Tests\Unit\Support;

use App\Support\ContractOverdueQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractOverdueQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_sql_returns_non_empty_case_expression(): void
    {
        $sql = (new ContractOverdueQuery)->statusSql('2026-07-15');

        $this->assertNotSame('', trim($sql));
        $this->assertStringContainsString('CASE', $sql);
        $this->assertStringContainsString("'overdue'", $sql);
        $this->assertStringContainsString('2026-07-15', $sql);
    }

    public function test_days_sql_returns_non_empty_case_expression(): void
    {
        $sql = (new ContractOverdueQuery)->daysSql('2026-07-15');

        $this->assertNotSame('', trim($sql));
        $this->assertStringContainsString('CASE', $sql);
        $this->assertStringContainsString('rent_status.grace_until', $sql);
    }

    public function test_days_sql_uses_sqlite_diff_expression_on_current_test_driver(): void
    {
        $sql = (new ContractOverdueQuery)->daysSql('2026-07-15');

        $this->assertStringContainsString('julianday', $sql);
    }

    public function test_pending_amount_expression_is_driver_aware(): void
    {
        $query = new ContractOverdueQuery;

        $this->assertStringContainsString('MAX(', $query->pendingAmountExpression());
        $this->assertStringContainsString('MAX(SUM(', $query->contractPendingAmountExpression());
    }

    public function test_oldest_pending_rent_subquery_can_include_period_column(): void
    {
        $query = new ContractOverdueQuery;

        $withoutPeriod = $query->oldestPendingRentSubquery()->toSql();
        $withPeriod = $query->oldestPendingRentSubquery(true)->toSql();

        $this->assertStringNotContainsString('rent_rows.period', $withoutPeriod);
        $this->assertStringContainsString('rent_rows.period', $withPeriod);
    }

    public function test_balance_and_latest_payment_subqueries_build_valid_sql(): void
    {
        $query = new ContractOverdueQuery;

        $this->assertNotSame('', trim($query->balanceByContractSubquery()->toSql()));
        $this->assertNotSame('', trim($query->latestPaymentByContractSubquery()->toSql()));
    }
}
