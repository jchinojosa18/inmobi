<?php

namespace App\Models\Concerns;

use App\Support\AuditContext;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Traits\LogsActivity;

trait Auditable
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        // Chosen over custom logging because it provides Eloquent-ready old/new diffs and causer linkage.
        return LogOptions::defaults()
            ->useLogName(strtolower(class_basename(static::class)))
            ->logOnly($this->auditableAttributes())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return list<string>
     */
    protected function auditableAttributes(): array
    {
        return [];
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        $reason = AuditContext::currentReason()
            ?? request()->input('audit_reason')
            ?? request()->header('X-Audit-Reason');

        $properties = $activity->properties instanceof Collection
            ? $activity->properties
            : collect($activity->properties);

        if (is_string($reason) && trim($reason) !== '') {
            $properties = $properties->put('reason', trim($reason));
        }

        $activity->properties = $properties->put('event', $eventName);
    }
}
