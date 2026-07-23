# Kardex de inquilino — Design Spec

**Date:** 2026-07-22  
**Status:** Approved  
**Prototype (oficial):** `docs/prototypes/2026-07-22-tenant-kardex-v2-tabs.html`  
**Prototype (descartado):** `docs/prototypes/2026-07-22-tenant-kardex.html` (scroll vertical de secciones)  
**Related:** `docs/AI_ONBOARDING.md` (Tenants → Contracts → Charges/Payments), `docs/superpowers/specs/2026-06-23-ui-redesign-design.md`

## Goal

Pantalla de consulta 360° del inquilino (`/tenants/{tenant}`) con cards de resumen financiero y listados de contratos, cargos con saldo y pagos recientes. Reutiliza componentes UI del sistema. No introduce acciones financieras nuevas.

## Decisions (brainstorming)

| Tema | Decisión |
|------|----------|
| Propósito | Consulta 360° (no centro operativo) |
| Alcance v1 | Datos + KPIs + contratos + cargos pendientes + pagos recientes |
| KPIs | Contratos activos · saldo pendiente · saldo a favor · total pagado |
| Entrada | Desde listado `/tenants`: botón **Ver** en acciones → show |
| Edición | Botón Editar en kardex abre modal (mismos campos que el índice) |
| Layout UI | Prototype #2: header/cards/datos fijos + pestañas para listados |
| Arquitectura | `Tenants\Show` + `App\Support\TenantKardexSummary` |

## Out of Scope (v1)

- Registrar pagos, ajustes, depósito o finiquito desde el kardex
- Documentos / timeline / PDF del kardex
- Link al kardex desde `contracts.show` (puede agregarse después)
- Permisos nuevos (reutilizar `tenants.view` / `tenants.manage`)
- Portal del inquilino

## Architecture

### Enfoque elegido

Livewire `Tenants\Show` orquesta UI, permisos y modal de edición. Un servicio `TenantKardexSummary` calcula KPIs y provee colecciones para las tablas, reutilizando la noción de cargos **operativos** (excluye `DEPOSIT_HOLD`) alineada a `Contracts\Show`.

### Alternativas descartadas

| Enfoque | Motivo |
|---------|--------|
| Show monolítico sin Support | Totales atados a UI; peor testabilidad |
| Paneles Livewire hijos | Overkill para v1 de solo lectura |

### Piezas

| Pieza | Rol |
|-------|-----|
| `app/Livewire/Tenants/Show.php` | Página; `tenants.view`; modal edit con `tenants.manage` |
| `resources/views/livewire/tenants/show.blade.php` | Layout de ficha |
| `app/Support/TenantKardexSummary.php` | Agregados + listados |
| Ruta `GET /tenants/{tenant}` | `tenants.show`, middleware `permission:tenants.view` |
| `Tenants\Index` | Nombre del inquilino enlaza a `tenants.show` |

## Data / KPI rules

Scope: contratos del inquilino en la organización (org-scoped; soft-deleted excluidos).

| KPI | Cálculo |
|-----|---------|
| Contratos activos | `contracts.status = active` |
| Saldo pendiente | Suma `max(0, amount − allocated)` en cargos operativos (excluye `DEPOSIT_HOLD` y `DEPOSIT_APPLY`, igual que `Contracts\Show`) |
| Saldo a favor | Suma `credit_balances.balance` de esos contratos |
| Total pagado | Suma `payments.amount` (no soft-deleted) de esos contratos |

### Listados

- **Contratos:** todos; columnas: unidad/propiedad, status, fechas relevantes, renta; link a `contracts.show` si aplica permiso.
- **Cargos con saldo:** operativos con balance > 0; columnas: contrato/unidad, tipo, fecha, monto, pagado, saldo; link al contrato.
- **Pagos recientes:** últimos 15 por `paid_at` desc; columnas: folio, fecha, método, monto, contrato; link a `payments.show` si `payments.view`.

## UI

Alineada a `Contracts\Show` / `Units\Show` y design system (`x-ui.*`). Layout oficial = **prototype #2 (tabs)**.

**Siempre visibles (arriba):**
1. `page-header`: nombre; descripción email · teléfono; badge status; acciones Volver + Editar  
2. Grid 4× `stat-card`  
3. `card` datos básicos + notas  

**Debajo, una sola `card` con pestañas** (Alpine/`wire:model` tab; una sección visible a la vez):
4. Tab **Contratos** (default) — `table` + empty state; contador en la pestaña  
5. Tab **Cargos con saldo** — `table` operativos con balance > 0; contador  
6. Tab **Pagos recientes** — `table` últimos 15; contador  

7. Modal edición (mismos campos/validación que `Tenants\Index`)

**Acciones “ver”:** botones solo con icono **eye** (mismo SVG que `documents/panel` contract variant): `variant="secondary" size="sm"`, `title` + `aria-label`. Aplicar a ver contrato, ver pago y cualquier apertura de archivo vía `x-ui.file-viewer-trigger`. No usar texto “Ver …” en tablas del kardex.

**Navegación de regreso:** los links del kardex a contrato/pago llevan `?return=` + `?return_label=` (vía `App\Support\NavigationReturn`, solo paths relativos internos). El `return` incluye la pestaña activa cuando no es la default (`Show::DEFAULT_TAB`): cualquier tab en `Show::TABS` distinta de default → `/tenants/{id}?tab={tab}`. Al agregar una pestaña nueva, registrarla en `TABS` basta para que el Volver la conserve. En `contracts.show` / `payments.show`, el botón Volver usa esa URL y etiqueta cuando vienen; si no, defaults (`Volver a contratos` / `Volver al contrato`).

Pestaña activa puede persistir en query string (`?tab=contracts|charges|payments`) opcional en implementación.

En el índice: botón **Ver** en acciones abre `tenants.show`; el nombre es texto plano; el botón Editar del listado se mantiene.

## Permissions & errors

- Sin `tenants.view` → 403  
- Tenant otra org / inexistente → 404 (OrganizationScoped)  
- Sin `tenants.manage` → no Editar / no save  
- Links a contratos/pagos respetan `@can` de esos módulos  
- Sin contratos → KPIs en `0` y empty states

## Testing

- Feature: show con permiso; 403 sin permiso; aislamiento por org  
- Feature: KPIs con 2 contratos (activo/ended, cargos, allocations, crédito, pagos)  
- Feature: nombre en índice → show; edit desde show actualiza  
- Unit (opcional): `TenantKardexSummary` casos de agregación  

Verificación: `./vendor/bin/sail test --filter=Tenant` + `./vendor/bin/sail pint --dirty`

## Future (no v1)

- Link desde contrato al kardex del inquilino  
- Cards de depósito en custodia / documentos  
- Acciones rápidas (pago) si el producto lo pide
