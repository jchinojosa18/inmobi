<?php

namespace Tests\Unit\Support;

use App\Support\DateDisplay;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class DateDisplayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => 'America/Tijuana']);
    }

    public function test_format_date_uses_day_month_year(): void
    {
        $value = CarbonImmutable::parse('2026-07-15 14:30:00', 'America/Tijuana');

        $this->assertSame('15/07/2026', DateDisplay::formatDate($value));
    }

    public function test_format_datetime_includes_hours_and_minutes(): void
    {
        $value = CarbonImmutable::parse('2026-07-15 14:30:45', 'America/Tijuana');

        $this->assertSame('15/07/2026 14:30', DateDisplay::formatDateTime($value));
    }

    public function test_empty_value_returns_placeholder(): void
    {
        $this->assertSame('-', DateDisplay::formatDate(null));
        $this->assertSame('N/A', DateDisplay::formatDateTime('', 'N/A'));
    }
}
