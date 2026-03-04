<?php

namespace App\Models;

use App\Domain\Shared\OrganizationScopedModel;
use App\Models\Concerns\Auditable;
use App\Support\MonthCloseGuard;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends OrganizationScopedModel
{
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'unit_id',
        'category',
        'amount',
        'spent_at',
        'vendor',
        'notes',
        'meta',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $expense): void {
            MonthCloseGuard::assertExpenseMonthOpen($expense, 'crear');
        });

        static::updating(function (self $expense): void {
            MonthCloseGuard::assertExpenseMonthOpen($expense, 'editar');
        });

        static::deleting(function (self $expense): void {
            MonthCloseGuard::assertExpenseMonthOpen($expense, 'eliminar');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'spent_at' => 'date',
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
            'unit_id',
            'category',
            'amount',
            'spent_at',
            'vendor',
        ];
    }
}
