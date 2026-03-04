<?php

namespace App\Livewire\MonthCloses;

use App\Actions\MonthCloses\CloseMonthAction;
use App\Actions\MonthCloses\ReopenMonthAction;
use App\Models\MonthClose;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Index extends Component
{
    public string $monthToClose = '';

    public ?string $notes = null;

    public function mount(): void
    {
        $this->monthToClose = now('America/Tijuana')->format('Y-m');
    }

    public function closeMonth(CloseMonthAction $action, ?string $month = null): void
    {
        $selectedMonth = $month ?? $this->monthToClose;

        $this->validate([
            'monthToClose' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ], [
            'monthToClose.required' => 'Selecciona un mes para cerrar.',
            'monthToClose.regex' => 'El mes debe usar formato YYYY-MM.',
        ]);

        if ($month !== null && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw ValidationException::withMessages([
                'monthToClose' => 'El mes debe usar formato YYYY-MM.',
            ]);
        }

        $action->execute(
            organizationId: (int) auth()->user()?->organization_id,
            userId: (int) auth()->id(),
            month: (string) $selectedMonth,
            notes: $this->notes,
        );

        $this->notes = null;

        session()->flash('success', "Mes {$selectedMonth} cerrado correctamente.");
    }

    public function reopenMonth(ReopenMonthAction $action, string $month): void
    {
        if (! auth()->user()?->hasRole('Admin')) {
            abort(403);
        }

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) !== 1) {
            throw ValidationException::withMessages([
                'monthToClose' => 'El mes debe usar formato YYYY-MM.',
            ]);
        }

        $reopened = $action->execute(
            organizationId: (int) auth()->user()?->organization_id,
            month: $month,
        );

        session()->flash(
            'success',
            $reopened
                ? "Mes {$month} reabierto correctamente."
                : "El mes {$month} ya estaba abierto."
        );
    }

    public function render(): View
    {
        $organizationId = (int) auth()->user()?->organization_id;

        $closedMonths = MonthClose::query()
            ->where('organization_id', $organizationId)
            ->with('closedBy:id,name')
            ->orderByDesc('month')
            ->get()
            ->keyBy('month');

        $referenceMonths = collect(range(0, 23))
            ->map(fn (int $offset): string => now('America/Tijuana')->startOfMonth()->subMonths($offset)->format('Y-m'))
            ->merge($closedMonths->keys())
            ->unique()
            ->sortDesc()
            ->values();

        $rows = $referenceMonths->map(function (string $month) use ($closedMonths): array {
            /** @var MonthClose|null $closed */
            $closed = $closedMonths->get($month);

            return [
                'month' => $month,
                'is_closed' => $closed !== null,
                'closed_at' => $closed?->closed_at,
                'closed_by' => $closed?->closedBy?->name,
                'snapshot' => $closed?->snapshot,
            ];
        });

        return view('livewire.month-closes.index', [
            'rows' => $rows,
            'isAdmin' => auth()->user()?->hasRole('Admin') ?? false,
        ])->layout('layouts.app', ['title' => 'Cierres mensuales']);
    }
}
