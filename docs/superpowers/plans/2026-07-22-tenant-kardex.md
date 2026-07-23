# Tenant Kardex Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a tenant 360° kardex page at `/tenants/{tenant}` with summary KPI cards and tabbed lists (contracts, outstanding charges, recent payments), plus edit-from-show modal.

**Architecture:** Livewire `Tenants\Show` owns UI, tabs, and edit modal. `App\Support\TenantKardexSummary` aggregates KPIs and list rows across the tenant’s contracts using the same operational-charge rules as `Contracts\Show` (exclude `DEPOSIT_HOLD` + `DEPOSIT_APPLY`). Entry from tenants index via name link. No new financial actions or permissions.

**Tech Stack:** Laravel 11, Livewire 4, Tailwind, Spatie Permission, Sail for artisan/test/pint.

## Global Constraints

- Run all commands via `./vendor/bin/sail` (never bare `php artisan`).
- Multi-tenant: `Tenant` is `OrganizationScopedModel`; cross-org → 404.
- Permissions: `tenants.view` (show), `tenants.manage` (edit); gate payment links with `payments.view`, contract links with `contracts.view`.
- Layout UI = prototype #2 tabs (`docs/prototypes/2026-07-22-tenant-kardex-v2-tabs.html`).
- View actions in tables = eye icon only (same SVG as `documents/panel` contract variant); no “Ver …” text.
- KPIs: active contracts · pending balance · credit balance · total paid (`payments.amount` sum).
- Operational charges exclude `Charge::TYPE_DEPOSIT_HOLD` and `Charge::TYPE_DEPOSIT_APPLY`.
- No new permissions; no deposit/documents/timeline/PDF in v1.
- Tests: `./vendor/bin/sail test --filter=...`; format: `./vendor/bin/sail pint --dirty`.
- i18n: extend `lang/es/catalog.php` and `lang/en/catalog.php` under `tenants` / `tenants.kardex`.
- Spec: `docs/superpowers/specs/2026-07-22-tenant-kardex-design.md`.

---

## File Map

| File | Responsibility |
|------|----------------|
| `app/Support/TenantKardexSummary.php` | KPI + list aggregations for a tenant |
| `tests/Unit/Support/TenantKardexSummaryTest.php` | Unit tests for aggregates |
| `app/Livewire/Tenants/Show.php` | Kardex page + edit modal |
| `resources/views/livewire/tenants/show.blade.php` | Header, cards, profile, tabs, modal |
| `routes/web.php` | `GET /tenants/{tenant}` → `tenants.show` |
| `resources/views/livewire/tenants/index.blade.php` | Name → show link |
| `lang/es/catalog.php`, `lang/en/catalog.php` | Kardex copy |
| `tests/Feature/Tenants/TenantKardexShowTest.php` | Feature: show, authz, KPIs, edit, tabs, index link |

---

### Task 1: `TenantKardexSummary` service

**Files:**
- Create: `app/Support/TenantKardexSummary.php`
- Test: `tests/Unit/Support/TenantKardexSummaryTest.php`

**Interfaces:**
- Produces:
  - `TenantKardexSummary::for(Tenant $tenant): self` (or constructor + static factory)
  - `activeContractsCount(): int`
  - `pendingBalance(): float`
  - `creditBalance(): float`
  - `totalPaid(): float`
  - `contracts(): Collection` — each item: `id`, `status`, `rent_amount`, `starts_at`, `ends_at`, `unit_label`, `show_url`
  - `outstandingCharges(): Collection` — items with `balance > 0`: `contract_id`, `unit_label`, `type`, `charge_date`, `amount`, `paid`, `balance`, `contract_show_url`
  - `recentPayments(int $limit = 15): Collection` — `id`, `folio`, `paid_at`, `method`, `amount`, `contract_id`, `show_url`

- [ ] **Step 1: Write the failing unit test**

```php
<?php

namespace Tests\Unit\Support;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantKardexSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantKardexSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aggregates_kpis_across_tenant_contracts(): void
    {
        $organization = Organization::factory()->create();
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);

        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unitA = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'name' => '402',
        ]);
        $unitB = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
            'name' => 'Local 3',
        ]);

        $active = Contract::factory()->create([
            'organization_id' => $organization->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unitA->id,
            'status' => Contract::STATUS_ACTIVE,
            'rent_amount' => 12500,
        ]);
        $ended = Contract::factory()->create([
            'organization_id' => $organization->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unitB->id,
            'status' => Contract::STATUS_ENDED,
            'rent_amount' => 8000,
            'ends_at' => '2025-02-28',
        ]);

        $rent = Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $active->id,
            'unit_id' => $active->unit_id,
            'type' => Charge::TYPE_RENT,
            'amount' => 12500,
            'charge_date' => '2026-07-01',
        ]);
        Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $active->id,
            'unit_id' => $active->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'amount' => 12500,
            'charge_date' => '2026-01-01',
        ]);

        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $active->id,
            'amount' => 8000,
            'paid_at' => '2026-07-03 12:00:00',
        ]);
        PaymentAllocation::factory()->create([
            'organization_id' => $organization->id,
            'payment_id' => $payment->id,
            'charge_id' => $rent->id,
            'amount' => 8000,
        ]);

        Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $ended->id,
            'amount' => 8000,
            'paid_at' => '2025-02-01 12:00:00',
        ]);

        CreditBalance::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $active->id,
            'balance' => 200,
        ]);

        $summary = TenantKardexSummary::for($tenant);

        $this->assertSame(1, $summary->activeContractsCount());
        $this->assertSame(4500.0, $summary->pendingBalance());
        $this->assertSame(200.0, $summary->creditBalance());
        $this->assertSame(16000.0, $summary->totalPaid());
        $this->assertCount(2, $summary->contracts());
        $this->assertCount(1, $summary->outstandingCharges());
        $this->assertSame(4500.0, (float) $summary->outstandingCharges()->first()['balance']);
        $this->assertCount(2, $summary->recentPayments());
    }

    public function test_deposit_apply_is_excluded_from_pending_balance(): void
    {
        $organization = Organization::factory()->create();
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'status' => Contract::STATUS_ACTIVE,
        ]);

        Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_DEPOSIT_APPLY,
            'amount' => 500,
            'charge_date' => '2026-07-01',
        ]);

        $summary = TenantKardexSummary::for($tenant);

        $this->assertSame(0.0, $summary->pendingBalance());
        $this->assertCount(0, $summary->outstandingCharges());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail test --filter=TenantKardexSummaryTest`

Expected: FAIL — class `TenantKardexSummary` not found.

- [ ] **Step 3: Implement `TenantKardexSummary`**

Create `app/Support/TenantKardexSummary.php`:

```php
<?php

namespace App\Support;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Tenant;
use Illuminate\Support\Collection;

final class TenantKardexSummary
{
    /** @var list<int> */
    private array $contractIds;

    public function __construct(private readonly Tenant $tenant)
    {
        $this->contractIds = $tenant->contracts()->pluck('id')->all();
    }

    public static function for(Tenant $tenant): self
    {
        return new self($tenant);
    }

    public function activeContractsCount(): int
    {
        return (int) $this->tenant->contracts()
            ->where('status', Contract::STATUS_ACTIVE)
            ->count();
    }

    public function pendingBalance(): float
    {
        return round((float) $this->outstandingCharges()->sum('balance'), 2);
    }

    public function creditBalance(): float
    {
        if ($this->contractIds === []) {
            return 0.0;
        }

        return round((float) CreditBalance::query()
            ->whereIn('contract_id', $this->contractIds)
            ->sum('balance'), 2);
    }

    public function totalPaid(): float
    {
        if ($this->contractIds === []) {
            return 0.0;
        }

        return round((float) Payment::query()
            ->whereIn('contract_id', $this->contractIds)
            ->sum('amount'), 2);
    }

    /**
     * @return Collection<int, array{
     *     id:int,
     *     status:string,
     *     rent_amount:float,
     *     starts_at:?string,
     *     ends_at:?string,
     *     unit_label:string,
     *     show_url:string
     * }>
     */
    public function contracts(): Collection
    {
        return $this->tenant->contracts()
            ->with(['unit.property'])
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (Contract $contract): array {
                $propertyName = $contract->unit?->property?->name ?? '';
                $unitName = $contract->unit?->name ?? '';
                $unitLabel = trim($propertyName.' / '.$unitName, ' /');

                return [
                    'id' => $contract->id,
                    'status' => $contract->status,
                    'rent_amount' => round((float) $contract->rent_amount, 2),
                    'starts_at' => optional($contract->starts_at)->format('Y-m-d'),
                    'ends_at' => optional($contract->ends_at)->format('Y-m-d'),
                    'unit_label' => $unitLabel !== '' ? $unitLabel : __('common.n_a'),
                    'show_url' => route('contracts.show', $contract),
                ];
            });
    }

    /**
     * @return Collection<int, array{
     *     contract_id:int,
     *     unit_label:string,
     *     type:string,
     *     charge_date:?string,
     *     amount:float,
     *     paid:float,
     *     balance:float,
     *     contract_show_url:string
     * }>
     */
    public function outstandingCharges(): Collection
    {
        if ($this->contractIds === []) {
            return collect();
        }

        $allocationSubquery = PaymentAllocation::query()
            ->selectRaw('payment_allocations.charge_id, SUM(payment_allocations.amount) as allocated_total')
            ->groupBy('payment_allocations.charge_id');

        $charges = Charge::query()
            ->whereIn('charges.contract_id', $this->contractIds)
            ->whereNotIn('charges.type', [Charge::TYPE_DEPOSIT_HOLD, Charge::TYPE_DEPOSIT_APPLY])
            ->with(['contract.unit.property'])
            ->leftJoinSub($allocationSubquery, 'alloc', function ($join): void {
                $join->on('alloc.charge_id', '=', 'charges.id');
            })
            ->select('charges.*')
            ->selectRaw('COALESCE(alloc.allocated_total, 0) as allocated_amount')
            ->orderByDesc('charges.charge_date')
            ->orderByDesc('charges.id')
            ->get();

        return $charges
            ->map(function (Charge $charge): array {
                $amount = round((float) $charge->amount, 2);
                $paid = round((float) max(min((float) $charge->allocated_amount, $amount), 0), 2);
                $balance = round($amount - $paid, 2);
                $propertyName = $charge->contract?->unit?->property?->name ?? '';
                $unitName = $charge->contract?->unit?->name ?? '';
                $unitLabel = trim('#'.$charge->contract_id.' · '.$unitName, ' ·');

                return [
                    'contract_id' => (int) $charge->contract_id,
                    'unit_label' => $unitLabel !== '' ? $unitLabel : '#'.$charge->contract_id,
                    'type' => $charge->type,
                    'charge_date' => optional($charge->charge_date)->format('Y-m-d'),
                    'amount' => $amount,
                    'paid' => $paid,
                    'balance' => $balance,
                    'contract_show_url' => route('contracts.show', $charge->contract_id),
                ];
            })
            ->filter(fn (array $row): bool => $row['balance'] > 0)
            ->values();
    }

    /**
     * @return Collection<int, array{
     *     id:int,
     *     folio:?string,
     *     paid_at:?string,
     *     method:?string,
     *     amount:float,
     *     contract_id:int,
     *     show_url:string
     * }>
     */
    public function recentPayments(int $limit = 15): Collection
    {
        if ($this->contractIds === []) {
            return collect();
        }

        return Payment::query()
            ->whereIn('contract_id', $this->contractIds)
            ->latest('paid_at')
            ->limit($limit)
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'folio' => $payment->receipt_folio,
                'paid_at' => optional($payment->paid_at)->format('Y-m-d'),
                'method' => $payment->method,
                'amount' => round((float) $payment->amount, 2),
                'contract_id' => (int) $payment->contract_id,
                'show_url' => route('payments.show', $payment),
            ]);
    }
}
```

Adjust `unit_label` formatting if tests need `property / unit` (match prototype). Prefer:

```php
$unitLabel = trim(($propertyName !== '' ? $propertyName.' / ' : '').$unitName);
```

for contracts; for charges keep `#id · unitName`.

- [ ] **Step 4: Run unit tests**

Run: `./vendor/bin/sail test --filter=TenantKardexSummaryTest`

Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add app/Support/TenantKardexSummary.php tests/Unit/Support/TenantKardexSummaryTest.php
git commit -m "$(cat <<'EOF'
Add TenantKardexSummary for tenant-level financial aggregates.

EOF
)"
```

---

### Task 2: Route + Livewire `Tenants\Show` (authz + KPIs)

**Files:**
- Create: `app/Livewire/Tenants/Show.php`
- Create: `resources/views/livewire/tenants/show.blade.php` (minimal shell)
- Modify: `routes/web.php` (after `tenants.index`)
- Modify: `lang/es/catalog.php`, `lang/en/catalog.php` (kardex keys needed by assertions)
- Test: `tests/Feature/Tenants/TenantKardexShowTest.php`

**Interfaces:**
- Consumes: `TenantKardexSummary::for()`
- Produces: Livewire route `tenants.show`; public props `tenant`, `tab`; edit fields mirrored from `Tenants\Index`

- [ ] **Step 1: Write failing feature tests**

```php
<?php

namespace Tests\Feature\Tenants;

use App\Livewire\Tenants\Show;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\CreditBalance;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TenantKardexShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_requires_tenants_view(): void
    {
        $organization = Organization::factory()->create();
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);
        $user = User::factory()->create(['organization_id' => $organization->id]);
        $user->syncRoles([]);

        $this->actingAs($user)
            ->get(route('tenants.show', $tenant))
            ->assertForbidden();
    }

    public function test_show_hides_tenant_from_other_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        $tenantB = Tenant::factory()->create(['organization_id' => $orgB->id]);
        $userA = User::factory()->create(['organization_id' => $orgA->id]);

        $this->actingAs($userA)
            ->get(route('tenants.show', $tenantB))
            ->assertNotFound();
    }

    public function test_show_renders_kpis_and_tabs(): void
    {
        [$organization, $tenant] = $this->seedKardexGraph();
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($user)
            ->get(route('tenants.show', $tenant))
            ->assertOk()
            ->assertSeeText($tenant->full_name)
            ->assertSeeText(__('catalog.tenants.kardex.active_contracts'))
            ->assertSeeText(__('catalog.tenants.kardex.pending_balance'))
            ->assertSeeText(__('catalog.tenants.kardex.credit_balance'))
            ->assertSeeText(__('catalog.tenants.kardex.total_paid'))
            ->assertSeeText('$4,500.00')
            ->assertSeeText('$200.00')
            ->assertSeeText('$16,000.00')
            ->assertSeeText(__('catalog.tenants.kardex.tab_contracts'))
            ->assertSeeText(__('catalog.tenants.kardex.tab_charges'))
            ->assertSeeText(__('catalog.tenants.kardex.tab_payments'));
    }

    public function test_edit_from_show_updates_tenant(): void
    {
        $organization = Organization::factory()->create();
        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'full_name' => 'Nombre Viejo',
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        Livewire::actingAs($user)
            ->test(Show::class, ['tenant' => $tenant])
            ->call('startEdit')
            ->set('full_name', 'Nombre Nuevo')
            ->set('formStatus', 'active')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Nombre Nuevo', $tenant->fresh()->full_name);
    }

    public function test_index_name_links_to_kardex(): void
    {
        $organization = Organization::factory()->create();
        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'full_name' => 'Maria Link',
        ]);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($user)
            ->get(route('tenants.index'))
            ->assertOk()
            ->assertSee('href="'.route('tenants.show', $tenant).'"', false);
    }

    /**
     * @return array{Organization, Tenant}
     */
    private function seedKardexGraph(): array
    {
        $organization = Organization::factory()->create();
        $tenant = Tenant::factory()->create([
            'organization_id' => $organization->id,
            'full_name' => 'Maria Fernanda Lopez',
        ]);
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $unit->id,
            'status' => Contract::STATUS_ACTIVE,
        ]);
        $endedUnit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $ended = Contract::factory()->create([
            'organization_id' => $organization->id,
            'tenant_id' => $tenant->id,
            'unit_id' => $endedUnit->id,
            'status' => Contract::STATUS_ENDED,
            'ends_at' => '2025-02-28',
        ]);

        $rent = Charge::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'unit_id' => $unit->id,
            'type' => Charge::TYPE_RENT,
            'amount' => 12500,
            'charge_date' => '2026-07-01',
        ]);
        $payment = Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'amount' => 8000,
            'paid_at' => '2026-07-03 12:00:00',
        ]);
        PaymentAllocation::factory()->create([
            'organization_id' => $organization->id,
            'payment_id' => $payment->id,
            'charge_id' => $rent->id,
            'amount' => 8000,
        ]);
        Payment::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $ended->id,
            'amount' => 8000,
            'paid_at' => '2025-02-01 12:00:00',
        ]);
        CreditBalance::factory()->create([
            'organization_id' => $organization->id,
            'contract_id' => $contract->id,
            'balance' => 200,
        ]);

        return [$organization, $tenant];
    }
}
```

- [ ] **Step 2: Run tests — expect fail**

Run: `./vendor/bin/sail test --filter=TenantKardexShowTest`

Expected: FAIL (missing route / component).

- [ ] **Step 3: Add i18n keys**

In `lang/es/catalog.php` under `tenants`:

```php
'kardex' => [
    'page_title' => 'Kardex de inquilino',
    'back_to_tenants' => 'Volver a inquilinos',
    'profile_title' => 'Datos del inquilino',
    'active_contracts' => 'Contratos activos',
    'active_contracts_hint' => 'de :total contratos en total',
    'pending_balance' => 'Saldo pendiente',
    'pending_balance_hint' => 'Cargos operativos sin aplicar',
    'credit_balance' => 'Saldo a favor',
    'total_paid' => 'Total pagado',
    'total_paid_hint' => 'Histórico recibido',
    'tab_contracts' => 'Contratos',
    'tab_charges' => 'Cargos con saldo',
    'tab_charges_hint' => 'Operativos · excluye depósito en custodia',
    'tab_payments' => 'Pagos recientes',
    'empty_contracts' => 'Este inquilino no tiene contratos.',
    'empty_charges' => 'No hay cargos con saldo pendiente.',
    'empty_payments' => 'No hay pagos registrados.',
    'view_contract' => 'Ver contrato',
    'view_payment' => 'Ver pago',
    'flash_updated' => 'Inquilino actualizado.',
],
```

Mirror in `lang/en/catalog.php` (English equivalents).

- [ ] **Step 4: Register route**

In `routes/web.php`, import `Show as TenantsShow` and add after `tenants.index`:

```php
Route::get('/tenants/{tenant}', TenantsShow::class)
    ->middleware('permission:tenants.view')
    ->name('tenants.show');
```

- [ ] **Step 5: Implement Livewire `Show`**

Create `app/Livewire/Tenants/Show.php` patterned on `Tenants\Index` validation + `Units\Show` mount:

```php
<?php

namespace App\Livewire\Tenants;

use App\Models\Tenant;
use App\Support\TenantKardexSummary;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Show extends Component
{
    public Tenant $tenant;

    public string $tab = 'contracts';

    public bool $showForm = false;

    public string $full_name = '';

    public ?string $email = null;

    public ?string $phone = null;

    public string $formStatus = 'active';

    public ?string $notes = null;

    /**
     * @var array<string, array<string, string>>
     */
    protected $queryString = [
        'tab' => ['except' => 'contracts'],
    ];

    public function mount(Tenant $tenant): void
    {
        if (! (auth()->user()?->can('tenants.view') ?? false)) {
            abort(403);
        }

        $this->tenant = $tenant;

        if (! in_array($this->tab, ['contracts', 'charges', 'payments'], true)) {
            $this->tab = 'contracts';
        }
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['contracts', 'charges', 'payments'], true)) {
            return;
        }

        $this->tab = $tab;
    }

    public function startEdit(): void
    {
        if (! (auth()->user()?->can('tenants.manage') ?? false)) {
            abort(403);
        }

        $this->full_name = $this->tenant->full_name;
        $this->email = $this->tenant->email;
        $this->phone = $this->tenant->phone;
        $this->formStatus = $this->tenant->status;
        $this->notes = $this->tenant->notes;
        $this->showForm = true;
        $this->resetValidation();
    }

    public function cancelForm(): void
    {
        $this->showForm = false;
        $this->resetValidation();
    }

    public function save(): void
    {
        if (! (auth()->user()?->can('tenants.manage') ?? false)) {
            abort(403);
        }

        $validated = $this->validate([
            'full_name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:50'],
            'formStatus' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'full_name.required' => __('catalog.validation.full_name_required'),
            'full_name.max' => __('catalog.validation.full_name_max'),
            'email.email' => __('catalog.validation.email_invalid'),
            'email.max' => __('catalog.validation.email_max'),
            'phone.max' => __('catalog.validation.phone_max'),
            'formStatus.required' => __('catalog.validation.status_required'),
            'formStatus.in' => __('catalog.validation.status_invalid'),
            'notes.max' => __('catalog.validation.notes_max'),
        ]);

        $this->tenant->update([
            'full_name' => $validated['full_name'],
            'email' => $validated['email'] ?: null,
            'phone' => $validated['phone'] ?: null,
            'status' => $validated['formStatus'],
            'notes' => $validated['notes'] ?: null,
        ]);

        $this->tenant->refresh();
        $this->showForm = false;
        session()->flash('success', __('catalog.tenants.kardex.flash_updated'));
    }

    public function render(TenantKardexSummary $unused = null): View
    {
        $summary = TenantKardexSummary::for($this->tenant->fresh());

        return view('livewire.tenants.show', [
            'summary' => $summary,
            'contracts' => $summary->contracts(),
            'charges' => $summary->outstandingCharges(),
            'payments' => $summary->recentPayments(),
            'canManageTenants' => auth()->user()?->can('tenants.manage') ?? false,
            'canViewContracts' => auth()->user()?->can('contracts.view') ?? false,
            'canViewPayments' => auth()->user()?->can('payments.view') ?? false,
        ])->layout('layouts.app', [
            'title' => __('catalog.tenants.kardex.page_title'),
        ]);
    }
}
```

Remove unused `$unused` param — call `TenantKardexSummary::for` only inside `render()`.

- [ ] **Step 6: Implement Blade UI (tabs + eye icons)**

Create `resources/views/livewire/tenants/show.blade.php` matching prototype #2:

- `x-ui.page-header` with back + edit
- 4× `x-ui.stat-card`
- profile `x-ui.card`
- One `x-ui.card` with tab buttons `wire:click="setTab('…')"` and `aria-selected`
- Three panels gated by `$tab === '…'`
- Tables via `x-ui.table`; empty via `x-ui.empty-state`
- Eye icon buttons for contract/payment (copy SVG from `documents/panel.blade.php`); `title` + `aria-label` from `__('catalog.tenants.kardex.view_*')`
- Modal reuse same fields as index (`x-ui.modal`)

Stat card pending tone: `tone="danger"` + rose value class when pending > 0; total paid `tone="success"` optional.

- [ ] **Step 7: Link index name to show**

In `resources/views/livewire/tenants/index.blade.php`, replace the plain name `<p>` with:

```blade
<a href="{{ route('tenants.show', $tenant) }}" class="font-medium text-indigo-600 hover:text-indigo-500 hover:underline">
    {{ $tenant->full_name }}
</a>
```

Keep email/phone subtitle under the link.

- [ ] **Step 8: Run feature tests**

Run: `./vendor/bin/sail test --filter=TenantKardexShowTest`

Expected: PASS

- [ ] **Step 9: Pint + commit**

```bash
./vendor/bin/sail pint --dirty
git add app/Livewire/Tenants/Show.php resources/views/livewire/tenants/show.blade.php resources/views/livewire/tenants/index.blade.php routes/web.php lang/es/catalog.php lang/en/catalog.php tests/Feature/Tenants/TenantKardexShowTest.php
git commit -m "$(cat <<'EOF'
Add tenant kardex show page with tabbed related data.

EOF
)"
```

---

### Task 3: Spec/docs + final verification

**Files:**
- Modify (if needed): `docs/AI_ONBOARDING.md` map `/tenants/{id}` → `Tenants\Show`
- Already present: design spec + prototypes

- [ ] **Step 1: Document route in onboarding**

In `docs/AI_ONBOARDING.md` near `/tenants` → `Tenants\Index`, add:

```text
- `/tenants/{tenant}` -> [`Tenants\Show`](../app/Livewire/Tenants/Show.php) (kardex)
```

- [ ] **Step 2: Full filter run + pint**

```bash
./vendor/bin/sail test --filter=TenantKardex
./vendor/bin/sail pint --dirty
```

Expected: all PASS; pint clean.

- [ ] **Step 3: Commit docs**

```bash
git add docs/AI_ONBOARDING.md docs/superpowers/specs/2026-07-22-tenant-kardex-design.md docs/prototypes/2026-07-22-tenant-kardex.html docs/prototypes/2026-07-22-tenant-kardex-v2-tabs.html docs/superpowers/plans/2026-07-22-tenant-kardex.md
git commit -m "$(cat <<'EOF'
Document tenant kardex design, prototypes, and plan.

EOF
)"
```

(Only if those docs are still uncommitted; skip files already committed.)

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| `/tenants/{tenant}` show | 2 |
| `TenantKardexSummary` KPIs | 1 |
| Exclude deposit hold/apply | 1 |
| Cards + profile + tabs UI | 2 |
| Eye icons for view actions | 2 |
| Edit modal on show | 2 |
| Index name link | 2 |
| Permissions / org isolation | 2 |
| i18n | 2 |
| Tests | 1–2 |
| No financial actions / no new perms | Global + all tasks |

## Self-review notes

- No placeholders left in steps.
- `DEPOSIT_APPLY` excluded to match `Contracts\Show` (spec updated).
- Commit steps assume user wants commits during execution; if user prefers no commits, skip Step 5/9/3 commit blocks and leave staging to them.
