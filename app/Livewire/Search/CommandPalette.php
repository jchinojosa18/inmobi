<?php

namespace App\Livewire\Search;

use App\Models\Contract;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class CommandPalette extends Component
{
    public bool $open = false;

    public string $q = '';

    /** @var list<array<string, mixed>> */
    public array $results = [];

    #[On('open-command-palette')]
    public function open(): void
    {
        $this->open = true;
        $this->q = '';
        $this->results = [];
        $this->dispatch('cp-opened');
    }

    #[On('close-command-palette')]
    public function close(): void
    {
        $this->open = false;
        $this->q = '';
        $this->results = [];
    }

    public function updatedQ(): void
    {
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
