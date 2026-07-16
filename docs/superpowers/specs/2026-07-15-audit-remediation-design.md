# Remediation Audit 2026-07-15 — Design Spec

**Date:** 2026-07-15  
**Status:** Approved (product: credit option B)  
**Source:** Architecture/quality audit canvas + remediation order  
**Related:** `docs/AI_ONBOARDING.md`, `docs/ARCHITECTURE.md`

## Goal

Cerrar los hallazgos Critical/High (y Medium de seguridad) del audit en el orden acordado, sin refactor cosmético fuera de alcance. El crédito a favor debe **consumirse automáticamente** ante cargos pendientes (opción B): al pagar, al generar rentas/multas y al finiquitar.

## Out of Scope

- Larastan / PHPStan en CI (pase aparte)
- Cambiar prioridad de allocations (RENT → SERVICE refundable → PENALTY → resto)
- UI manual “Aplicar crédito”
- Migrar documentos existentes de `public` a privado en producción (solo default + ruta de descarga + docs; ops migra disco)
- Policies Laravel nuevas (seguir Spatie + `can()`)
- Redesign visual / i18n de pantallas operativas

---

## 1. Crédito — `ApplyCreditBalanceAction`

### Comportamiento

Nueva action: `app/Actions/Payments/ApplyCreditBalanceAction.php`.

1. Lock `Contract` + `CreditBalance` (`lockForUpdate`).
2. Si `balance <= 0` o no hay cargos pendientes aplicables → no-op (resultado `applied=0`).
3. Crear `Payment` interno:
   - `amount` = monto que se va a aplicar (min(credit, pending total applicable))
   - `method` = `CREDIT` (nueva constante `Payment::METHOD_CREDIT`)
   - `paid_at` = `now()` (timezone app). Respeta `MonthCloseGuard`: si el mes de `paid_at` está cerrado, la action **falla** (no inventar fechas). Los schedulers de renta/multa corren en meses operativos abiertos; si un backfill choca con mes cerrado, el operador reabre o aplica crédito manualmente vía un pago en mes abierto.
   - `receipt_folio` = `null` (sin folio de recibo)
   - `meta`: `source=credit_application`; marcar `allocation_processed=true` y `credited_amount=0` tras aplicar
4. Extraer la prioridad de cargos a un helper compartido (p.ej. `App\Support\ChargeAllocationPrioritizer` o método estático usado por `ApplyPaymentAction` y esta action) para no divergir.
5. Decrementar `credit_balances.balance` por el monto allocated.
6. **No** registrar overflow de este payment como crédito nuevo (el amount del payment CREDIT = exactamente lo allocated).
7. Ingresos operativos: siguen siendo suma de **allocations** por tipo de charge (`OperatingIncomeService`). El payment `CREDIT` no es cash; no cambia esa regla.

### Call sites (opción B)

| Dónde | Cuándo |
|-------|--------|
| `ApplyPaymentAction` | **Antes** de asignar el pago en efectivo (crédito primero) |
| `GenerateMonthlyRentChargesAction` | Tras `firstOrCreate` exitoso de RENT por contrato |
| `RunDailyPenaltiesAction` | Tras crear cada cargo PENALTY |
| `ProcessContractSettlementAction` | **Antes** de calcular outstanding / aplicar depósito |

### Idempotencia / concurrencia

- Locks de fila en contract + credit.
- Si dos procesos aplican a la vez, el segundo ve balance reducido.
- No crear payment CREDIT con amount 0.

### Multas

Siguen restando `credit_balances.balance` **restante** de la base. El crédito ya aplicado vive en allocations y baja el pending de cargos; no se doble-cuenta.

### Finiquito (B2)

`DepositBalanceService::outstandingBalanceExcludingDepositHold` (o el settlement) debe netear crédito disponible **después** de `ApplyCreditBalanceAction` (balance ~0 si había pending), o restar `min(credit, outstanding)` si se calcula sin action. Preferir llamar la action primero para un solo camino.

### Tests

- Crédito cubre RENT parcial → balance baja, charge partial/paid.
- Pago cash con crédito previo → crédito primero, luego cash; overflow cash → crédito nuevo.
- Tras generar RENT con crédito existente → RENT pagado/parcial sin payment CASH.
- Tras PENALTY con crédito → se aplica al pending (prioridad).
- Settlement con crédito → no inventa adeudo ni sobre-aplica depósito.

---

## 2. Documentos (S1–S3)

### Morph allowlist

`Documents\Panel::resolveDocumentable()`:

- Allowlist: `Contract`, `Payment`, `Expense`, `Unit`, `Charge` (FQCN).
- Cargar con scope de org; assert `organization_id === auth user org`.
- Abort 403/404 si no.

### Demo upload

- Eliminar ruta `GET /demo/document-upload` y vista/demo Livewire asociados, **o** proteger con `auth` + `permission:system.view` y gate `app.env !== production`. Preferir **eliminar** la ruta pública.

### Disco

- `.env.example`: `DOCUMENTS_DISK=local` (no `public`).
- Subidas existentes siguen usando `config('filesystems.documents_disk')`.
- Añadir ruta autenticada de descarga (p.ej. `documents/{document}/download` con `permission:documents.view` + org scope) que haga `Storage::disk(...)->response()`.
- UI que hoy apunte a `/storage/...` debe usar la ruta auth.
- No migrar archivos en este pase.

### Tests

- Morph a `User` / otro org → 403.
- Demo route → 404 o redirect auth.
- Download sin permiso / otra org → 403.

---

## 3. Financiero (B3–B6)

### B3 — Unique RENT

- MySQL no soporta `UNIQUE … WHERE`. Usar columna generada/nullable:
  - `rent_period_key` (nullable string 7): valor = `period` solo si `type = RENT`, si no `NULL`.
  - Unique `(contract_id, rent_period_key)`. En MySQL varios `NULL` no chocan → no-RENT ilimitados.
- Implementación: columna `storedAs` / generated, o mantener sincronizada en model boot (`saving`). Preferir **generated stored** en MySQL + equivalente en SQLite para tests (trigger o fill en factory/boot).
- Limpieza previa: detectar RENTs duplicados `(contract_id, period)`; si alguno tiene allocations, abortar migración con mensaje; si no, soft-delete duplicados (conservar menor `id`).
- `GenerateMonthlyRentChargesAction`: catch duplicate key como penalties.

### B5 — Transacción única

`RegisterContractPaymentAction`: envolver create payment + `ApplyPaymentAction` + evidencia/document en **una** `DB::transaction`.

### B4 — Settlement idempotente

Al inicio de `ProcessContractSettlementAction`:

- Si `status === ended` → abort con RuntimeException / ValidationException clara.
- Si `meta.settlements` / `settlement_batch_id` ya existe → abort.
- UI wizard: ocultar/bloquear si ended.

### B6 — Rebuild penalties

- No `DB::table` hard-delete de charges con allocations.
- Si hay allocations: abort o soft-path documentado (preferir **abort** con mensaje).
- Respetar `MonthCloseGuard` / no tocar meses cerrados sin flag explícito (fuera: flag admin).

### Tests

- Doble generate-rent concurrente / segundo call → 1 RENT.
- Settlement dos veces → segunda falla.
- Register payment: mock failure mid-apply no deja huérfano (transaction rollback).
- Rebuild con allocation → no borra / falla limpio.

---

## 4. Seguridad media (S4–S6)

- `AuditIndex`: `AuditEvent::query()->where('organization_id', $orgId)->findOrFail($id)`.
- Plazas/settings delete confirms: escapar nombres (`{{ }}` / `e()`), no `{!! $name !!}`.
- `Payments\Show` email: **solo** el email del tenant del contrato (campo readonly o sin input). Botón enviar usa ese valor. Si el tenant no tiene email → deshabilitar envío con mensaje. Quitar input libre.

### Tests

- Audit ID otra org → 404.
- XSS: nombre con `<script>` se escapa en HTML.
- Intento de enviar recibo a otro email (si la action aún acepta param) → validation error / ignored.

---

## 5. Livewire / a11y / quality (parcial)

- `wire:key` en `@forelse` de contracts, cobranza, dashboard, expenses, tenants, properties, payments lists, settlement concepts.
- Modal: focus trap (Alpine `x-trap` o equivalente ya en proyecto) + `aria-labelledby` al título.
- `x-ui.input` / `select`: generar `id` automático si hay `label` y no hay `id`.
- Extraer `ContractOverdueQuery` (o Support) con `overdueStatusSql` / `overdueDaysSql` usados por Dashboard + Cobranza.

**No** en este pase: Larastan.

---

## Docs a actualizar post-implementación

- `docs/AI_ONBOARDING.md` § pagos/crédito: consumo automático opción B + call sites.
- `docs/ARCHITECTURE.md` § CreditBalance / aplicación de pagos.
- `.env.example` `DOCUMENTS_DISK`.

---

## Acceptance checklist

- [ ] Crédito se consume en pago, renta, multa y finiquito; tests verdes
- [ ] Demo upload no público; morph allowlist; download auth
- [ ] Unique RENT; settlement no reentrante; register+apply atómico; rebuild seguro
- [ ] Audit scoped; confirms escapados; recibo email acotado
- [ ] wire:key + labels + focus trap + overdue SQL compartido
- [ ] `sail test` filtros relevantes + `sail pint --dirty`

## Non-goals reminder

No cambiar tasas de multa, prioridades de allocation, ni introducir panel cliente de permisos.
