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

class Payment extends OrganizationScopedModel
{
    use Auditable, HasFactory, SoftDeletes;

    public const METHOD_CASH = 'CASH';

    public const METHOD_TRANSFER = 'TRANSFER';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'contract_id',
        'paid_at',
        'amount',
        'method',
        'reference',
        'receipt_folio',
        'meta',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            MonthCloseGuard::assertPaymentMonthOpen($payment, 'crear');
        });

        static::updating(function (self $payment): void {
            MonthCloseGuard::assertPaymentMonthOpen($payment, 'editar');
        });

        static::deleting(function (self $payment): void {
            MonthCloseGuard::assertPaymentMonthOpen($payment, 'eliminar');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
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
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * @return HasMany<CreditBalance, $this>
     */
    public function creditedBalances(): HasMany
    {
        return $this->hasMany(CreditBalance::class, 'last_payment_id');
    }

    /**
     * @return BelongsToMany<Charge, $this>
     */
    public function charges(): BelongsToMany
    {
        return $this->belongsToMany(Charge::class, 'payment_allocations')
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
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return [
            'contract_id',
            'paid_at',
            'amount',
            'method',
            'reference',
            'receipt_folio',
        ];
    }
}
