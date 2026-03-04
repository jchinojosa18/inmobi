# AI Onboarding (Estado Real del Repo)

## 1) TL;DR
- `inmo-admin` es un SaaS Laravel 11 + Livewire para administración inmobiliaria multi-tenant por `organization_id`.
- El flujo financiero está basado en ledger: `Charge` + `Payment` + `PaymentAllocation`.
- La fuente de verdad de ingresos operativos son las allocations (no `payments.amount` bruto).
- Hay motor de multas diarias compuestas (`inmo:penalties:run`) con idempotencia fuerte.
- Hay generación de rentas mensuales (`inmo:generate-rent`) al crear/activar contrato y por comando/scheduler.
- Hay depósitos/finiquito (`DEPOSIT_HOLD`, `MOVEOUT`, `DEPOSIT_APPLY`) con PDF de finiquito.
- Hay cierre mensual con snapshot y bloqueo de movimientos en meses cerrados.
- Hay configuración por organización (`/settings`) para folios, plantillas y categorías de egreso.
- Hay panel operativo (`/dashboard`, `/cobranza`) y panel técnico admin (`/admin/system`).
- Hay smoke test E2E (`inmo:smoke`) y CI con tests + Pint.

## 2) Cómo correr local (Sail)
- Levantar servicios:
```bash
./vendor/bin/sail up -d
```
- Instalar dependencias:
```bash
./vendor/bin/sail composer install
./vendor/bin/sail npm install
```
- Migrar y seed base:
```bash
./vendor/bin/sail artisan migrate
./vendor/bin/sail artisan db:seed
```
- Seed demo (smoke):
```bash
./vendor/bin/sail artisan db:seed --class=DemoDataSeeder
```
- Frontend:
```bash
./vendor/bin/sail npm run dev
```
- Scheduler/queue en dev:
```bash
./vendor/bin/sail artisan schedule:work
./vendor/bin/sail artisan queue:work redis --queue=default --tries=3
```
- Smoke:
```bash
./vendor/bin/sail artisan inmo:smoke --date=2026-03-10
```

Puertos por defecto (ver [`compose.yaml`](../compose.yaml)):
- App: `http://localhost` (`APP_PORT=80`)
- Vite: `http://localhost:5173`
- Mailpit SMTP: `localhost:1025`
- Mailpit UI: `http://localhost:8025`
- MySQL: `localhost:3306`
- Redis: `localhost:6379`

Credenciales de prueba comunes:
- Seed base (`DatabaseSeeder`): `test@example.com` / `password`
- Seed demo smoke (`DemoDataSeeder`): `admin-smoke@inmo.test` / `password`

## 3) Mapa mental del dominio
```text
Organization
 ├─ Users
 ├─ Properties
 │   └─ Units
 │      ├─ Contracts
 │      │   ├─ Charges
 │      │   ├─ Payments
 │      │   │   └─ PaymentAllocations -> Charges
 │      │   └─ CreditBalance
 │      └─ Expenses
 ├─ Tenants -> Contracts
 ├─ MonthCloses (snapshot)
 ├─ ExpenseCategories
 └─ OrganizationSetting

Documents (morph):
 - Contract / Payment / Expense / Unit / Charge (en finiquitos)
```

Relaciones clave en modelos:
- [`Contract`](../app/Models/Contract.php)
- [`Charge`](../app/Models/Charge.php)
- [`Payment`](../app/Models/Payment.php)
- [`PaymentAllocation`](../app/Models/PaymentAllocation.php)
- [`Expense`](../app/Models/Expense.php)
- [`CreditBalance`](../app/Models/CreditBalance.php)

## 4) Reglas de negocio INNEGOCIABLES
### 4.1 Multa diaria compuesta
- Acción: [`RunDailyPenaltiesAction`](../app/Actions/Penalties/RunDailyPenaltiesAction.php)
- Comando: `inmo:penalties:run`
- Regla: 1 multa por contrato por día (`contract_id + penalty_date + type`) con constraint único `charges_contract_penalty_type_unique`.
- Base incluye saldo vencido total (incluye multas previas), menos allocations y menos saldo a favor.
- Corte: `D-1 23:59:59` en `America/Tijuana`; se convierte a timezone de almacenamiento para comparar `payments.paid_at`.
- Idempotencia: chequeo previo + captura de duplicate key.

Ejemplo: si el 2026-03-04 hubo pago parcial al mediodía, impacta base para multa del 2026-03-05.

### 4.2 Pagos y allocations
- Acción: [`ApplyPaymentAction`](../app/Actions/Payments/ApplyPaymentAction.php)
- Prioridad:
1. RENT más antiguo
2. SERVICE reembolsable (`meta.refundable=true`)
3. PENALTY
4. Resto
- Soporta parciales y adelantados.
- Idempotencia: si el pago ya fue procesado o tiene allocations, no reaplica.
- Saldo a favor: tabla [`credit_balances`](../app/Models/CreditBalance.php), no cargo negativo.

### 4.3 Depósitos y finiquito
- Tipos: `DEPOSIT_HOLD`, `MOVEOUT`, `DEPOSIT_APPLY` (en [`Charge`](../app/Models/Charge.php)).
- `DEPOSIT_HOLD` se puede pagar con flujo normal para evidencia/recibo.
- Finiquito: [`ProcessContractSettlementAction`](../app/Actions/Contracts/ProcessContractSettlementAction.php)
  - crea `MOVEOUT`
  - aplica depósito con `DEPOSIT_APPLY` (negativo)
  - si sobra depósito crea `Expense` categoría `Refund deposit`
  - termina contrato (`status=ended`)

### 4.4 Reportes por allocations (fuente de verdad)
- Servicio: [`OperatingIncomeService`](../app/Support/OperatingIncomeService.php)
- Ingreso operativo = sum de `payment_allocations.amount` en tipos operativos.
- Excluye depósitos (`DEPOSIT_HOLD`, `DEPOSIT_APPLY`).
- Reporte UI: [`/reports/flow`](../app/Livewire/Reports/CashFlow.php), export CSV controller.

### 4.5 Cierre mensual + ajustes
- Guard: [`MonthCloseGuard`](../app/Support/MonthCloseGuard.php)
- Bloquea create/update/delete en mes cerrado para `Payment`, `Expense`, `Charge`, `Document` (según fecha asociada).
- Excepción permitida: `ADJUSTMENT` con `meta.reason` obligatorio.
- Snapshot al cerrar: [`BuildMonthCloseSnapshotAction`](../app/Actions/MonthCloses/BuildMonthCloseSnapshotAction.php).

## 5) Dónde vive la lógica (guía de navegación)
### `app/Actions`
- Casos de uso transaccionales de negocio.
- Toca aquí si cambias reglas de cálculo/aplicación.

Archivos clave:
- [`app/Actions/Payments/ApplyPaymentAction.php`](../app/Actions/Payments/ApplyPaymentAction.php): motor de asignación de pagos.
- [`app/Actions/Penalties/RunDailyPenaltiesAction.php`](../app/Actions/Penalties/RunDailyPenaltiesAction.php): multas diarias compuestas/backfill.
- [`app/Actions/Charges/GenerateMonthlyRentChargesAction.php`](../app/Actions/Charges/GenerateMonthlyRentChargesAction.php): cargos RENT mensuales idempotentes.
- [`app/Actions/MonthCloses/CloseMonthAction.php`](../app/Actions/MonthCloses/CloseMonthAction.php): cierre mensual.
- [`app/Actions/Contracts/ProcessContractSettlementAction.php`](../app/Actions/Contracts/ProcessContractSettlementAction.php): finiquito.

### `app/Support`
- Servicios transversales y políticas técnicas.
- Toca aquí si cambias reglas compartidas por varios módulos.

Archivos clave:
- [`app/Support/OperatingIncomeService.php`](../app/Support/OperatingIncomeService.php): ingresos operativos por allocations.
- [`app/Support/MonthCloseGuard.php`](../app/Support/MonthCloseGuard.php): enforcement de meses cerrados.
- [`app/Support/DepositBalanceService.php`](../app/Support/DepositBalanceService.php): saldo depósito/aplicado/devuelto.
- [`app/Support/PaymentReceiptDataBuilder.php`](../app/Support/PaymentReceiptDataBuilder.php): payload de recibo.
- [`app/Support/OrganizationSettingsService.php`](../app/Support/OrganizationSettingsService.php): defaults/render de settings por org.
- [`app/Support/TenantContext.php`](../app/Support/TenantContext.php): contexto tenant para scope global.

### Livewire (`app/Livewire`)
- Orquesta UI + queries de listado; no debe redefinir reglas core de negocio.
- Si una regla afecta consistencia financiera, muévela a Action/Support.

### Models (`app/Models`)
- Tipos/constantes, relaciones, hooks de guard (month close, tenant scope).
- Multi-tenant scope global vía `OrganizationScopedModel` + `BelongsToOrganization`.

## 6) Scheduler / Jobs / comandos
Definición en [`routes/console.php`](../routes/console.php):
- Heartbeat scheduler: cada minuto.
- `inmo:daily`: diario `00:15` America/Tijuana.
- `inmo:penalties:run`: diario `00:05` America/Tijuana.
- `inmo:generate-rent --month=YYYY-MM`: día 1 a `00:10` America/Tijuana.
- `inmo:backup`: diario `03:10` America/Tijuana.

Locks/mutex (Redis/Cache lock, salida `0` cuando ya está tomado):
- [`GenerateRentChargesCommand`](../app/Console/Commands/GenerateRentChargesCommand.php)
- [`RunPenaltiesCommand`](../app/Console/Commands/RunPenaltiesCommand.php)
- [`InmoDailyCommand`](../app/Console/Commands/InmoDailyCommand.php)
- [`InmoBackupCommand`](../app/Console/Commands/InmoBackupCommand.php)

Comandos manuales útiles:
```bash
./vendor/bin/sail artisan inmo:generate-rent --month=2026-03
./vendor/bin/sail artisan inmo:penalties:run --date=2026-03-10 --from-date=2026-03-05
./vendor/bin/sail artisan inmo:daily
./vendor/bin/sail artisan inmo:backup --keep=14
./vendor/bin/sail artisan inmo:smoke --date=2026-03-10
```

Nota:
- `inmo:preflight` no existe actualmente en `app/Console/Commands`.

## 7) UI / rutas principales
Definidas en [`routes/web.php`](../routes/web.php) (todas auth salvo donde se indique):
- `/dashboard` -> [`App\Livewire\Dashboard\Index`](../app/Livewire/Dashboard/Index.php)
- `/properties` -> [`Properties\Index`](../app/Livewire/Properties/Index.php)
- `/properties/{property}/units` -> [`Units\Index`](../app/Livewire/Units/Index.php) (redirige a casa si standalone)
- `/houses/create` -> [`Houses\Create`](../app/Livewire/Houses/Create.php)
- `/houses/{property}` -> [`Houses\Show`](../app/Livewire/Houses/Show.php)
- `/tenants` -> [`Tenants\Index`](../app/Livewire/Tenants/Index.php)
- `/contracts` -> [`Contracts\Index`](../app/Livewire/Contracts/Index.php)
- `/contracts/create|{contract}/edit` -> [`Contracts\Form`](../app/Livewire/Contracts/Form.php)
- `/contracts/{contract}` -> [`Contracts\Show`](../app/Livewire/Contracts/Show.php)
- `/contracts/{contract}/payments/create` -> [`Payments\Create`](../app/Livewire/Payments/Create.php)
- `/payments/{payment}` -> [`Payments\Show`](../app/Livewire/Payments/Show.php)
- `/cobranza` -> [`Cobranza\Index`](../app/Livewire/Cobranza/Index.php)
- `/expenses` -> [`Expenses\Index`](../app/Livewire/Expenses/Index.php)
- `/reports/flow` -> [`Reports\CashFlow`](../app/Livewire/Reports/CashFlow.php)
- `/month-closes` -> [`MonthCloses\Index`](../app/Livewire/MonthCloses/Index.php)
- `/settings` -> [`Settings\Index`](../app/Livewire/Settings/Index.php)
- `/admin/system` (Admin) -> [`Admin\SystemStatus`](../app/Livewire/Admin/SystemStatus.php)
- `/admin/health` (role:Admin) JSON health simple
- `/receipts/{paymentId}/shared.pdf` firmado (sin auth, con `signed`)

Navegación principal está en [`resources/views/layouts/app.blade.php`](../resources/views/layouts/app.blade.php).

## 8) Testing y CI
### Comandos
```bash
./vendor/bin/sail test
./vendor/bin/sail pint --test
```

### CI
- Workflow: [`.github/workflows/ci.yml`](../.github/workflows/ci.yml)
- PHP 8.3, `composer test`, `composer pint:test`.

### Nota crítica Vite
- En tests se ejecuta `Vite::fake()` para no depender de `public/build/manifest.json`.
- Implementado en [`tests/TestCase.php`](../tests/TestCase.php).

### DB en tests
- `phpunit.xml` usa sqlite in-memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`).
- Ojo: hay SQL condicional mysql/sqlite en listados Livewire (`Contracts\Index`, `Cobranza\Index`, `Dashboard\Index`).

Pitfalls de testing:
- Si agregas consultas con funciones SQL no compatibles con sqlite, romperás CI.
- Mantén idempotencia validada por tests en comandos de rentas/multas.

## 9) Producción (Hostinger)
Checklist corto:
- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` correcto.
- `php artisan storage:link`.
- `php artisan migrate --force`.
- Build frontend y publicar `public/build`.
- Cron scheduler: `* * * * * php artisan schedule:run`.
- Worker queue (`queue:work redis ...`) si el entorno lo soporta.
- Permisos de escritura: `storage/` y `bootstrap/cache/`.

Referencia:
- [`docs/DEPLOY_HOSTINGER.md`](./DEPLOY_HOSTINGER.md)

System status/heartbeats:
- UI: `/admin/system`
- Heartbeats en tabla `system_heartbeats` (scheduler, queue_worker, backup).

## 10) No rompas esto (pitfalls comunes)
- Multi-tenant: nunca hagas queries de dominio sin `organization_id` (o sin scope explícito).
- Evita N+1 en listados grandes (`contracts`, `cobranza`, `dashboard`) y respeta subqueries agregadas.
- Idempotencia:
  - rentas mensuales: no duplicar por contrato/mes.
  - multas: no duplicar por contrato/día (`penalty_date`).
- Timezone/cutoff: multas usan America/Tijuana + comparación datetime completa (no simplificar a `whereDate` para `paid_at`).
- No mezclar depósito con ingreso operativo en reportes/snapshots.
- Mes cerrado: no bypass de `MonthCloseGuard`; usa `ADJUSTMENT` con `meta.reason`.

## 11) Roadmap sugerido (sin compromiso)
- Importador CSV para altas masivas de propiedades/unidades/contratos.
- Portal de inquilino (estado de cuenta + pago + descarga de recibos).
- Integración WhatsApp API (hoy MVP es deep link).
- Plantillas PDF versionadas por organización (branding/legal).
- Alertas automáticas de cobranza (email/WhatsApp) con colas.
- Tablero de aging de cartera y KPIs de cobranza.
- Endpoints API para app móvil/admin externo.
- Hardening de observabilidad (métricas y tracing de comandos nocturnos).

## Inconsistencias detectadas
- [`README.md`](../README.md): indica `inmo:daily` a las `00:05`; código actual en [`routes/console.php`](../routes/console.php) lo agenda a `00:15`.
  - Sugerencia: actualizar README para evitar operación fuera de horario real.
- [`docs/DAILY_OPERATIONS.md`](./DAILY_OPERATIONS.md): también dice `00:05` y que no hay lógica de multas/cierres reales.
  - Sugerencia: actualizar porque hoy sí existe motor real de multas y flujo de cierres.
- [`docs/PDF_GENERATION.md`](./PDF_GENERATION.md): indica “infraestructura-only” sin lógica real.
  - Sugerencia: reflejar que ya existen recibo/finiquito PDF reales (`PaymentReceiptPdfController`, `ContractSettlementPdfController`).
- [`docs/ARCHITECTURE.md`](./ARCHITECTURE.md): contiene partes históricas “antes de implementar código” y naming genérico que ya no representa al 100% la estructura real.
  - Sugerencia: separar “target architecture” vs “implemented architecture”.
