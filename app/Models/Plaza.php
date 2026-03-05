<?php

namespace App\Models;

use App\Domain\Shared\OrganizationScopedModel;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plaza extends OrganizationScopedModel
{
    use Auditable, HasFactory, SoftDeletes;

    public const DEFAULT_NAME = 'Principal';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'nombre',
        'ciudad',
        'timezone',
        'is_default',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
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
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return [
            'nombre',
            'ciudad',
            'timezone',
            'is_default',
            'created_by_user_id',
        ];
    }
}
