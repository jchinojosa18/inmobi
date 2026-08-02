# Contract expiring soon — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mostrar contratos activos por vencer (ventana 30 días) y vencidos con badges, columna de fecha, filtros, banner en detalle, dashboard y alerta en el sidebar.

**Architecture:** Estado derivado en `Contract` (`isExpiringSoon`, `daysUntilEnd`) sin migración ni job. Filtros SQL en `Contracts\Index`, queries en dashboard, y `ContractAttentionNav` + view composer para el pill del sidebar.

**Tech Stack:** Laravel 11, Livewire 4, Blade/Tailwind, PHPUnit via Sail, CarbonImmutable, `TenantContext`.

## Global Constraints

- Timezone operativa: `America/Tijuana`.
- Ventana fija: `Contract::EXPIRING_SOON_DAYS = 30`.
- No persistir status `expiring` / `attention` en BD; solo `active` / `ended`.
- Fechas UI: `DateDisplay` / `<x-ui.display-date>` (`d/m/Y`); nunca `format('Y-m-d')` en vistas.
- Scope plaza: `TenantContext::applyCurrentPlazaFilter` / `currentPlazaId` como en listado y dashboard.
- Sail obligatorio: `./vendor/bin/sail test …` y `./vendor/bin/sail pint --dirty`.
- No commitear salvo que el usuario lo pida.
- Spec: `docs/superpowers/specs/2026-08-02-contract-expiring-soon-design.md`.

## File map

| File | Role |
|------|------|
| `app/Models/Contract.php` | `EXPIRING_SOON_DAYS`, `isExpiringSoon()`, `daysUntilEnd()` |
| `app/Support/ContractAttentionNav.php` | Conteo sidebar `{count, has_expired}` |
| `app/Providers/AppServiceProvider.php` | View composer del sidebar |
| `app/Livewire/Contracts/Index.php` | Filtros `expiring`/`attention`, sort `ends_at` |
| `resources/views/livewire/contracts/index.blade.php` | Filtro, badge, columna Vencimiento |
| `resources/views/livewire/contracts/show.blade.php` | Banner + status pill |
| `app/Livewire/Dashboard/Index.php` | Conteo + top-10 por vencer |
| `resources/views/livewire/dashboard/index.blade.php` | Stat-card + tabla |
| `resources/views/layouts/partials/sidebar.blade.php` | Pill rojo/ámbar |
| `lang/es|en/contracts.php`, `dashboard.php` | Strings |
| `tests/Unit/Models/ContractExpiryTest.php` | Unit helpers |
| `tests/Feature/Contracts/ContractExpiringSoonTest.php` | Index + show |
| `tests/Feature/Dashboard/DashboardExpiringContractsTest.php` | Dashboard |
| `tests/Feature/Contracts/ContractAttentionNavTest.php` | Sidebar / Support |

---

### Task 1: Model helpers `isExpiringSoon` / `daysUntilEnd`

**Files:**
- Create: `tests/Unit/Models/ContractExpiryTest.php`
- Modify: `app/Models/Contract.php`

**Interfaces:**
- Consumes: existing `isExpired(?CarbonImmutable $today = null): bool`
- Produces:
  - `Contract::EXPIRING_SOON_DAYS = 30`
  - `Contract::isExpiringSoon(?CarbonImmutable $today = null): bool`
  - `Contract::daysUntilEnd(?CarbonImmutable $today = null): ?int`

- [ ] **Step 1: Write the failing unit tests**

```php
<?php

namespace Tests\Unit\Models;

use App\Models\Contract;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContractExpiryTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function is_expiring_soon_true_when_ends_at_is_today(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        $contract = Contract::factory()->make([
            'status' => Contract::STATUS_ACTIVE,
            'ends_at' => '2026-08-01',
        ]);

        $this->assertTrue($contract->isExpiringSoon());
        $this->assertFalse($contract->isExpired());
        $this->assertSame(0, $contract->daysUntilEnd());
    }

    #[Test]
    public function is_expiring_soon_true_on_day_30(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        $contract = Contract::factory()->make([
            'status' => Contract::STATUS_ACTIVE,
            'ends_at' => '2026-08-31',
        ]);

        $this->assertTrue($contract->isExpiringSoon());
        $this->assertSame(30, $contract->daysUntilEnd());
    }

    #[Test]
    public function is_expiring_soon_false_on_day_31(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        $contract = Contract::factory()->make([
            'status' => Contract::STATUS_ACTIVE,
            'ends_at' => '2026-09-01',
        ]);

        $this->assertFalse($contract->isExpiringSoon());
        $this->assertSame(31, $contract->daysUntilEnd());
    }

    #[Test]
    public function is_expiring_soon_false_when_already_expired(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        $contract = Contract::factory()->make([
            'status' => Contract::STATUS_ACTIVE,
            'ends_at' => '2026-07-31',
        ]);

        $this->assertFalse($contract->isExpiringSoon());
        $this->assertTrue($contract->isExpired());
        $this->assertSame(-1, $contract->daysUntilEnd());
    }

    #[Test]
    public function is_expiring_soon_false_for_ended_or_null_ends_at(): void
    {
        CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

        $ended = Contract::factory()->make([
            'status' => Contract::STATUS_ENDED,
            'ends_at' => '2026-08-15',
        ]);
        $open = Contract::factory()->make([
            'status' => Contract::STATUS_ACTIVE,
            'ends_at' => null,
        ]);

        $this->assertFalse($ended->isExpiringSoon());
        $this->assertFalse($open->isExpiringSoon());
        $this->assertNull($open->daysUntilEnd());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/sail test --filter=ContractExpiryTest`

Expected: FAIL (methods missing).

- [ ] **Step 3: Implement helpers on `Contract`**

Add next to `isExpired()` in `app/Models/Contract.php`:

```php
public const EXPIRING_SOON_DAYS = 30;

public function isExpiringSoon(?CarbonImmutable $today = null): bool
{
    $today ??= CarbonImmutable::now('America/Tijuana')->startOfDay();

    if ($this->status !== self::STATUS_ACTIVE || $this->ends_at === null) {
        return false;
    }

    $endsAt = $this->ends_at->toDateString();
    $todayDate = $today->toDateString();
    $horizon = $today->addDays(self::EXPIRING_SOON_DAYS)->toDateString();

    return $endsAt >= $todayDate && $endsAt <= $horizon;
}

public function daysUntilEnd(?CarbonImmutable $today = null): ?int
{
    if ($this->ends_at === null) {
        return null;
    }

    $today ??= CarbonImmutable::now('America/Tijuana')->startOfDay();

    return (int) $today->diffInDays($this->ends_at->startOfDay(), false);
}
```

Notes:
- Use `$today->addDays(...)` which returns a new immutable instance (do not mutate `$today` if reused).
- `diffInDays(..., false)` signed: positive = future, negative = past. Confirm CarbonImmutable signature in this project; if absolute-only, compute via `$this->ends_at->startOfDay()->diffInDays($today, false)` equivalent.

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/sail test --filter=ContractExpiryTest`

Expected: PASS.

- [ ] **Step 5: Pint dirty files**

Run: `./vendor/bin/sail pint --dirty`

---

### Task 2: Index filters, column, badges + i18n

**Files:**
- Modify: `lang/es/contracts.php`, `lang/en/contracts.php`
- Modify: `app/Livewire/Contracts/Index.php` (`applyFilters`, `applySorting`)
- Modify: `resources/views/livewire/contracts/index.blade.php`
- Create: `tests/Feature/Contracts/ContractExpiringSoonTest.php`

**Interfaces:**
- Consumes: `Contract::isExpiringSoon()`, `Contract::daysUntilEnd()`, `Contract::EXPIRING_SOON_DAYS`, `isExpired()`
- Produces: status filters `expiring` / `attention`; sort key `ends_at`; UI strings

- [ ] **Step 1: Add i18n keys**

In `lang/es/contracts.php` (near existing status keys):

```php
'status_expiring' => 'Por vencer',
'status_expiring_label' => 'Por vencer',
'status_attention' => 'Requieren atención',
'expiring_banner' => 'Este contrato está por vencer. La vigencia termina el :date.',
'expiration' => 'Vencimiento',
'ends_in_days' => 'Faltan :days días',
'ends_today' => 'Vence hoy',
'ended_days_ago' => 'Vencido hace :days días',
```

In `lang/en/contracts.php`:

```php
'status_expiring' => 'Expiring soon',
'status_expiring_label' => 'Expiring soon',
'status_attention' => 'Needs attention',
'expiring_banner' => 'This contract is expiring soon. Coverage ends on :date.',
'expiration' => 'Expiration',
'ends_in_days' => ':days days left',
'ends_today' => 'Ends today',
'ended_days_ago' => 'Expired :days days ago',
```

- [ ] **Step 2: Write failing feature tests**

Create `tests/Feature/Contracts/ContractExpiringSoonTest.php` reusing the helper pattern from `ContractExpiredBadgeTest` (`createOrganizationWithUser`, `createContractForOrganization` with `endsAt` / `status`). Include `TenantContext` set/clear + `CarbonImmutable::setTestNow`.

```php
public function test_index_shows_expiring_badge_and_expiration_column(): void
{
    CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');
    [$user] = $this->createOrganizationWithUser();

    $this->createContractForOrganization(
        $user->organization_id,
        tenantName: 'Inquilino Por Vencer',
        endsAt: '2026-08-15',
    );

    $response = $this->actingAs($user)->get(route('contracts.index', ['status' => 'all']));

    $response->assertOk();
    $response->assertSeeText('Inquilino Por Vencer');
    $response->assertSeeText(__('contracts.status_expiring_label'));
    $response->assertSeeText(DateDisplay::formatDate('2026-08-15'));
    $response->assertSeeText(__('contracts.ends_in_days', ['days' => 14]));
}

public function test_expiring_filter_shows_only_window(): void
{
    CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');
    [$user] = $this->createOrganizationWithUser();

    $this->createContractForOrganization($user->organization_id, 'Solo Por Vencer', '2026-08-20');
    $this->createContractForOrganization($user->organization_id, 'Ya Vencido', '2026-07-20');
    $this->createContractForOrganization($user->organization_id, 'Lejos', '2026-12-01');

    $response = $this->actingAs($user)->get(route('contracts.index', ['status' => 'expiring']));

    $response->assertOk();
    $response->assertSeeText('Solo Por Vencer');
    $response->assertDontSeeText('Ya Vencido');
    $response->assertDontSeeText('Lejos');
}

public function test_attention_filter_includes_expired_and_expiring(): void
{
    CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');
    [$user] = $this->createOrganizationWithUser();

    $this->createContractForOrganization($user->organization_id, 'Atencion Vencido', '2026-07-20');
    $this->createContractForOrganization($user->organization_id, 'Atencion Por Vencer', '2026-08-20');
    $this->createContractForOrganization($user->organization_id, 'Fuera Ventana', '2026-12-01');

    $response = $this->actingAs($user)->get(route('contracts.index', ['status' => 'attention']));

    $response->assertOk();
    $response->assertSeeText('Atencion Vencido');
    $response->assertSeeText('Atencion Por Vencer');
    $response->assertDontSeeText('Fuera Ventana');
}

public function test_show_displays_expiring_banner(): void
{
    CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');
    [$user] = $this->createOrganizationWithUser();

    $contract = $this->createContractForOrganization(
        $user->organization_id,
        'Banner Por Vencer',
        '2026-08-20',
    );

    $response = $this->actingAs($user)->get(route('contracts.show', $contract));

    $response->assertOk();
    $response->assertSeeText(__('contracts.expiring_banner', [
        'date' => DateDisplay::formatDate('2026-08-20'),
    ]));
    $response->assertSeeText(__('contracts.status_expiring_label'));
}
```

(Copy private helpers from `ContractExpiredBadgeTest`; keep `tearDown` clearing test now + TenantContext.)

- [ ] **Step 3: Run tests to verify they fail**

Run: `./vendor/bin/sail test --filter=ContractExpiringSoonTest`

Expected: FAIL (filter/UI missing).

- [ ] **Step 4: Update `applyFilters` in `Index.php`**

Replace the status filter block so derived statuses are handled before the generic `where status`:

```php
if ($this->status_filter === 'expired') {
    $today = now('America/Tijuana')->toDateString();
    $query
        ->where('contracts.status', Contract::STATUS_ACTIVE)
        ->whereNotNull('contracts.ends_at')
        ->whereDate('contracts.ends_at', '<', $today);
} elseif ($this->status_filter === 'expiring') {
    $today = CarbonImmutable::now('America/Tijuana')->startOfDay();
    $horizon = $today->addDays(Contract::EXPIRING_SOON_DAYS)->toDateString();
    $query
        ->where('contracts.status', Contract::STATUS_ACTIVE)
        ->whereNotNull('contracts.ends_at')
        ->whereDate('contracts.ends_at', '>=', $today->toDateString())
        ->whereDate('contracts.ends_at', '<=', $horizon);
} elseif ($this->status_filter === 'attention') {
    $today = CarbonImmutable::now('America/Tijuana')->startOfDay();
    $horizon = $today->addDays(Contract::EXPIRING_SOON_DAYS)->toDateString();
    $query
        ->where('contracts.status', Contract::STATUS_ACTIVE)
        ->whereNotNull('contracts.ends_at')
        ->whereDate('contracts.ends_at', '<=', $horizon);
} elseif ($this->status_filter !== 'all') {
    $query->where('contracts.status', $this->status_filter);
}
```

Add `use Carbon\CarbonImmutable;` if missing.

- [ ] **Step 5: Add sort case `ends_at`**

In `applySorting` switch:

```php
case 'ends_at':
    $query
        ->orderByRaw("COALESCE(contracts.ends_at, '9999-12-31') {$direction}")
        ->orderBy('contracts.id', 'desc');
    break;
```

- [ ] **Step 6: Update index Blade**

1. Status select options (order):

```blade
<option value="active">{{ __('contracts.status_active') }}</option>
<option value="expiring">{{ __('contracts.status_expiring') }}</option>
<option value="expired">{{ __('contracts.status_expired') }}</option>
<option value="attention">{{ __('contracts.status_attention') }}</option>
<option value="ended">{{ __('contracts.status_ended') }}</option>
<option value="all">{{ __('contracts.all_masculine') }}</option>
```

2. Header column between Propiedad/Unidad and Próximo vencimiento:

```blade
<th class="px-4 py-3">
    <button type="button" wire:click="sortBy('ends_at')" class="inline-flex items-center gap-1 hover:text-slate-800">
        {{ __('contracts.expiration') }} <span>{{ $sortIndicator('ends_at') }}</span>
    </button>
</th>
```

3. Row badge priority:

```blade
@if ($contract->isExpired())
    <x-ui.badge variant="danger" class="mt-1">
        {{ __('contracts.status_expired_label') }}
    </x-ui.badge>
@elseif ($contract->isExpiringSoon())
    <x-ui.badge variant="warning" class="mt-1">
        {{ __('contracts.status_expiring_label') }}
    </x-ui.badge>
@else
    <x-ui.badge :variant="$contract->status === 'active' ? 'success' : 'neutral'" class="mt-1">
        {{ $contract->status === 'active' ? __('common.active') : __('common.finished') }}
    </x-ui.badge>
@endif
```

4. Body cell for expiration (after property/unit `<td>`):

```blade
<td class="px-4 py-3 align-top">
    @if ($contract->ends_at)
        @php $daysUntilEnd = $contract->daysUntilEnd(); @endphp
        <p class="font-medium text-slate-900">
            <x-ui.display-date :value="$contract->ends_at" />
        </p>
        <p class="text-xs text-slate-500">
            @if ($daysUntilEnd === 0)
                {{ __('contracts.ends_today') }}
            @elseif ($daysUntilEnd > 0)
                {{ __('contracts.ends_in_days', ['days' => $daysUntilEnd]) }}
            @else
                {{ __('contracts.ended_days_ago', ['days' => abs($daysUntilEnd)]) }}
            @endif
        </p>
    @else
        <p class="text-slate-500">—</p>
    @endif
</td>
```

5. Update empty-state `:colspan="8"`.

- [ ] **Step 7: Update show Blade (banner + status)**

```blade
@if ($contract->isExpired())
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">
        {{ __('contracts.expired_banner', ['date' => \App\Support\DateDisplay::formatDate($contract->ends_at)]) }}
    </div>
@elseif ($contract->isExpiringSoon())
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">
        {{ __('contracts.expiring_banner', ['date' => \App\Support\DateDisplay::formatDate($contract->ends_at)]) }}
    </div>
@endif
```

Status stat-card value:

```blade
:value="$contract->isExpired()
    ? __('contracts.status_expired_label')
    : ($contract->isExpiringSoon()
        ? __('contracts.status_expiring_label')
        : ($contract->status === 'active' ? __('common.active') : __('common.finished')))"
```

- [ ] **Step 8: Run feature tests**

Run: `./vendor/bin/sail test --filter=ContractExpiringSoonTest`

Expected: PASS. Also run `./vendor/bin/sail test --filter=ContractExpiredBadgeTest` to ensure expired still works (badge now `danger` — assert text labels, not CSS).

- [ ] **Step 9: Pint**

Run: `./vendor/bin/sail pint --dirty`

---

### Task 3: Dashboard stat-card + top-10

**Files:**
- Modify: `lang/es/dashboard.php`, `lang/en/dashboard.php`
- Modify: `app/Livewire/Dashboard/Index.php`
- Modify: `resources/views/livewire/dashboard/index.blade.php`
- Create: `tests/Feature/Dashboard/DashboardExpiringContractsTest.php`

**Interfaces:**
- Consumes: `Contract::STATUS_ACTIVE`, `Contract::EXPIRING_SOON_DAYS`, `TenantContext::currentPlazaId()`
- Produces: view props `expiringSoonCount: int`, `expiringSoonContracts: Collection` (max 10)

- [ ] **Step 1: Add dashboard i18n**

ES:

```php
'expiring_soon_contracts' => 'Contratos por vencer',
'expiring_soon_top10' => 'Por vencer (top 10)',
'days_remaining' => 'Días restantes',
'no_expiring_soon' => 'Sin contratos por vencer.',
```

EN:

```php
'expiring_soon_contracts' => 'Expiring soon',
'expiring_soon_top10' => 'Expiring soon (top 10)',
'days_remaining' => 'Days left',
'no_expiring_soon' => 'No contracts expiring soon.',
```

- [ ] **Step 2: Write failing dashboard test**

Create `tests/Feature/Dashboard/DashboardExpiringContractsTest.php` with RefreshDatabase, tearDown clearing Carbon + TenantContext, and a private `createContract(...)` helper (same shape as `ContractExpiredBadgeTest`).

```php
public function test_dashboard_shows_expiring_soon_stat_and_table(): void
{
    CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');

    $organization = Organization::factory()->create();
    TenantContext::setOrganizationId($organization->id);
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $this->createContract($organization->id, 'Dash Por Vencer', '2026-08-15');
    $this->createContract($organization->id, 'Dash Ya Vencido', '2026-07-01');
    $this->createContract($organization->id, 'Dash Lejos', '2026-12-01');

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk();
    $response->assertSeeText(__('dashboard.expiring_soon_contracts'));
    $response->assertSeeText(__('dashboard.expiring_soon_top10'));
    $response->assertSeeText('Dash Por Vencer');
    $response->assertDontSeeText('Dash Ya Vencido');
    $response->assertDontSeeText('Dash Lejos');
    $response->assertSee(route('contracts.index', ['status' => 'expiring'], false));
}
```

- [ ] **Step 3: Run to verify fail**

Run: `./vendor/bin/sail test --filter=DashboardExpiringContractsTest`

Expected: FAIL.

- [ ] **Step 4: Add query helpers + props in `Dashboard\Index`**

Private methods (plaza-aware joins like `$activeContracts`):

```php
/**
 * @return array{0: int, 1: \Illuminate\Support\Collection<int, object>}
 */
private function expiringSoonSummary(?int $currentPlazaId): array
{
    $today = CarbonImmutable::now('America/Tijuana')->startOfDay();
    $horizon = $today->addDays(Contract::EXPIRING_SOON_DAYS)->toDateString();
    $todayDate = $today->toDateString();

    $base = Contract::query()
        ->join('units', 'units.id', '=', 'contracts.unit_id')
        ->join('properties', 'properties.id', '=', 'units.property_id')
        ->join('tenants', 'tenants.id', '=', 'contracts.tenant_id')
        ->where('contracts.status', Contract::STATUS_ACTIVE)
        ->whereNotNull('contracts.ends_at')
        ->whereDate('contracts.ends_at', '>=', $todayDate)
        ->whereDate('contracts.ends_at', '<=', $horizon)
        ->when($currentPlazaId !== null, fn (Builder $q) => $q->where('properties.plaza_id', $currentPlazaId));

    $count = (clone $base)->count('contracts.id');

    $rows = (clone $base)
        ->select([
            'contracts.id as contract_id',
            'contracts.ends_at',
            'tenants.full_name as tenant_name',
            'tenants.email as tenant_email',
            'tenants.phone as tenant_phone',
            'properties.name as property_name',
            'units.name as unit_name',
            'units.code as unit_code',
        ])
        ->orderBy('contracts.ends_at')
        ->limit(10)
        ->get();

    return [$count, $rows];
}
```

In `render()`, after grace contracts:

```php
[$expiringSoonCount, $expiringSoonContracts] = $this->expiringSoonSummary($currentPlazaId);
```

Pass to view: `'expiringSoonCount' => $expiringSoonCount`, `'expiringSoonContracts' => $expiringSoonContracts`.

- [ ] **Step 5: Update dashboard Blade**

Stat-card (near active contracts). Wrap label/value in a link when count > 0 is optional; minimum: show count and ensure a visible link exists (stat card + table header link):

```blade
<a href="{{ route('contracts.index', ['status' => 'expiring']) }}" class="block">
    <x-ui.stat-card
        :label="__('dashboard.expiring_soon_contracts')"
        :value="(string) $expiringSoonCount"
        tone="warning"
    />
</a>
```

Table after grace table (same structure as overdue/grace):

```blade
<x-ui.table>
    <x-slot:header>
        <h2 class="text-sm font-semibold text-slate-900">{{ __('dashboard.expiring_soon_top10') }}</h2>
    </x-slot:header>
    <x-slot:head>
        <th class="px-4 py-3">{{ __('common.contract') }}</th>
        <th class="px-4 py-3">{{ __('common.tenant') }}</th>
        <th class="px-4 py-3">{{ __('common.unit') }}</th>
        <th class="px-4 py-3">{{ __('contracts.expiration') }}</th>
        <th class="px-4 py-3 text-right">{{ __('dashboard.days_remaining') }}</th>
        <th class="px-4 py-3 text-right">{{ __('common.action') }}</th>
    </x-slot:head>
    <x-slot:body>
        @forelse ($expiringSoonContracts as $row)
            @php
                $daysLeft = \Carbon\CarbonImmutable::now('America/Tijuana')->startOfDay()
                    ->diffInDays(\Carbon\CarbonImmutable::parse($row->ends_at)->startOfDay(), false);
            @endphp
            <tr wire:key="dashboard-expiring-{{ $row->contract_id }}" class="transition hover:bg-slate-50/80">
                <td class="px-4 py-3 text-slate-700">#{{ $row->contract_id }}</td>
                <td class="px-4 py-3 text-slate-700">
                    {{ $row->tenant_name }}
                    <p class="text-xs text-slate-500">{{ $row->tenant_phone ?: ($row->tenant_email ?: __('common.no_contact')) }}</p>
                </td>
                <td class="px-4 py-3 text-slate-700">
                    {{ $row->property_name }} / {{ $row->unit_name ?? ($row->unit_code ?? '-') }}
                </td>
                <td class="px-4 py-3 text-slate-700">
                    <x-ui.display-date :value="$row->ends_at" />
                </td>
                <td class="px-4 py-3 text-right">
                    <x-ui.badge variant="warning">{{ (int) $daysLeft }} {{ __('common.days') }}</x-ui.badge>
                </td>
                <td class="px-4 py-3 text-right">
                    <x-ui.button href="{{ route('contracts.show', $row->contract_id) }}" variant="secondary" size="sm">
                        {{ __('contracts.view') }}
                    </x-ui.button>
                </td>
            </tr>
        @empty
            <x-ui.empty-state :title="__('dashboard.no_expiring_soon')" :colspan="6" />
        @endforelse
    </x-slot:body>
</x-ui.table>
```

After `->get()`, map each row in PHP to attach `days_remaining` using the same signed-day logic as `Contract::daysUntilEnd` (avoid Blade Carbon math):

```php
$rows = $rows->map(function ($row) use ($today): object {
    $ends = CarbonImmutable::parse($row->ends_at, 'America/Tijuana')->startOfDay();
    $row->days_remaining = (int) $today->diffInDays($ends, false);

    return $row;
});
```

In Blade use `{{ (int) $row->days_remaining }}`.

- [ ] **Step 6: Run dashboard test**

Run: `./vendor/bin/sail test --filter=DashboardExpiringContractsTest`

Expected: PASS.

- [ ] **Step 7: Pint**

Run: `./vendor/bin/sail pint --dirty`

---

### Task 4: Sidebar attention badge via `ContractAttentionNav`

**Files:**
- Create: `app/Support/ContractAttentionNav.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `resources/views/layouts/partials/sidebar.blade.php`
- Create: `tests/Feature/Contracts/ContractAttentionNavTest.php`

**Interfaces:**
- Consumes: `Contract`, `TenantContext`, auth permissions
- Produces: `ContractAttentionNav::summary(): array{count: int, has_expired: bool}`
- View var: `$contractAttentionNav` from composer

- [ ] **Step 1: Write failing tests**

Unit-style (can live in Feature file with RefreshDatabase):

```php
public function test_summary_counts_attention_and_flags_expired(): void
{
    CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');
    // org + user with Admin role via actingAs later
    // contracts: expired, expiring, far, ended-with-past-ends_at
    $this->actingAs($user);
    $summary = ContractAttentionNav::summary();
    $this->assertSame(2, $summary['count']); // expired + expiring
    $this->assertTrue($summary['has_expired']);
}

public function test_sidebar_shows_red_badge_when_expired_present(): void
{
    // GET any authenticated page that includes sidebar (dashboard)
    $response = $this->actingAs($user)->get(route('dashboard'));
    $response->assertOk();
    $response->assertSee('contract-attention-badge', false);
    $response->assertSee(route('contracts.index', ['status' => 'attention'], false));
    // Assert red classes when has_expired: e.g. bg-rose-600 or bg-red-600
}

public function test_sidebar_shows_amber_badge_when_only_expiring(): void
{
    // only expiring contract → amber classes
}

public function test_sidebar_hides_badge_when_count_zero(): void
{
    $response->assertDontSee('contract-attention-badge', false);
}

public function test_other_plaza_contract_not_counted(): void
{
    CarbonImmutable::setTestNow('2026-08-01 10:00:00', 'America/Tijuana');
    $organization = Organization::factory()->create();
    TenantContext::setOrganizationId($organization->id);
    $user = User::factory()->create(['organization_id' => $organization->id]);

    $plazaA = Plaza::factory()->create(['organization_id' => $organization->id]);
    $plazaB = Plaza::factory()->create(['organization_id' => $organization->id]);

    // Property/unit/contract on plazaB ending 2026-08-15
    $this->createContractOnPlaza($organization->id, $plazaB->id, 'Otra Plaza', '2026-08-15');

    TenantContext::setCurrentPlazaId($plazaA->id);
    $this->actingAs($user);

    $summary = ContractAttentionNav::summary();
    $this->assertSame(0, $summary['count']);
    $this->assertFalse($summary['has_expired']);
}
```

Use `Plaza` factory + property `plaza_id` like `PlazaScopedScreensTest`.

- [ ] **Step 2: Run to verify fail**

Run: `./vendor/bin/sail test --filter=ContractAttentionNav`

Expected: FAIL.

- [ ] **Step 3: Implement `ContractAttentionNav`**

```php
<?php

namespace App\Support;

use App\Models\Contract;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class ContractAttentionNav
{
    /**
     * @return array{count: int, has_expired: bool}
     */
    public static function summary(): array
    {
        if (! (auth()->user()?->can('contracts.view') ?? false)) {
            return ['count' => 0, 'has_expired' => false];
        }

        $today = CarbonImmutable::now('America/Tijuana')->startOfDay();
        $horizon = $today->addDays(Contract::EXPIRING_SOON_DAYS)->toDateString();
        $todayDate = $today->toDateString();

        $base = Contract::query()
            ->join('units', 'units.id', '=', 'contracts.unit_id')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->where('contracts.status', Contract::STATUS_ACTIVE)
            ->whereNotNull('contracts.ends_at')
            ->whereDate('contracts.ends_at', '<=', $horizon);

        TenantContext::applyCurrentPlazaFilter($base, 'properties.plaza_id');

        $count = (clone $base)->count('contracts.id');
        $hasExpired = $count > 0
            && (clone $base)->whereDate('contracts.ends_at', '<', $todayDate)->exists();

        return [
            'count' => $count,
            'has_expired' => $hasExpired,
        ];
    }
}
```

- [ ] **Step 4: Register view composer**

In `AppServiceProvider::boot()`:

```php
use App\Support\ContractAttentionNav;
use Illuminate\Support\Facades\View;

View::composer('layouts.partials.sidebar', function ($view): void {
    $view->with('contractAttentionNav', ContractAttentionNav::summary());
});
```

- [ ] **Step 5: Update sidebar contracts link**

Replace the contracts `<a>` block:

```blade
@can('contracts.view')
@php
    $attentionCount = (int) ($contractAttentionNav['count'] ?? 0);
    $attentionHasExpired = (bool) ($contractAttentionNav['has_expired'] ?? false);
    $contractsHref = $attentionCount > 0
        ? route('contracts.index', ['status' => 'attention'])
        : route('contracts.index');
@endphp
<a href="{{ $contractsHref }}" class="{{ $lc('contracts.*') }}">
    <svg class="{{ $ic('contracts.*') }}" ...>...</svg>
    <span class="flex-1">{{ __('ui.nav.contracts') }}</span>
    @if ($attentionCount > 0)
        <span
            id="contract-attention-badge"
            class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-semibold leading-none text-white {{ $attentionHasExpired ? 'bg-rose-600' : 'bg-amber-500' }}"
        >
            {{ $attentionCount > 99 ? '99+' : $attentionCount }}
        </span>
    @endif
</a>
@endcan
```

Ensure `$linkBase` / flex layout still aligns (`items-center`); the `flex-1` on the label pushes the pill right.

- [ ] **Step 6: Run tests**

Run: `./vendor/bin/sail test --filter=ContractAttentionNav`

Expected: PASS.

- [ ] **Step 7: Pint**

Run: `./vendor/bin/sail pint --dirty`

---

### Task 5: Regression sweep

**Files:** none new — verification only

- [ ] **Step 1: Run related test suite**

```bash
./vendor/bin/sail test --filter='ContractExpiryTest|ContractExpiringSoonTest|ContractExpiredBadgeTest|DashboardExpiringContractsTest|ContractAttentionNav|ContractsIndexTest|DashboardControlCenterTest'
```

Expected: all PASS.

- [ ] **Step 2: Pint**

```bash
./vendor/bin/sail pint --dirty
```

- [ ] **Step 3: Manual smoke (optional)**

1. Seed/demo with a contract `ends_at` within 30 days → sidebar amber badge.
2. Past `ends_at` active → red badge; click → filter attention.
3. Dashboard top-10 lists only por vencer; stat count matches.
4. Index column shows fecha + días; sort by Vencimiento works.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| `isExpiringSoon` / `daysUntilEnd` / constant 30 | Task 1 |
| Index filters expiring + attention | Task 2 |
| Column fecha + días + sort | Task 2 |
| Badges priority + show banner | Task 2 |
| Dashboard stat + top-10 | Task 3 |
| Sidebar pill rojo/ámbar + link attention | Task 4 |
| Plaza scope | Tasks 3–4 |
| No migration / no persisted status | All |
| i18n es/en | Tasks 2–3 |

## Self-review notes

- `diffInDays` signed behavior verified in Task 1 against Carbon in this app; adjust if Carbon 3 returns float — cast `(int)`.
- Expired badge color changes from `warning` → `danger` so it differs from por vencer; label tests still pass.
- Sidebar `href` uses `attention` only when `count > 0` (plain index when empty) — slight UX refinement vs always-attention; still meets alert click path when badge visible.
)