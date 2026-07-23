# Rent charges 5 days before due — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Generar cargos `RENT` automáticamente 5 días antes del `due_date`, con scheduler diario (catch-up) y backfill manual por `--month`.

**Architecture:** Extender `GenerateMonthlyRentChargesAction` con `executeDueSoon()` que evalúa periodos candidatos (mes anterior/actual/siguiente) y crea solo si `asOf >= due_date - 5`. El comando sin `--month` usa ese modo; el scheduler pasa a diario. Create/activate de contrato y `--month` quedan sin cambio de semántica.

**Tech Stack:** Laravel 11, PHPUnit via Sail, CarbonImmutable, Artisan scheduler.

## Global Constraints

- Timezone operativa: `America/Tijuana`.
- Ventana fija: **5 días** antes de `due_date` (`asOf >= due_date->subDays(5)`).
- Idempotencia: 1 RENT por `(contract_id, period)`; conservar catch de unique race.
- Create/activate: `ensureCurrentMonthForContract` sigue generando mes corriente de inmediato.
- `--month=YYYY-MM`: backfill mes completo sin filtro de ventana.
- `charge_date`: inicio del periodo (`YYYY-MM-01`).
- Sail obligatorio: `./vendor/bin/sail test …` y `./vendor/bin/sail pint --dirty`.
- No commitear salvo que el usuario lo pida.

## File map

| File | Role |
|------|------|
| `app/Actions/Charges/GenerateMonthlyRentChargesAction.php` | Nuevo `executeDueSoon` + helpers de ventana/candidatos |
| `app/Console/Commands/GenerateRentChargesCommand.php` | Sin `--month` → due-soon; con `--month` → backfill |
| `routes/console.php` | Schedule diario `00:10` sin `--month` |
| `docs/AI_ONBOARDING.md` | Documentar regla y scheduler |
| `tests/Unit/Actions/GenerateMonthlyRentChargesActionTest.php` | Tests de ventana / catch-up / skip |
| `tests/Feature/Console/GenerateRentChargesCommandTest.php` | Tests modo default vs `--month` |

---

### Task 1: Unit tests for `executeDueSoon` (RED)

**Files:**
- Modify: `tests/Unit/Actions/GenerateMonthlyRentChargesActionTest.php`
- Test: same file

**Interfaces:**
- Consumes: existing `makeContractGraph()` helper (extend as needed)
- Produces: failing tests that call `GenerateMonthlyRentChargesAction::executeDueSoon(?CarbonImmutable $asOf = null, ?int $organizationId = null): array{created:int, skipped:int, as_of:string}`

- [ ] **Step 1: Add failing unit tests**

Append these tests (reuse / extend `makeContractGraph` so contracts can override `due_day`, `starts_at`, and avoid relying on create-hook side effects for the period under test — set `CarbonImmutable::setTestNow` **before** creating contracts when the create hook would create an unwanted current-month RENT, or create as `ended` then activate only when needed).

```php
public function test_due_soon_creates_next_month_rent_five_days_before_due_day_one(): void
{
    // asOf 2026-07-27, due_day=1 → period 2026-08
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-27 00:10:00', 'America/Tijuana'));

    [$organization, $contract] = $this->makeContractGraph([
        'due_day' => 1,
        'grace_days' => 5,
        'starts_at' => '2026-01-01',
        'rent_amount' => 1000,
    ]);

    $result = app(GenerateMonthlyRentChargesAction::class)
        ->executeDueSoon(CarbonImmutable::parse('2026-07-27', 'America/Tijuana')->startOfDay(), $organization->id);

    $this->assertSame(1, $result['created']);
    $this->assertSame('2026-07-27', $result['as_of']);

    $this->assertDatabaseHas('charges', [
        'contract_id' => $contract->id,
        'type' => Charge::TYPE_RENT,
        'period' => '2026-08',
        'charge_date' => '2026-08-01',
        'due_date' => '2026-08-01',
        'amount' => '1000.00',
    ]);

    CarbonImmutable::setTestNow();
}

public function test_due_soon_does_not_create_before_window(): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-09 12:00:00', 'America/Tijuana'));

    [$organization, $contract] = $this->makeContractGraph([
        'due_day' => 15,
        'starts_at' => '2026-01-01',
    ]);

    // Remove any RENT created by Contract::created for 2026-08 if present,
    // or create contract as ended and activate after clearing — prefer:
    // makeContractGraph creates active → hook may create Aug rent on Aug 9
    // because ensureCurrentMonthForContract ignores the 5-day window (option B).
    // For this test we only assert executeDueSoon does not create *additional*
    // period candidates outside window. Use due_day=15 and assert period 2026-09
    // is NOT created on Aug 9 (next month due Sep 15 opens Aug 10? Sep 15 - 5 = Sep 10).
    // Better focused assertion:

    $asOf = CarbonImmutable::parse('2026-08-09', 'America/Tijuana')->startOfDay();
    $before = Charge::query()->withoutOrganizationScope()
        ->where('contract_id', $contract->id)->where('type', Charge::TYPE_RENT)->count();

    $result = app(GenerateMonthlyRentChargesAction::class)
        ->executeDueSoon($asOf, $organization->id);

    $after = Charge::query()->withoutOrganizationScope()
        ->where('contract_id', $contract->id)->where('type', Charge::TYPE_RENT)->count();

    $this->assertSame(0, $result['created']);
    $this->assertSame($before, $after);
    $this->assertSame(0, Charge::query()->withoutOrganizationScope()
        ->where('contract_id', $contract->id)
        ->where('period', '2026-09')
        ->count());

    CarbonImmutable::setTestNow();
}

public function test_due_soon_creates_on_window_open_for_due_day_fifteen(): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-10 00:10:00', 'America/Tijuana'));

    [$organization, $contract] = $this->makeContractGraph([
        'due_day' => 15,
        'grace_days' => 2,
        'starts_at' => '2026-01-01',
        'rent_amount' => 8500,
    ]);

    // Create-hook already made 2026-08 RENT (option B). Delete it to isolate due-soon,
    // OR assert created=0/skipped if already exists — prefer delete for clarity:
    Charge::query()->withoutOrganizationScope()
        ->where('contract_id', $contract->id)
        ->where('type', Charge::TYPE_RENT)
        ->where('period', '2026-08')
        ->delete();

    $result = app(GenerateMonthlyRentChargesAction::class)
        ->executeDueSoon(CarbonImmutable::parse('2026-08-10', 'America/Tijuana')->startOfDay(), $organization->id);

    $this->assertSame(1, $result['created']);
    $this->assertDatabaseHas('charges', [
        'contract_id' => $contract->id,
        'period' => '2026-08',
        'due_date' => '2026-08-15',
        'grace_until' => '2026-08-17',
        'charge_date' => '2026-08-01',
    ]);

    CarbonImmutable::setTestNow();
}

public function test_due_soon_catch_up_after_window_still_creates(): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-01 00:10:00', 'America/Tijuana'));

    [$organization, $contract] = $this->makeContractGraph([
        'due_day' => 1,
        'starts_at' => '2026-01-01',
    ]);

    Charge::query()->withoutOrganizationScope()
        ->where('contract_id', $contract->id)
        ->where('type', Charge::TYPE_RENT)
        ->where('period', '2026-08')
        ->delete();

    $result = app(GenerateMonthlyRentChargesAction::class)
        ->executeDueSoon(CarbonImmutable::parse('2026-08-01', 'America/Tijuana')->startOfDay(), $organization->id);

    $this->assertSame(1, $result['created']);
    $this->assertDatabaseHas('charges', [
        'contract_id' => $contract->id,
        'period' => '2026-08',
        'due_date' => '2026-08-01',
    ]);

    CarbonImmutable::setTestNow();
}

public function test_due_soon_skips_when_rent_already_exists(): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-10 00:10:00', 'America/Tijuana'));

    [$organization, $contract] = $this->makeContractGraph([
        'due_day' => 15,
        'starts_at' => '2026-01-01',
    ]);

    // Hook already created 2026-08
    $result = app(GenerateMonthlyRentChargesAction::class)
        ->executeDueSoon(CarbonImmutable::parse('2026-08-10', 'America/Tijuana')->startOfDay(), $organization->id);

    $this->assertSame(0, $result['created']);
    $this->assertGreaterThanOrEqual(1, $result['skipped']);
    $this->assertSame(1, Charge::query()->withoutOrganizationScope()
        ->where('contract_id', $contract->id)
        ->where('period', '2026-08')
        ->where('type', Charge::TYPE_RENT)
        ->count());

    CarbonImmutable::setTestNow();
}

public function test_due_soon_skips_period_when_contract_not_yet_started(): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-27 00:10:00', 'America/Tijuana'));

    [$organization, $contract] = $this->makeContractGraph([
        'due_day' => 1,
        'starts_at' => '2026-09-01', // no vigente en periodo 2026-08
    ]);

    Charge::query()->withoutOrganizationScope()
        ->where('contract_id', $contract->id)
        ->where('type', Charge::TYPE_RENT)
        ->delete();

    $result = app(GenerateMonthlyRentChargesAction::class)
        ->executeDueSoon(CarbonImmutable::parse('2026-07-27', 'America/Tijuana')->startOfDay(), $organization->id);

    $this->assertSame(0, Charge::query()->withoutOrganizationScope()
        ->where('contract_id', $contract->id)
        ->where('period', '2026-08')
        ->count());

    CarbonImmutable::setTestNow();
}
```

Also refactor `makeContractGraph` to accept an optional overrides array:

```php
/**
 * @param  array<string, mixed>  $contractOverrides
 * @return array{0: Organization, 1: Contract, 2: Unit}
 */
private function makeContractGraph(array $contractOverrides = []): array
{
    // ... existing factories ...
    $contract = Contract::factory()->create(array_merge([
        'organization_id' => $organization->id,
        'unit_id' => $unit->id,
        'tenant_id' => $tenant->id,
        'status' => Contract::STATUS_ACTIVE,
        'rent_amount' => 1000,
        'due_day' => 1,
        'grace_days' => 5,
        'starts_at' => '2026-01-01',
        'ends_at' => null,
    ], $contractOverrides));

    return [$organization, $contract, $unit];
}
```

Add imports: `use Carbon\CarbonImmutable;`

- [ ] **Step 2: Run tests to verify they fail**

```bash
./vendor/bin/sail test --filter=GenerateMonthlyRentChargesActionTest
```

Expected: FAIL — `executeDueSoon` does not exist (or similar).

- [ ] **Step 3: Commit only if user asks** (skip by default)

---

### Task 2: Implement `executeDueSoon` (GREEN)

**Files:**
- Modify: `app/Actions/Charges/GenerateMonthlyRentChargesAction.php`

**Interfaces:**
- Consumes: existing `createRentChargeForContractPeriod`, `buildDueDate`
- Produces:
  - `executeDueSoon(?CarbonImmutable $asOf = null, ?int $organizationId = null): array{created:int, skipped:int, as_of:string}`
  - private helpers as below

- [ ] **Step 1: Add constant + `executeDueSoon`**

```php
private const DUE_SOON_DAYS = 5;

/**
 * @return array{created:int, skipped:int, as_of:string}
 */
public function executeDueSoon(?CarbonImmutable $asOf = null, ?int $organizationId = null): array
{
    $asOf = ($asOf ?? CarbonImmutable::now('America/Tijuana'))->startOfDay();

    $created = 0;
    $skipped = 0;

    $candidatePeriods = [
        $asOf->subMonthNoOverflow()->format('Y-m'),
        $asOf->format('Y-m'),
        $asOf->addMonthNoOverflow()->format('Y-m'),
    ];

    $contractsQuery = Contract::query()
        ->withoutOrganizationScope()
        ->where('status', Contract::STATUS_ACTIVE);

    if (is_int($organizationId) && $organizationId > 0) {
        $contractsQuery->where('organization_id', $organizationId);
    }

    $contractsQuery->orderBy('id')
        ->chunkById(200, function ($contracts) use (&$created, &$skipped, $asOf, $candidatePeriods): void {
            foreach ($contracts as $contract) {
                foreach ($candidatePeriods as $month) {
                    $periodStart = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
                    $periodEnd = $periodStart->endOfMonth();

                    if (! $this->contractCoversPeriod($contract, $periodStart, $periodEnd)) {
                        continue;
                    }

                    $dueDate = $this->buildDueDate($periodStart, (int) $contract->due_day);
                    $windowOpens = $dueDate->subDays(self::DUE_SOON_DAYS);

                    if ($asOf->lt($windowOpens)) {
                        continue;
                    }

                    $charge = $this->createRentChargeForContractPeriod($contract, $periodStart);

                    if ($charge->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $skipped++;
                    }
                }
            }
        });

    return [
        'created' => $created,
        'skipped' => $skipped,
        'as_of' => $asOf->toDateString(),
    ];
}

private function contractCoversPeriod(
    Contract $contract,
    CarbonImmutable $periodStart,
    CarbonImmutable $periodEnd
): bool {
    if ($contract->starts_at !== null
        && $contract->starts_at->toDateString() > $periodEnd->toDateString()
    ) {
        return false;
    }

    if ($contract->ends_at !== null
        && $contract->ends_at->toDateString() < $periodStart->toDateString()
    ) {
        return false;
    }

    return true;
}
```

Notes for implementer:
- Reuse the same vigencia filter shape as `executeForOrganization` (starts_at <= periodEnd AND (ends_at null OR ends_at >= periodStart)).
- `wasRecentlyCreated` is false when the charge was fetched existing — counts as skipped.
- Month closed: `Charge` model already calls `MonthCloseGuard::assertChargeMonthOpen` on create; let it throw or catch only if existing code paths already handle it — do **not** swallow silently unless tests require skip. Prefer filtering with `MonthCloseGuard::isMonthClosed` before create to skip closed periods (align with syncOpenRentSchedule pattern):

```php
if (MonthCloseGuard::isMonthClosed((int) $contract->organization_id, $month)) {
    continue;
}
```

- [ ] **Step 2: Run unit tests**

```bash
./vendor/bin/sail test --filter=GenerateMonthlyRentChargesActionTest
```

Expected: PASS (fix test setup if create-hook interferes — delete period under test as shown in Task 1).

- [ ] **Step 3: Pint**

```bash
./vendor/bin/sail pint --dirty
```

---

### Task 3: Command default mode due-soon (RED → GREEN)

**Files:**
- Modify: `app/Console/Commands/GenerateRentChargesCommand.php`
- Modify: `tests/Feature/Console/GenerateRentChargesCommandTest.php`
- Keep working: `tests/Feature/Console/CommandLockingTest.php` (still passes `--month`)

**Interfaces:**
- Consumes: `executeDueSoon()`, `execute(string $month)`
- Produces: CLI behavior documented below

- [ ] **Step 1: Add failing feature tests**

```php
public function test_without_month_runs_due_soon_mode(): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-27 00:10:00', 'America/Tijuana'));

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
        'status' => Contract::STATUS_ACTIVE,
        'starts_at' => '2026-01-01',
        'ends_at' => null,
        'due_day' => 1,
        'grace_days' => 5,
        'rent_amount' => 12000,
    ]);

    Charge::query()->withoutOrganizationScope()
        ->where('contract_id', $contract->id)
        ->where('type', Charge::TYPE_RENT)
        ->delete();

    $this->artisan('inmo:generate-rent')
        ->assertExitCode(0)
        ->expectsOutputToContain('Cargos creados: 1');

    $this->assertDatabaseHas('charges', [
        'contract_id' => $contract->id,
        'type' => Charge::TYPE_RENT,
        'period' => '2026-08',
        'due_date' => '2026-08-01',
    ]);

    CarbonImmutable::setTestNow();
}

public function test_without_month_is_idempotent(): void
{
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-07-27 00:10:00', 'America/Tijuana'));

    // same setup as above (extract private helper if useful)
    // ...
    $this->artisan('inmo:generate-rent')->assertExitCode(0);
    $this->artisan('inmo:generate-rent')
        ->assertExitCode(0)
        ->expectsOutputToContain('Cargos creados: 0');

    CarbonImmutable::setTestNow();
}
```

Keep existing `--month` tests unchanged (still require `--month`).

- [ ] **Step 2: Run to verify fail**

```bash
./vendor/bin/sail test --filter=GenerateRentChargesCommandTest
```

Expected: FAIL — missing `--month` currently returns FAILURE.

- [ ] **Step 3: Update command**

```php
protected $signature = 'inmo:generate-rent {--month= : Mes objetivo YYYY-MM (backfill forzado; omitir para modo due-soon diario)}';

protected $description = 'Genera cargos de renta: sin --month usa ventana due-soon (5 días); con --month backfill del mes';

public function handle(): int
{
    $lock = Cache::lock(self::LOCK_KEY, self::LOCK_TTL_SECONDS);

    if (! $lock->get()) {
        Log::info('skipped (locked)', [
            'command' => $this->getName(),
            'lock_key' => self::LOCK_KEY,
        ]);
        $this->line('skipped (locked)');

        return self::SUCCESS;
    }

    try {
        $month = (string) $this->option('month');

        if ($month === '') {
            $result = $this->action->executeDueSoon();

            $this->info("Generación due-soon completada para {$result['as_of']}.");
            $this->line("Cargos creados: {$result['created']}");
            $this->line("Cargos omitidos (ya existentes): {$result['skipped']}");

            return self::SUCCESS;
        }

        if (! $this->isValidMonth($month)) {
            $this->error('Debes enviar --month con formato YYYY-MM. Ejemplo: --month=2026-03');

            return self::FAILURE;
        }

        $result = $this->action->execute($month);

        $this->info("Generación de rentas completada para {$result['month']}.");
        $this->line("Cargos creados: {$result['created']}");
        $this->line("Cargos omitidos (ya existentes): {$result['skipped']}");

        return self::SUCCESS;
    } finally {
        $lock->release();
    }
}
```

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/sail test --filter=GenerateRentChargesCommandTest
./vendor/bin/sail test --filter=CommandLockingTest
```

Expected: PASS.

---

### Task 4: Scheduler diario + docs

**Files:**
- Modify: `routes/console.php`
- Modify: `docs/AI_ONBOARDING.md` (scheduler section + TL;DR if needed)

- [ ] **Step 1: Change schedule**

Replace:

```php
Schedule::command('inmo:generate-rent --month='.now('America/Tijuana')->format('Y-m'))
    ->monthlyOn(1, '00:10')
    ->timezone('America/Tijuana')
    ->withoutOverlapping();
```

With:

```php
Schedule::command('inmo:generate-rent')
    ->dailyAt('00:10')
    ->timezone('America/Tijuana')
    ->withoutOverlapping();
```

- [ ] **Step 2: Update `docs/AI_ONBOARDING.md`**

In TL;DR / scheduler section, replace the “día 1” wording with:

- `inmo:generate-rent` (sin `--month`): diario `00:10` America/Tijuana — genera RENT cuando `hoy >= due_date - 5 días` (catch-up incluido; candidatos mes anterior/actual/siguiente).
- `inmo:generate-rent --month=YYYY-MM`: backfill forzado del mes completo.
- Al crear/activar contrato: sigue generando RENT del mes corriente de inmediato.

- [ ] **Step 3: Smoke related tests + pint**

```bash
./vendor/bin/sail test --filter=GenerateMonthlyRentCharges
./vendor/bin/sail test --filter=GenerateRentChargesCommandTest
./vendor/bin/sail test --filter=ContractRentAutogenerationTest
./vendor/bin/sail pint --dirty
```

Expected: all PASS.

---

### Task 5: Regression check (no code unless fail)

**Files:** none expected

- [ ] **Step 1: Confirm create/activate unchanged**

```bash
./vendor/bin/sail test --filter=ContractRentAutogenerationTest
```

Expected: PASS without code changes (option B).

- [ ] **Step 2: Confirm Dashboard/palette still use `executeForOrganization`**

No code change. Quick grep verification:

- `app/Livewire/Dashboard/Index.php` → `executeForOrganization`
- `app/Livewire/Search/CommandPalette.php` → `executeForOrganization`

---

## Spec coverage checklist

| Spec requirement | Task |
|------------------|------|
| Ventana 5 días | Task 1–2 |
| Cruce de mes (27-jul → ago) | Task 1–2 |
| Catch-up diario | Task 1–2, 4 |
| Candidatos prev/current/next | Task 2 |
| Create/activate inmediato | Task 5 (no change) |
| `--month` backfill | Task 3 |
| Scheduler diario | Task 4 |
| `charge_date` = period start | Task 1 asserts |
| Docs AI_ONBOARDING | Task 4 |
| Idempotencia / unique race | existing + Task 1 skip test |

## Out of scope (do not implement)

- Notificaciones a inquilinos
- Cambiar `charge_date` a fecha de generación
- Backfill histórico masivo más allá de prev/current/next
- Cambiar semántica create/activate a opción C
