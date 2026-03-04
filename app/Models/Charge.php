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

    /**
     * @var list<string>
     */
    protected $appends = [
        'status',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $charge): void {
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

    public function getStatusAttribute(): string
    {
        $total = (float) $this->amount;
        $allocated = (float) $this->paymentAllocations()->sum('amount');

        if ($allocated <= 0) {
            return self::STATUS_PENDING;
        }

        if ($allocated >= $total) {
            return self::STATUS_PAID;
        }

        return self::STATUS_PARTIAL;
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
