<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Organization extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'owner_user_id',
    ];

    protected static function booted(): void
    {
        static::created(function (self $organization): void {
            $organization->ensureDefaultPlaza();
        });
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return HasOne<OrganizationSetting, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(OrganizationSetting::class);
    }

    /**
     * @return HasMany<ExpenseCategory, $this>
     */
    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    /**
     * @return HasMany<Plaza, $this>
     */
    public function plazas(): HasMany
    {
        return $this->hasMany(Plaza::class);
    }

    /**
     * @return HasOne<Plaza, $this>
     */
    public function defaultPlaza(): HasOne
    {
        return $this->hasOne(Plaza::class)->where('is_default', true);
    }

    public function ensureDefaultPlaza(?int $createdByUserId = null): Plaza
    {
        $timezone = $this->resolveDefaultPlazaTimezone();

        return DB::transaction(function () use ($timezone, $createdByUserId): Plaza {
            /** @var Collection<int, Plaza> $plazas */
            $plazas = Plaza::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $this->id)
                ->orderBy('id')
                ->get();

            $default = $plazas->first(fn (Plaza $plaza): bool => (bool) $plaza->is_default);
            $default ??= $plazas->first(fn (Plaza $plaza): bool => $plaza->nombre === Plaza::DEFAULT_NAME);
            $default ??= $plazas->first();

            if ($default === null) {
                return Plaza::query()
                    ->withoutOrganizationScope()
                    ->create([
                        'organization_id' => $this->id,
                        'nombre' => Plaza::DEFAULT_NAME,
                        'ciudad' => null,
                        'timezone' => $timezone,
                        'is_default' => true,
                        'created_by_user_id' => $createdByUserId,
                    ]);
            }

            Plaza::query()
                ->withoutOrganizationScope()
                ->where('organization_id', $this->id)
                ->where('id', '!=', $default->id)
                ->where('is_default', true)
                ->update([
                    'is_default' => false,
                    'updated_at' => now(),
                ]);

            $dirty = false;

            if ($default->is_default !== true) {
                $default->is_default = true;
                $dirty = true;
            }

            if (trim((string) $default->nombre) === '') {
                $default->nombre = Plaza::DEFAULT_NAME;
                $dirty = true;
            }

            if (trim((string) $default->timezone) === '') {
                $default->timezone = $timezone;
                $dirty = true;
            }

            if ($default->created_by_user_id === null && $createdByUserId !== null) {
                $default->created_by_user_id = $createdByUserId;
                $dirty = true;
            }

            if ($dirty) {
                $default->save();
            }

            return $default->fresh() ?? $default;
        });
    }

    private function resolveDefaultPlazaTimezone(): string
    {
        $timezone = trim((string) config('app.timezone'));

        return $timezone !== '' ? $timezone : 'America/Tijuana';
    }
}
