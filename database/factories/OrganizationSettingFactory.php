<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\OrganizationSetting;
use App\Support\OrganizationSettingsService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationSetting>
 */
class OrganizationSettingFactory extends Factory
{
    protected $model = OrganizationSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'receipt_folio_mode' => OrganizationSetting::RECEIPT_MODE_ANNUAL,
            'receipt_folio_prefix' => 'REC',
            'receipt_folio_padding' => 6,
            'penalty_rounding_scale' => OrganizationSettingsService::DEFAULT_PENALTY_ROUNDING_SCALE,
            'penalty_calculation_policy' => OrganizationSettingsService::DEFAULT_PENALTY_CALCULATION_POLICY,
            'whatsapp_template' => OrganizationSettingsService::DEFAULT_WHATSAPP_TEMPLATE,
            'email_template' => OrganizationSettingsService::DEFAULT_EMAIL_TEMPLATE,
        ];
    }
}
