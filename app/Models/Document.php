<?php

namespace App\Models;

use App\Domain\Shared\OrganizationScopedModel;
use App\Models\Concerns\Auditable;
use App\Support\MonthCloseGuard;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends OrganizationScopedModel
{
    use Auditable, HasFactory, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'documentable_id',
        'documentable_type',
        'path',
        'mime',
        'size',
        'type',
        'tags',
        'meta',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $document): void {
            MonthCloseGuard::assertDocumentMonthOpen($document, 'subir');
        });

        static::updating(function (self $document): void {
            MonthCloseGuard::assertDocumentMonthOpen($document, 'editar');
        });

        static::deleting(function (self $document): void {
            MonthCloseGuard::assertDocumentMonthOpen($document, 'eliminar');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'tags' => 'array',
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
     * @return MorphTo<Model, $this>
     */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return [
            'documentable_type',
            'documentable_id',
            'path',
            'mime',
            'size',
            'type',
            'tags',
        ];
    }
}
