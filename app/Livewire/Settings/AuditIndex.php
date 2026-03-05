<?php

namespace App\Livewire\Settings;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithPagination;

class AuditIndex extends Component
{
    use WithPagination;

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $actorUserId = '';

    public string $action = '';

    public string $search = '';

    public string $auditableType = '';

    public ?int $selectedEventId = null;

    public function mount(): void
    {
        $this->assertAdmin();

        $this->dateFrom = now()->subDays(30)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedActorUserId(): void
    {
        $this->resetPage();
    }

    public function updatedAction(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAuditableType(): void
    {
        $this->resetPage();
    }

    public function viewEvent(int $id): void
    {
        $this->selectedEventId = $id;
    }

    public function closeEvent(): void
    {
        $this->selectedEventId = null;
    }

    public function render(): View
    {
        $this->assertAdmin();

        $organizationId = (int) auth()->user()?->organization_id;

        $query = AuditEvent::query()
            ->where('organization_id', $organizationId)
            ->when($this->dateFrom !== '', fn (Builder $q) => $q->whereDate('occurred_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $q) => $q->whereDate('occurred_at', '<=', $this->dateTo))
            ->when($this->actorUserId !== '', fn (Builder $q) => $q->where('actor_user_id', (int) $this->actorUserId))
            ->when($this->action !== '', fn (Builder $q) => $q->where('action', $this->action))
            ->when($this->auditableType !== '', fn (Builder $q) => $q->where('auditable_type', $this->auditableType))
            ->when($this->search !== '', fn (Builder $q) => $q->where('summary', 'like', '%'.$this->search.'%'))
            ->with('actor:id,name,email')
            ->orderByDesc('occurred_at');

        $events = $query->paginate(30);

        $selectedEvent = $this->selectedEventId !== null
            ? AuditEvent::with('actor:id,name,email')->find($this->selectedEventId)
            : null;

        $actors = User::query()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $actions = AuditEvent::query()
            ->where('organization_id', $organizationId)
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        // Returns [ 'App\Models\Contract' => 'Contract', ... ] for select options
        $auditableTypes = AuditEvent::query()
            ->where('organization_id', $organizationId)
            ->whereNotNull('auditable_type')
            ->distinct()
            ->orderBy('auditable_type')
            ->pluck('auditable_type')
            ->mapWithKeys(fn (string $type): array => [$type => class_basename($type)]);

        return view('livewire.settings.audit-index', [
            'events' => $events,
            'selectedEvent' => $selectedEvent,
            'actors' => $actors,
            'actions' => $actions,
            'auditableTypes' => $auditableTypes,
        ])->layout('layouts.app', [
            'title' => 'Auditoría',
        ]);
    }

    private function assertAdmin(): void
    {
        if (! (auth()->user()?->hasRole('Admin') ?? false)) {
            abort(403);
        }
    }
}
