<?php

namespace App\Models;

use App\Actions\Charges\GenerateMonthlyRentChargesAction;
use App\Domain\Shared\OrganizationScopedModel;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends OrganizationScopedModel
{
    use Auditable, HasFactory, SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const STATUS_CANCELLED = 'cancelled';

    public const EXPIRING_SOON_DAYS = 30;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'unit_id',
        'tenant_id',
        'rent_amount',
        'deposit_amount',
        'due_day',
        'grace_days',
        'penalty_rate_daily',
        'status',
        'active_lock',
        'starts_at',
        'ends_at',
        'meta',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $contract): void {
            $contract->active_lock = $contract->status === self::STATUS_ACTIVE ? 1 : null;
        });

        static::created(function (self $contract): void {
            if ($contract->status !== self::STATUS_ACTIVE) {
                return;
            }

            app(GenerateMonthlyRentChargesAction::class)->ensureCurrentMonthForContract($contract);
        });

        static::updated(function (self $contract): void {
            if (
                $contract->status === self::STATUS_ACTIVE
                && $contract->wasChanged('status')
            ) {
                app(GenerateMonthlyRentChargesAction::class)->ensureCurrentMonthForContract($contract);
            }

            if (
                $contract->status === self::STATUS_ACTIVE
                && $contract->wasChanged(['due_day', 'grace_days'])
            ) {
                app(GenerateMonthlyRentChargesAction::class)->syncOpenRentScheduleForContract($contract);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rent_amount' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'penalty_rate_daily' => 'decimal:4',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'meta' => 'array',
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
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<Charge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(Charge::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasOne<CreditBalance, $this>
     */
    public function creditBalance(): HasOne
    {
        return $this->hasOne(CreditBalance::class);
    }

    /**
     * @return MorphMany<Document, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Payments, deposits and settlement — only while the contract is active.
     */
    public function isOperable(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Adjustments and document uploads — allowed on active/ended, blocked when cancelled.
     */
    public function allowsLedgerMutations(): bool
    {
        return ! $this->isCancelled();
    }

    public function isExpired(?CarbonImmutable $today = null): bool
    {
        $today ??= CarbonImmutable::now('America/Tijuana')->startOfDay();

        return $this->status === self::STATUS_ACTIVE
            && $this->ends_at !== null
            && $this->ends_at->toDateString() < $today->toDateString();
    }

    public function isExpiringSoon(?CarbonImmutable $today = null): bool
    {
        $today ??= CarbonImmutable::now('America/Tijuana')->startOfDay();

        if ($this->status !== self::STATUS_ACTIVE || $this->ends_at === null) {
            return false;
        }

        $endsAt = $this->ends_at->toDateString();
        $todayDate = $today->toDateString();
        $horizon = $today->addDays(self::EXPIRING_SOON_DAYS)->toDateString();

        return $endsAt >= $todayDate && $endsAt <= $horizon;
    }

    public function daysUntilEnd(?CarbonImmutable $today = null): ?int
    {
        if ($this->ends_at === null) {
            return null;
        }

        $today ??= CarbonImmutable::now('America/Tijuana')->startOfDay();

        $endDate = CarbonImmutable::parse($this->ends_at->toDateString(), 'America/Tijuana');

        return (int) $today->diffInDays($endDate, false);
    }

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return [
            'unit_id',
            'tenant_id',
            'rent_amount',
            'deposit_amount',
            'due_day',
            'grace_days',
            'penalty_rate_daily',
            'status',
            'starts_at',
            'ends_at',
        ];
    }
}
