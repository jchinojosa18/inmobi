<?php

namespace App\Livewire\Search;

use App\Actions\Charges\GenerateMonthlyRentChargesAction;
use App\Models\Contract;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\MonthCloseGuard;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class CommandPalette extends Component
{
    public bool $open = false;

    public string $q = '';

    /** @var list<array<string, mixed>> */
    public array $results = [];

    /** @var list<array<string, mixed>> */
    public array $actions = [];

    public ?string $confirmingActionId = null;

    public function mount(): void
    {
        $this->actions = $this->buildActions();
    }

    #[On('open-command-palette')]
    public function open(): void
    {
        $this->open = true;
        $this->q = '';
        $this->results = [];
        $this->confirmingActionId = null;
        $this->dispatch('cp-opened');
    }

    #[On('close-command-palette')]
    public function close(): void
    {
        $this->open = false;
        $this->q = '';
        $this->results = [];
        $this->confirmingActionId = null;
    }

    public function handleEscape(): void
    {
        if ($this->confirmingActionId !== null) {
            $this->confirmingActionId = null;

            return;
        }

        $this->close();
    }

    public function updatedQ(): void
    {
        $this->confirmingActionId = null;

        $q = trim($this->q);

        if (mb_strlen($q) < 2) {
            $this->results = [];

            return;
        }

        $this->results = $this->runSearch($q);
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function filteredActions(): array
    {
        $q = Str::of(trim($this->q))->lower()->value();

        $actions = collect($this->actions)
            ->filter(fn (array $action): bool => $this->isActionVisible($action))
            ->sortBy('priority')
            ->values();

        if ($q === '') {
            return $actions
                ->filter(fn (array $action): bool => (bool) ($action['featured'] ?? false))
                ->take(5)
                ->values()
                ->all();
        }

        return $actions
            ->filter(function (array $action) use ($q): bool {
                $keywords = collect($action['keywords'] ?? [])
                    ->map(fn ($keyword): string => Str::of((string) $keyword)->lower()->value())
                    ->join(' ');

                $haystack = Str::of((string) ($action['label'] ?? ''))
                    ->append(' ')
                    ->append($keywords)
                    ->lower()
                    ->value();

                return Str::contains($haystack, $q);
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function navigableItems(): array
    {
        return array_values(array_merge(
            array_map(
                fn (array $action): array => ['kind' => 'action'] + $action,
                $this->filteredActions
            ),
            array_map(
                fn (array $result): array => ['kind' => 'result'] + $result,
                $this->results
            ),
        ));
    }

    #[Computed]
    public function confirmingActionLabel(): ?string
    {
        if ($this->confirmingActionId === null) {
            return null;
        }

        $action = $this->findAction($this->confirmingActionId);

        return $action['label'] ?? null;
    }

    public function executeAction(string $actionId): void
    {
        $action = $this->findAction($actionId);

        if ($action === null) {
            return;
        }

        $requiresConfirmation = (bool) ($action['requires_confirmation'] ?? false);
        if ($requiresConfirmation && $this->confirmingActionId !== $actionId) {
            $this->confirmingActionId = $actionId;

            return;
        }

        $this->confirmingActionId = null;

        $kind = (string) ($action['kind'] ?? '');

        if ($kind === 'modal') {
            $event = (string) data_get($action, 'payload.event', '');
            if ($event !== '') {
                $this->dispatch($event);
            }

            $this->close();
            $this->dispatch('cp-notify', message: (string) ($action['success_message'] ?? 'Acción ejecutada.'));

            return;
        }

        if ($kind === 'route') {
            $href = (string) data_get($action, 'payload.href', '');
            if ($href === '') {
                return;
            }

            $this->close();
            session()->flash('success', (string) ($action['success_message'] ?? 'Navegando...'));
            $this->redirect($href, navigate: true);

            return;
        }

        if ($kind === 'command' && $actionId === 'generate_current_month_rent') {
            $this->runGenerateCurrentMonthRent(app(GenerateMonthlyRentChargesAction::class));
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function runSearch(string $q): array
    {
        $term = '%'.$q.'%';
        $results = [];

        $this->searchContracts($term, $q, $results);
        $this->searchTenants($term, $results);
        $this->searchUnits($term, $results);
        $this->searchProperties($term, $results);

        return $results;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildActions(): array
    {
        return [
            [
                'id' => 'register_payment',
                'label' => 'Registrar pago',
                'keywords' => ['pago', 'abono', 'cobranza', 'recibo'],
                'icon' => 'currency-dollar',
                'kind' => 'modal',
                'payload' => ['event' => 'open-quick-payment'],
                'featured' => true,
                'priority' => 10,
                'requires_confirmation' => false,
                'success_message' => 'Abriendo registro de pago...',
            ],
            [
                'id' => 'register_expense',
                'label' => 'Registrar egreso',
                'keywords' => ['egreso', 'gasto', 'salida'],
                'icon' => 'receipt-percent',
                'kind' => 'modal',
                'payload' => ['event' => 'open-quick-expense'],
                'featured' => true,
                'priority' => 20,
                'requires_confirmation' => false,
                'success_message' => 'Abriendo registro de egreso...',
            ],
            [
                'id' => 'new_contract',
                'label' => 'Nuevo contrato',
                'keywords' => ['contrato', 'alta', 'nuevo'],
                'icon' => 'document-plus',
                'kind' => 'route',
                'payload' => ['href' => route('contracts.create')],
                'featured' => true,
                'priority' => 30,
                'requires_confirmation' => false,
                'success_message' => 'Navegando a Nuevo contrato...',
            ],
            [
                'id' => 'new_house',
                'label' => 'Nueva casa',
                'keywords' => ['casa', 'standalone', 'propiedad'],
                'icon' => 'home',
                'kind' => 'route',
                'payload' => ['href' => route('houses.create')],
                'featured' => true,
                'priority' => 40,
                'requires_confirmation' => false,
                'success_message' => 'Navegando a Nueva casa...',
            ],
            [
                'id' => 'go_cobranza',
                'label' => 'Ir a Cobranza',
                'keywords' => ['cobranza', 'vencidos', 'gracia', 'atraso'],
                'icon' => 'banknotes',
                'kind' => 'route',
                'payload' => ['href' => route('cobranza.index')],
                'featured' => true,
                'priority' => 50,
                'requires_confirmation' => false,
                'success_message' => 'Navegando a Cobranza...',
            ],
            [
                'id' => 'go_contracts',
                'label' => 'Ir a Contratos',
                'keywords' => ['contratos', 'contrato', 'lista'],
                'icon' => 'document-text',
                'kind' => 'route',
                'payload' => ['href' => route('contracts.index')],
                'featured' => false,
                'priority' => 60,
                'requires_confirmation' => false,
                'success_message' => 'Navegando a Contratos...',
            ],
            [
                'id' => 'go_flow_report',
                'label' => 'Reporte de flujo',
                'keywords' => ['reporte', 'flujo', 'ingresos', 'egresos'],
                'icon' => 'chart-bar',
                'kind' => 'route',
                'payload' => ['href' => route('reports.flow')],
                'featured' => false,
                'priority' => 70,
                'requires_confirmation' => false,
                'success_message' => 'Navegando a Reporte de flujo...',
            ],
            [
                'id' => 'generate_current_month_rent',
                'label' => 'Generar rentas del mes',
                'keywords' => ['rentas', 'generar', 'cargo', 'mes'],
                'icon' => 'calendar-days',
                'kind' => 'command',
                'payload' => [],
                'featured' => false,
                'priority' => 80,
                'requires_admin' => true,
                'requires_confirmation' => true,
            ],
        ];
    }

    private function isActionVisible(array $action): bool
    {
        if (! ($action['requires_admin'] ?? false)) {
            return true;
        }

        return (bool) auth()->user()?->hasRole('Admin');
    }

    private function findAction(string $actionId): ?array
    {
        return collect($this->actions)
            ->filter(fn (array $action): bool => $this->isActionVisible($action))
            ->first(fn (array $action): bool => ($action['id'] ?? null) === $actionId);
    }

    private function runGenerateCurrentMonthRent(GenerateMonthlyRentChargesAction $action): void
    {
        $organizationId = (int) auth()->user()?->organization_id;
        if ($organizationId <= 0) {
            return;
        }

        $currentMonth = CarbonImmutable::now('America/Tijuana')->format('Y-m');

        if (MonthCloseGuard::isMonthClosed($organizationId, $currentMonth)) {
            $message = "El mes {$currentMonth} está cerrado. No se pueden generar rentas.";
            session()->flash('error', $message);
            $this->dispatch('cp-notify', message: $message);
            $this->close();

            return;
        }

        $result = $action->executeForOrganization($currentMonth, $organizationId);
        $message = "Rentas generadas: creadas {$result['created']}, omitidas {$result['skipped']}.";
        session()->flash('success', $message);
        $this->dispatch('cp-notify', message: $message);
        $this->close();
    }

    /**
     * @param  list<array<string, mixed>>  $results
     */
    private function searchContracts(string $term, string $rawQ, array &$results): void
    {
        $query = Contract::query()
            ->join('units', 'units.id', '=', 'contracts.unit_id')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->join('tenants', 'tenants.id', '=', 'contracts.tenant_id')
            ->where(function ($q) use ($term, $rawQ): void {
                $q->where('tenants.full_name', 'like', $term)
                    ->orWhere('tenants.email', 'like', $term)
                    ->orWhere('tenants.phone', 'like', $term)
                    ->orWhere('properties.name', 'like', $term)
                    ->orWhere('units.name', 'like', $term)
                    ->orWhere('units.code', 'like', $term);

                if (is_numeric($rawQ)) {
                    $q->orWhere('contracts.id', (int) $rawQ);
                }
            })
            ->select([
                'contracts.id',
                'contracts.status',
                'tenants.full_name as tenant_name',
                'units.name as unit_name',
                'units.code as unit_code',
                'properties.name as property_name',
            ])
            ->orderBy('tenants.full_name')
            ->limit(5)
            ->get();

        foreach ($query as $c) {
            $sublabel = $c->property_name;
            if ($c->unit_name) {
                $sublabel .= ' · '.$c->unit_name;
                if ($c->unit_code) {
                    $sublabel .= " ({$c->unit_code})";
                }
            }

            $results[] = [
                'type' => 'contract',
                'label' => "Contrato #{$c->id} · {$c->tenant_name}",
                'sublabel' => $sublabel,
                'badge' => $c->status === Contract::STATUS_ACTIVE ? 'Activo' : 'Finalizado',
                'href' => route('contracts.show', $c->id),
                'href2' => route('contracts.payments.create', $c->id),
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $results
     */
    private function searchTenants(string $term, array &$results): void
    {
        $tenants = Tenant::query()
            ->where(function ($q) use ($term): void {
                $q->where('full_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            })
            ->select(['id', 'full_name', 'email', 'phone'])
            ->orderBy('full_name')
            ->limit(5)
            ->get();

        foreach ($tenants as $t) {
            $sublabel = implode(' · ', array_filter([$t->email, $t->phone]));

            $results[] = [
                'type' => 'tenant',
                'label' => $t->full_name,
                'sublabel' => $sublabel ?: 'Inquilino',
                'badge' => null,
                'href' => route('tenants.index').'?'.http_build_query(['search' => $t->full_name]),
                'href2' => null,
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $results
     */
    private function searchUnits(string $term, array &$results): void
    {
        $units = Unit::query()
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->where(function ($q) use ($term): void {
                $q->where('units.name', 'like', $term)
                    ->orWhere('units.code', 'like', $term)
                    ->orWhere('properties.name', 'like', $term);
            })
            ->select([
                'units.id',
                'units.name',
                'units.code',
                'units.kind',
                'units.property_id',
                'properties.name as property_name',
            ])
            ->orderBy('properties.name')
            ->orderBy('units.name')
            ->limit(5)
            ->get();

        foreach ($units as $u) {
            $label = $u->name.($u->code ? " ({$u->code})" : '');

            $href = $u->kind === Unit::KIND_HOUSE
                ? route('houses.show', $u->property_id)
                : route('properties.units.index', $u->property_id);

            $results[] = [
                'type' => 'unit',
                'label' => $label,
                'sublabel' => $u->property_name,
                'badge' => null,
                'href' => $href,
                'href2' => null,
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $results
     */
    private function searchProperties(string $term, array &$results): void
    {
        $properties = Property::query()
            ->where(function ($q) use ($term): void {
                $q->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('address', 'like', $term);
            })
            ->select(['id', 'name', 'code', 'address', 'kind'])
            ->orderBy('name')
            ->limit(5)
            ->get();

        foreach ($properties as $p) {
            $label = $p->name.($p->code ? " ({$p->code})" : '');
            $sublabel = $p->address ?? ($p->kind === Property::KIND_BUILDING ? 'Edificio' : 'Casa independiente');

            $href = $p->kind === Property::KIND_STANDALONE_HOUSE
                ? route('houses.show', $p->id)
                : route('properties.index');

            $results[] = [
                'type' => 'property',
                'label' => $label,
                'sublabel' => $sublabel,
                'badge' => null,
                'href' => $href,
                'href2' => null,
            ];
        }
    }

    public function render(): View
    {
        return view('livewire.search.command-palette');
    }
}
