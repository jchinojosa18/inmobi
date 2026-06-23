<?php

namespace App\Models;

use App\Domain\Shared\OrganizationScopedModel;
use App\Models\Concerns\Auditable;
use App\Support\TextCase;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends OrganizationScopedModel
{
    use Auditable, HasFactory, SoftDeletes;

    public const KIND_APARTMENT = 'apartment';

    public const KIND_HOUSE = 'house';

    public const KIND_LOCAL = 'local';

    public const KIND_LAND = 'land';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'property_id',
        'name',
        'code',
        'status',
        'kind',
        'floor',
        'notes',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return HasMany<Contract, $this>
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * @return HasMany<Charge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * @return MorphMany<Document, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function isHouse(): bool
    {
        return $this->kind === self::KIND_HOUSE;
    }

    protected function code(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null || $value === ''
                ? $value
                : mb_strtoupper($value, 'UTF-8'),
            set: fn (?string $value): ?string => TextCase::upper($value),
        );
    }

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return [
            'property_id',
            'name',
            'code',
            'status',
            'kind',
            'floor',
        ];
    }
}
