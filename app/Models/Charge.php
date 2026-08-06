<?php

namespace App\Models;

use App\Domain\Shared\OrganizationScopedModel;
use App\Models\Concerns\Auditable;
use App\Support\MonthCloseGuard;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Charge extends OrganizationScopedModel
{
    use Auditable, HasFactory, SoftDeletes;

    public const TYPE_RENT = 'RENT';

    public const TYPE_PENALTY = 'PENALTY';

    public const TYPE_SERVICE = 'SERVICE';

    public const TYPE_DAMAGE = 'DAMAGE';

    public const TYPE_CLEANING = 'CLEANING';

    public const TYPE_ADJUSTMENT = 'ADJUSTMENT';

    public const TYPE_OTHER = 'OTHER';

    public const TYPE_DEPOSIT_HOLD = 'DEPOSIT_HOLD';

    public const TYPE_MOVEOUT = 'MOVEOUT';

    public const TYPE_DEPOSIT_APPLY = 'DEPOSIT_APPLY';

    public const TYPE_DEPOSIT_TRANSFER_OUT = 'DEPOSIT_TRANSFER_OUT';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'contract_id',
        'unit_id',
        'type',
        'period',
        'charge_date',
        'due_date',
        'grace_until',
        'penalty_date',
        'amount',
        'meta',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $charge): void {
            $charge->rent_period_key = $charge->type === self::TYPE_RENT ? $charge->period : null;

            if ($charge->type !== self::TYPE_PENALTY) {
                $charge->penalty_date = null;

                return;
            }

            if ($charge->penalty_date === null && $charge->charge_date !== null) {
                $charge->penalty_date = $charge->charge_date;
            }
        });

        static::creating(function (self $charge): void {
            MonthCloseGuard::assertChargeMonthOpen($charge, 'crear');
        });

        static::updating(function (self $charge): void {
            MonthCloseGuard::assertChargeMonthOpen($charge, 'editar');
        });

        static::deleting(function (self $charge): void {
            MonthCloseGuard::assertChargeMonthOpen($charge, 'eliminar');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'charge_date' => 'date',
            'due_date' => 'date',
            'grace_until' => 'date',
            'penalty_date' => 'date',
            'amount' => 'decimal:2',
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
     * @return BelongsTo<Contract, $this>
     */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * @return BelongsToMany<Payment, $this>
     */
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'payment_allocations')
            ->withPivot('amount')
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    /**
     * @return MorphMany<Document, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Payment status derived from allocations. Not appended — use explicitly or
     * prefer withSum('paymentAllocations as allocated_amount') / ledger joins in lists.
     */
    public function getStatusAttribute(): string
    {
        $total = round((float) $this->amount, 2);

        if (
            in_array($this->type, [
                self::TYPE_DEPOSIT_HOLD,
                self::TYPE_DEPOSIT_APPLY,
                self::TYPE_DEPOSIT_TRANSFER_OUT,
            ], true)
        ) {
            return self::STATUS_PAID;
        }

        if (
            $this->type === self::TYPE_ADJUSTMENT
            && $total < 0
            && (bool) data_get($this->meta, 'settled_as_credit')
        ) {
            return self::STATUS_PAID;
        }

        $allocated = $this->resolveAllocatedAmount();

        if ($total <= 0) {
            return $allocated > 0 ? self::STATUS_PAID : self::STATUS_PENDING;
        }

        if ($allocated <= 0) {
            return self::STATUS_PENDING;
        }

        if ($allocated >= $total) {
            return self::STATUS_PAID;
        }

        return self::STATUS_PARTIAL;
    }

    private function resolveAllocatedAmount(): float
    {
        if (array_key_exists('allocated_amount', $this->attributes)) {
            return round((float) $this->attributes['allocated_amount'], 2);
        }

        if ($this->relationLoaded('paymentAllocations')) {
            return round((float) $this->paymentAllocations->sum('amount'), 2);
        }

        return round((float) $this->paymentAllocations()
            ->withoutOrganizationScope()
            ->sum('amount'), 2);
    }

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return [
            'contract_id',
            'unit_id',
            'type',
            'period',
            'charge_date',
            'due_date',
            'grace_until',
            'penalty_date',
            'amount',
            'meta',
        ];
    }
}
