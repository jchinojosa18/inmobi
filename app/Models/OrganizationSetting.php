<?php

namespace App\Models;

use App\Domain\Shared\OrganizationScopedModel;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationSetting extends OrganizationScopedModel
{
    use Auditable, HasFactory;

    public const RECEIPT_MODE_ANNUAL = 'annual';

    public const RECEIPT_MODE_CONTINUOUS = 'continuous';

    /**
     * @var list<string>
     */
    public const RECEIPT_MODES = [
        self::RECEIPT_MODE_ANNUAL,
        self::RECEIPT_MODE_CONTINUOUS,
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'receipt_folio_mode',
        'receipt_folio_prefix',
        'receipt_folio_padding',
        'penalty_rounding_scale',
        'penalty_calculation_policy',
        'whatsapp_template',
        'email_template',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'receipt_folio_padding' => 'integer',
            'penalty_rounding_scale' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return [
            'receipt_folio_mode',
            'receipt_folio_prefix',
            'receipt_folio_padding',
            'penalty_rounding_scale',
            'penalty_calculation_policy',
            'whatsapp_template',
            'email_template',
        ];
    }
}
