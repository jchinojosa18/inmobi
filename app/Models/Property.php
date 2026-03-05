<?php

namespace App\Models;

use App\Domain\Shared\OrganizationScopedModel;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends OrganizationScopedModel
{
    use Auditable, HasFactory, SoftDeletes;

    public const KIND_BUILDING = 'building';

    public const KIND_STANDALONE_HOUSE = 'standalone_house';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'plaza_id',
        'name',
        'code',
        'status',
        'kind',
        'address',
        'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $property): void {
            if ($property->plaza_id !== null) {
                return;
            }

            $organizationId = (int) ($property->organization_id ?? 0);
            if ($organizationId <= 0) {
                return;
            }

            $organization = Organization::query()->find($organizationId);
            if ($organization === null) {
                return;
            }

            $property->plaza_id = $organization->ensureDefaultPlaza(
                auth()->id() !== null ? (int) auth()->id() : null
            )->id;
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Plaza, $this>
     */
    public function plaza(): BelongsTo
    {
        return $this->belongsTo(Plaza::class);
    }

    /**
     * @return HasMany<Unit, $this>
     */
    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    /**
     * @return HasOne<Unit, $this>
     */
    public function standaloneUnit(): HasOne
    {
        return $this->hasOne(Unit::class)->where('kind', Unit::KIND_HOUSE);
    }

    public function isStandaloneHouse(): bool
    {
        return $this->kind === self::KIND_STANDALONE_HOUSE;
    }

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return [
            'plaza_id',
            'name',
            'code',
            'status',
            'kind',
            'address',
        ];
    }
}
