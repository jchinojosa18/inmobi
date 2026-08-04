<?php

namespace Tests\Unit\Support;

use App\Support\OrganizationSettingsService;
use Tests\TestCase;

class OrganizationSettingsServiceTemplateTest extends TestCase
{
    public function test_render_template_formats_amount_due_with_thousands_separator(): void
    {
        $rendered = app(OrganizationSettingsService::class)->renderTemplate(
            'Saldo ${amount_due}',
            ['amount_due' => 12500.5],
        );

        $this->assertSame('Saldo $12,500.50', $rendered);
    }

    public function test_render_template_formats_rent_amount_with_thousands_separator(): void
    {
        $rendered = app(OrganizationSettingsService::class)->renderTemplate(
            'Renta: ${rent_amount}',
            ['rent_amount' => 10000],
        );

        $this->assertSame('Renta: $10,000.00', $rendered);
    }

    public function test_render_template_formats_numeric_string_money_values(): void
    {
        $rendered = app(OrganizationSettingsService::class)->renderTemplate(
            '{amount_due}|{rent_amount}',
            [
                'amount_due' => '9500.00',
                'rent_amount' => '10000.00',
            ],
        );

        $this->assertSame('9,500.00|10,000.00', $rendered);
    }

    public function test_render_template_leaves_already_formatted_money_strings(): void
    {
        $rendered = app(OrganizationSettingsService::class)->renderTemplate(
            '{amount_due}',
            ['amount_due' => '10,000.00'],
        );

        $this->assertSame('10,000.00', $rendered);
    }
}
