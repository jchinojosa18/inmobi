<?php

namespace App\Models;

use App\Domain\Shared\OrganizationScopedModel;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class UnitInventoryItem extends OrganizationScopedModel
{
    use Auditable, HasFactory, SoftDeletes;

    public const CONDITION_GOOD = 'good';

    public const CONDITION_FAIR = 'fair';

    public const CONDITION_POOR = 'poor';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'unit_id',
        'name',
        'quantity',
        'condition',
        'notes',
        'sort_order',
    ];

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return MorphMany<Document, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * @return list<string>
     */
    public static function conditionOptions(): array
    {
        return [
            self::CONDITION_GOOD,
            self::CONDITION_FAIR,
            self::CONDITION_POOR,
        ];
    }

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return [
            'unit_id',
            'name',
            'quantity',
            'condition',
            'notes',
            'sort_order',
        ];
    }
}
