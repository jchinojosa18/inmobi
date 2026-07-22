# Deposit Hold Cap Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cap registered `DEPOSIT_HOLD` charges per contract at `contracts.deposit_amount` (partials allowed), with Action validation and UI that hides the form when the cap is reached.

**Architecture:** Add `registeredDepositHoldAmount` / `remainingDepositHoldAmount` on `DepositBalanceService`. `RegisterDepositHoldAction` enforces the cap inside the existing contract `lockForUpdate` transaction. `DepositHoldForm` shows registered/remaining, prefills remaining, and swaps to a “complete” state when remaining is 0.

**Tech Stack:** Laravel 11, Livewire 4, PHPUnit via Sail, Pint.

## Global Constraints

- Run all commands via `./vendor/bin/sail` (never bare `php artisan` / `phpunit` / `pint`).
- Diff mínimo: no refactor fuera del alcance; no tocar finiquito ni `availableDepositAmount` (sigue por allocations).
- Contador del tope = suma de `charges.amount` con `type = DEPOSIT_HOLD` (no allocations).
- Spec: `docs/superpowers/specs/2026-07-21-deposit-hold-cap-design.md`.
- Tests: `./vendor/bin/sail test --filter=DepositHold`; format: `./vendor/bin/sail pint --dirty`.
- No commit unless the user explicitly asks (repo user rule).

---

## File Map

| File | Responsibility |
|------|----------------|
| `app/Support/DepositBalanceService.php` | `registeredDepositHoldAmount`, `remainingDepositHoldAmount` |
| `app/Actions/Contracts/RegisterDepositHoldAction.php` | Cap validation after lock, before create |
| `app/Livewire/Contracts/DepositHoldForm.php` | Totals, prefill remaining, complete state |
| `resources/views/livewire/contracts/deposit-hold-form.blade.php` | Summary + form / complete UI |
| `lang/es/contracts.php` | Copy for summary, complete, validation errors |
| `lang/en/contracts.php` | English pair |
| `docs/AI_ONBOARDING.md` | §4.3 note about cap |
| `tests/Unit/Support/DepositBalanceServiceDepositHoldCapTest.php` | Service totals |
| `tests/Unit/Actions/RegisterDepositHoldActionTest.php` | Action cap + partials |
| `tests/Feature/Contracts/DepositHoldFormTest.php` | Livewire UI |

---

### Task 1: DepositBalanceService helpers

**Files:**
- Modify: `app/Support/DepositBalanceService.php`
- Create: `tests/Unit/Support/DepositBalanceServiceDepositHoldCapTest.php`

**Interfaces:**
- Consumes: `App\Models\Charge`, `App\Models\Contract`
- Produces:
  - `registeredDepositHoldAmount(Contract $contract): float`
  - `remainingDepositHoldAmount(Contract $contract): float` (= `max(deposit_amount - registered, 0)`)

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Support/DepositBalanceServiceDepositHoldCapTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\DepositBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositBalanceServiceDepositHoldCapTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_and_remaining_deposit_hold_amounts(): void
    {
        $contract = $this->makeContract(depositAmount: 1000.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 400,
        ]);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-03',
            'charge_date' => '2026-03-05',
            'amount' => 250,
        ]);

        $service = app(DepositBalanceService::class);

        $this->assertSame(650.0, $service->registeredDepositHoldAmount($contract));
        $this->assertSame(350.0, $service->remainingDepositHoldAmount($contract));
    }

    public function test_remaining_is_zero_when_registered_meets_or_exceeds_deposit_amount(): void
    {
        $contract = $this->makeContract(depositAmount: 500.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 500,
        ]);

        $service = app(DepositBalanceService::class);

        $this->assertSame(500.0, $service->registeredDepositHoldAmount($contract));
        $this->assertSame(0.0, $service->remainingDepositHoldAmount($contract));
    }

    private function makeContract(float $depositAmount): Contract
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);

        return Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'deposit_amount' => $depositAmount,
            'rent_amount' => 0,
        ]);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/sail test --filter=DepositBalanceServiceDepositHoldCapTest`

Expected: FAIL — methods undefined on `DepositBalanceService`.

- [ ] **Step 3: Implement helpers**

Add to `app/Support/DepositBalanceService.php`:

```php
public function registeredDepositHoldAmount(Contract $contract): float
{
    return round((float) Charge::query()
        ->withoutOrganizationScope()
        ->where('organization_id', $contract->organization_id)
        ->where('contract_id', $contract->id)
        ->where('type', Charge::TYPE_DEPOSIT_HOLD)
        ->sum('amount'), 2);
}

public function remainingDepositHoldAmount(Contract $contract): float
{
    $depositAmount = round((float) $contract->deposit_amount, 2);

    return round(max($depositAmount - $this->registeredDepositHoldAmount($contract), 0), 2);
}
```

Place them near the other deposit helpers (above `paidDepositAmount` or after `availableDepositAmount`). Keep existing methods unchanged.

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/sail test --filter=DepositBalanceServiceDepositHoldCapTest`

Expected: PASS

- [ ] **Step 5: Format**

Run: `./vendor/bin/sail pint --dirty`

---

### Task 2: RegisterDepositHoldAction cap

**Files:**
- Modify: `app/Actions/Contracts/RegisterDepositHoldAction.php`
- Modify: `lang/es/contracts.php` (validation keys)
- Modify: `lang/en/contracts.php` (validation keys)
- Create: `tests/Unit/Actions/RegisterDepositHoldActionTest.php`

**Interfaces:**
- Consumes: `DepositBalanceService::remainingDepositHoldAmount(Contract): float`
- Produces: ValidationException keys `deposit_amount` with messages:
  - `contracts.validation.deposit_already_complete`
  - `contracts.validation.deposit_exceeds_remaining` (with `:remaining`)

- [ ] **Step 1: Add i18n keys**

In `lang/es/contracts.php` under `validation`:

```php
'deposit_already_complete' => 'El depósito del contrato ya está completo. No se pueden registrar más cargos DEPOSIT_HOLD.',
'deposit_exceeds_remaining' => 'El monto excede el remanente del depósito (:remaining).',
```

In `lang/en/contracts.php` under `validation`:

```php
'deposit_already_complete' => 'The contract deposit is already complete. No more DEPOSIT_HOLD charges can be recorded.',
'deposit_exceeds_remaining' => 'The amount exceeds the remaining deposit (:remaining).',
```

- [ ] **Step 2: Write the failing Action tests**

Create `tests/Unit/Actions/RegisterDepositHoldActionTest.php`:

```php
<?php

namespace Tests\Unit\Actions;

use App\Actions\Contracts\RegisterDepositHoldAction;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RegisterDepositHoldActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
    }

    public function test_partial_holds_are_allowed_until_deposit_amount_is_reached(): void
    {
        $contract = $this->makeContract(depositAmount: 1000.0);
        $action = app(RegisterDepositHoldAction::class);

        $action->execute($contract, 400.0, '2026-03-01', 'parcial 1', null);
        $action->execute($contract, 600.0, '2026-03-02', 'parcial 2', null);

        $this->assertSame(2, Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_DEPOSIT_HOLD)
            ->count());

        $sum = (float) Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_DEPOSIT_HOLD)
            ->sum('amount');

        $this->assertSame(1000.0, $sum);
    }

    public function test_amount_exceeding_remaining_is_rejected(): void
    {
        $contract = $this->makeContract(depositAmount: 1000.0);
        $action = app(RegisterDepositHoldAction::class);

        $action->execute($contract, 700.0, '2026-03-01', null, null);

        try {
            $action->execute($contract, 400.0, '2026-03-02', null, null);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('deposit_amount', $exception->errors());
        }

        $this->assertSame(1, Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_DEPOSIT_HOLD)
            ->count());
    }

    public function test_registration_is_rejected_when_deposit_already_complete(): void
    {
        $contract = $this->makeContract(depositAmount: 500.0);
        $action = app(RegisterDepositHoldAction::class);

        $action->execute($contract, 500.0, '2026-03-01', null, null);

        $this->expectException(ValidationException::class);
        $action->execute($contract, 100.0, '2026-03-02', null, null);
    }

    public function test_idempotent_same_date_and_amount_does_not_create_duplicate(): void
    {
        $contract = $this->makeContract(depositAmount: 1000.0);
        $action = app(RegisterDepositHoldAction::class);

        $first = $action->execute($contract, 400.0, '2026-03-01', 'a', null);
        $second = $action->execute($contract, 400.0, '2026-03-01', 'b', null);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_DEPOSIT_HOLD)
            ->count());
    }

    private function makeContract(float $depositAmount): Contract
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);

        return Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'deposit_amount' => $depositAmount,
            'rent_amount' => 0,
        ]);
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `./vendor/bin/sail test --filter=RegisterDepositHoldActionTest`

Expected: FAIL on exceed / complete cases (today Action allows overshoot).

- [ ] **Step 4: Implement Action guard**

Inject `DepositBalanceService` into `RegisterDepositHoldAction`. Inside the transaction, **after** the existing same-date+amount lookup (return early if found), and **before** create:

```php
$remaining = $this->depositBalanceService->remainingDepositHoldAmount($lockedContract);

if ($remaining <= 0) {
    throw ValidationException::withMessages([
        'deposit_amount' => __('contracts.validation.deposit_already_complete'),
    ]);
}

if (round($amount, 2) > $remaining) {
    throw ValidationException::withMessages([
        'deposit_amount' => __('contracts.validation.deposit_exceeds_remaining', [
            'remaining' => number_format($remaining, 2, '.', ''),
        ]),
    ]);
}
```

Constructor becomes:

```php
public function __construct(
    private readonly AuditLogger $auditLogger,
    private readonly DepositBalanceService $depositBalanceService,
) {}
```

Keep create + audit logging unchanged.

- [ ] **Step 5: Run tests to verify they pass**

Run: `./vendor/bin/sail test --filter=RegisterDepositHoldActionTest`

Expected: PASS

- [ ] **Step 6: Format**

Run: `./vendor/bin/sail pint --dirty`

---

### Task 3: DepositHoldForm UI

**Files:**
- Modify: `app/Livewire/Contracts/DepositHoldForm.php`
- Modify: `resources/views/livewire/contracts/deposit-hold-form.blade.php`
- Modify: `lang/es/contracts.php` / `lang/en/contracts.php` (UI labels)
- Create: `tests/Feature/Contracts/DepositHoldFormTest.php`

**Interfaces:**
- Consumes: `DepositBalanceService::registeredDepositHoldAmount`, `remainingDepositHoldAmount`
- Produces: view props `contractDepositAmount`, `registeredDeposit`, `remainingDeposit`; form hidden when `remainingDeposit <= 0`

- [ ] **Step 1: Add UI i18n keys**

`lang/es/contracts.php` (top-level, near `deposit_received`):

```php
'deposit_contract_amount' => 'Depósito del contrato',
'deposit_registered' => 'Registrado',
'deposit_remaining' => 'Remanente',
'deposit_complete_title' => 'Depósito completo',
'deposit_complete_description' => 'Ya se registró el depósito completo del contrato (:amount).',
```

`lang/en/contracts.php`:

```php
'deposit_contract_amount' => 'Contract deposit',
'deposit_registered' => 'Registered',
'deposit_remaining' => 'Remaining',
'deposit_complete_title' => 'Deposit complete',
'deposit_complete_description' => 'The full contract deposit has already been recorded (:amount).',
```

- [ ] **Step 2: Write failing Livewire tests**

Create `tests/Feature/Contracts/DepositHoldFormTest.php`:

```php
<?php

namespace Tests\Feature\Contracts;

use App\Livewire\Contracts\DepositHoldForm;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DepositHoldFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_prefills_remaining_and_allows_partial_registration(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 1000.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 400,
        ]);

        Livewire::actingAs($user)
            ->test(DepositHoldForm::class, ['contract' => $contract])
            ->assertSet('deposit_amount', '600.00')
            ->assertSee(__('contracts.deposit_remaining'))
            ->set('deposit_received_at', '2026-03-10')
            ->set('deposit_amount', '600.00')
            ->call('registerDeposit')
            ->assertHasNoErrors();

        $this->assertSame(1000.0, (float) Charge::query()
            ->where('contract_id', $contract->id)
            ->where('type', Charge::TYPE_DEPOSIT_HOLD)
            ->sum('amount'));
    }

    public function test_form_is_hidden_when_deposit_is_complete(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 500.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 500,
        ]);

        Livewire::actingAs($user)
            ->test(DepositHoldForm::class, ['contract' => $contract])
            ->assertSee(__('contracts.deposit_complete_title'))
            ->assertDontSee(__('contracts.register_deposit'));
    }

    public function test_registering_above_remaining_shows_error(): void
    {
        [$user, $contract] = $this->makeContractWithUser(depositAmount: 1000.0);

        Charge::factory()->create([
            'organization_id' => $contract->organization_id,
            'contract_id' => $contract->id,
            'unit_id' => $contract->unit_id,
            'type' => Charge::TYPE_DEPOSIT_HOLD,
            'period' => '2026-03',
            'charge_date' => '2026-03-01',
            'amount' => 800,
        ]);

        Livewire::actingAs($user)
            ->test(DepositHoldForm::class, ['contract' => $contract])
            ->set('deposit_received_at', '2026-03-10')
            ->set('deposit_amount', '300.00')
            ->call('registerDeposit')
            ->assertHasErrors(['deposit_general']);
    }

    /**
     * @return array{0: User, 1: Contract}
     */
    private function makeContractWithUser(float $depositAmount): array
    {
        $organization = Organization::factory()->create();
        $property = Property::factory()->create(['organization_id' => $organization->id]);
        $unit = Unit::factory()->create([
            'organization_id' => $organization->id,
            'property_id' => $property->id,
        ]);
        $tenant = Tenant::factory()->create(['organization_id' => $organization->id]);
        $contract = Contract::factory()->create([
            'organization_id' => $organization->id,
            'unit_id' => $unit->id,
            'tenant_id' => $tenant->id,
            'deposit_amount' => $depositAmount,
            'rent_amount' => 0,
        ]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        // Ensure Admin (charges.manage) even when using Livewire::actingAs
        $this->actingAs($user);

        return [$user, $contract];
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `./vendor/bin/sail test --filter=DepositHoldFormTest`

Expected: FAIL — prefill still uses full `contract.deposit_amount`; complete UI missing.

- [ ] **Step 4: Update DepositHoldForm.php**

```php
<?php

namespace App\Livewire\Contracts;

use App\Actions\Contracts\RegisterDepositHoldAction;
use App\Models\Contract;
use App\Support\DepositBalanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

class DepositHoldForm extends Component
{
    public Contract $contract;

    public string $deposit_received_at = '';

    public string $deposit_amount = '';

    public ?string $deposit_notes = null;

    public function mount(Contract $contract, DepositBalanceService $depositBalanceService): void
    {
        $this->contract = $contract;
        $this->deposit_received_at = now('America/Tijuana')->toDateString();
        $this->deposit_amount = number_format(
            $depositBalanceService->remainingDepositHoldAmount($contract),
            2,
            '.',
            ''
        );
    }

    #[On('deposit-hold-registered')]
    public function onDepositHoldRegistered(): void
    {
        // Re-render; remaining/prefill refreshed in render().
    }

    public function registerDeposit(RegisterDepositHoldAction $action): void
    {
        if (! (auth()->user()?->can('charges.manage') ?? false)) {
            abort(403);
        }

        $validated = $this->validate([
            'deposit_received_at' => ['required', 'date'],
            'deposit_amount' => ['required', 'numeric', 'min:0.01'],
            'deposit_notes' => ['nullable', 'string', 'max:500'],
        ], [
            'deposit_received_at.required' => __('contracts.validation.deposit_received_required'),
            'deposit_received_at.date' => __('contracts.validation.deposit_received_invalid'),
            'deposit_amount.required' => __('contracts.validation.deposit_amount_required'),
            'deposit_amount.numeric' => __('contracts.validation.deposit_amount_numeric'),
            'deposit_amount.min' => __('contracts.validation.deposit_amount_min'),
            'deposit_notes.max' => __('contracts.validation.deposit_notes_max'),
        ]);

        try {
            $action->execute(
                contract: $this->contract,
                amount: (float) $validated['deposit_amount'],
                receivedAt: $validated['deposit_received_at'],
                notes: $validated['deposit_notes'] ?? null,
                userId: auth()->id(),
            );
        } catch (ValidationException $exception) {
            $message = $exception->errors()['month_close'][0]
                ?? $exception->errors()['deposit_amount'][0]
                ?? __('contracts.validation.deposit_failed');
            $this->addError('deposit_general', $message);

            return;
        }

        $this->reset('deposit_notes');
        session()->flash('success', __('contracts.flash.deposit_registered'));
        $this->dispatch('deposit-hold-registered');
    }

    public function render(DepositBalanceService $depositBalanceService): View
    {
        $contract = Contract::query()->findOrFail($this->contract->id);
        $registered = $depositBalanceService->registeredDepositHoldAmount($contract);
        $remaining = $depositBalanceService->remainingDepositHoldAmount($contract);

        if ($remaining > 0 && (float) $this->deposit_amount <= 0) {
            $this->deposit_amount = number_format($remaining, 2, '.', '');
        }

        return view('livewire.contracts.deposit-hold-form', [
            'contractDepositAmount' => (float) $contract->deposit_amount,
            'registeredDeposit' => $registered,
            'remainingDeposit' => $remaining,
        ]);
    }
}
```

After successful register, also refresh prefill to new remaining:

```php
$this->deposit_amount = number_format(
    app(DepositBalanceService::class)->remainingDepositHoldAmount($this->contract->fresh()),
    2,
    '.',
    ''
);
```

(Insert after flash / before or after dispatch.)

- [ ] **Step 5: Update Blade**

Replace `resources/views/livewire/contracts/deposit-hold-form.blade.php` with:

```blade
<x-ui.card>
    <h2 class="text-lg font-semibold text-slate-900">{{ __('contracts.deposit_received') }}</h2>
    <p class="mt-1 text-sm text-slate-600">
        {!! __('contracts.deposit_received_description', ['code' => '<code>DEPOSIT_HOLD</code>']) !!}
    </p>

    <div class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('contracts.deposit_contract_amount') }}</p>
            <p class="font-semibold text-slate-900">${{ number_format($contractDepositAmount, 2) }}</p>
        </div>
        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('contracts.deposit_registered') }}</p>
            <p class="font-semibold text-slate-900">${{ number_format($registeredDeposit, 2) }}</p>
        </div>
        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2">
            <p class="text-xs uppercase tracking-wide text-slate-500">{{ __('contracts.deposit_remaining') }}</p>
            <p class="font-semibold text-slate-900">${{ number_format($remainingDeposit, 2) }}</p>
        </div>
    </div>

    @if ($remainingDeposit <= 0)
        <div class="mt-4 rounded-md border border-emerald-200 bg-emerald-50 px-3 py-3 text-sm text-emerald-800">
            <p class="font-semibold">{{ __('contracts.deposit_complete_title') }}</p>
            <p class="mt-1">{{ __('contracts.deposit_complete_description', ['amount' => '$'.number_format($contractDepositAmount, 2)]) }}</p>
        </div>
    @else
        <form wire:submit="registerDeposit" class="mt-4 grid gap-4 md:grid-cols-3">
            @error('deposit_general')
                <div class="md:col-span-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {{ $message }}
                </div>
            @enderror

            <div>
                <x-ui.input :label="__('contracts.deposit_received_at').' *'" type="date" wire:model.blur="deposit_received_at" />
                @error('deposit_received_at') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input :label="__('common.amount').' *'" type="number" step="0.01" min="0.01" wire:model.blur="deposit_amount" />
                @error('deposit_amount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-ui.input :label="__('contracts.notes')" type="text" wire:model.blur="deposit_notes" />
                @error('deposit_notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-3 flex justify-end">
                <x-ui.button type="submit">
                    {{ __('contracts.register_deposit') }}
                </x-ui.button>
            </div>
        </form>
    @endif
</x-ui.card>
```

- [ ] **Step 6: Run Livewire tests**

Run: `./vendor/bin/sail test --filter=DepositHoldFormTest`

Expected: PASS

- [ ] **Step 7: Format**

Run: `./vendor/bin/sail pint --dirty`

---

### Task 4: Docs + full verification

**Files:**
- Modify: `docs/AI_ONBOARDING.md` (§4.3)

- [ ] **Step 1: Update onboarding**

In `docs/AI_ONBOARDING.md` under `### 4.3 Depósitos y finiquito`, after the `DEPOSIT_HOLD` bullet, add:

```markdown
- Tope de registro: la suma de cargos `DEPOSIT_HOLD` de un contrato no puede superar `contracts.deposit_amount` (parciales permitidos). Guard en [`RegisterDepositHoldAction`](../app/Actions/Contracts/RegisterDepositHoldAction.php); helpers en [`DepositBalanceService`](../app/Support/DepositBalanceService.php) (`registeredDepositHoldAmount` / `remainingDepositHoldAmount`). UI en detalle de contrato oculta el form al completar.
```

- [ ] **Step 2: Run full related suite**

Run:

```bash
./vendor/bin/sail test --filter=DepositHold
./vendor/bin/sail pint --dirty
```

Expected: all matching tests PASS; Pint clean.

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Cap `SUM(DEPOSIT_HOLD) <= deposit_amount` | Task 2 |
| Partials until complete | Task 2 |
| Reject overshoot with validation | Task 2 |
| Reject when complete | Task 2 |
| Helpers single source | Task 1 |
| UI summary + prefill remaining | Task 3 |
| Hide form when complete | Task 3 |
| Keep settlement / availableDeposit unchanged | Out of scope (no task) |
| Docs §4.3 | Task 4 |
| Idempotency same date+amount | Task 2 |
