<?php

namespace App\Models;

use App\Domain\Shared\OrganizationScopedModel;
use App\Models\Concerns\Auditable;
use App\Support\TextCase;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    public const KIND_LOCAL = 'local';

    public const KIND_LAND = 'land';

    public const NUMBERING_FLOOR_BASED = 'floor_based';

    public const NUMBERING_SEQUENTIAL = 'sequential';

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
        'unit_numbering_scheme',
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
        return $this->hasOne(Unit::class)->whereIn('kind', [
            Unit::KIND_HOUSE,
            Unit::KIND_LOCAL,
            Unit::KIND_LAND,
        ]);
    }

    public function isStandaloneHouse(): bool
    {
        return $this->kind === self::KIND_STANDALONE_HOUSE;
    }

    public function isStandaloneEntity(): bool
    {
        return in_array($this->kind, [
            self::KIND_STANDALONE_HOUSE,
            self::KIND_LOCAL,
            self::KIND_LAND,
        ], true);
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            self::KIND_STANDALONE_HOUSE => 'Casa',
            self::KIND_BUILDING => 'Edificio',
            self::KIND_LOCAL => 'Local',
            self::KIND_LAND => 'Terreno',
            default => 'Propiedad',
        };
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value === null || $value === ''
                ? $value
                : mb_strtoupper($value, 'UTF-8'),
            set: fn (?string $value): ?string => TextCase::upper($value),
        );
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

    protected function address(): Attribute
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
            'plaza_id',
            'name',
            'code',
            'status',
            'kind',
            'unit_numbering_scheme',
            'address',
        ];
    }
}
