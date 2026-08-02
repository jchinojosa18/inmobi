# Contratos por vencer — Design Spec

**Date:** 2026-08-02  
**Status:** Approved  
**Related:** `Contract::isExpired()`, `Contracts\Index`, `Contracts\Show`, `Dashboard\Index`, sidebar nav

## Goal

Que el operador sepa cuándo un contrato activo está **por vencer** o ya **vencido**, con:

1. Badge/filtro de status derivado «Por vencer».
2. Columna de vencimiento en el listado (fecha + días).
3. Banner en el detalle.
4. Stat-card + tabla top-10 en el dashboard.
5. Badge de alerta en el ítem «Contratos» del sidebar.

## Decisions (brainstorming)

| Tema | Decisión |
|------|----------|
| Ventana | 30 días fijos (`Contract::EXPIRING_SOON_DAYS`) |
| Enfoque | Estado derivado (como `isExpired()`); no persistir status nuevo en BD |
| Columna listado | Fecha `ends_at` (`d/m/Y`) + subtítulo de días |
| Superficies | Índice + show + dashboard + sidebar |
| Dashboard | Stat-card (conteo por vencer) + tabla top-10 |
| Sidebar | Conteo combinado atención; rojo si hay vencidos, ámbar si solo por vencer |
| Click sidebar | `contracts.index?status=attention` |

## Out of Scope (v1)

- Ventana configurable por organización / Settings
- Emails, WhatsApp o notificaciones push por vencimiento
- Cambiar el enum persistido `contracts.status` (`active` / `ended`)
- Job scheduler para marcar contratos
- Tabla dashboard dedicada solo a «Vencidos» (el filtro `attention` + badge lo cubren)

## Domain rules

Timezone: `America/Tijuana` (igual que `isExpired()`).

| Etiqueta UI | Condición |
|-------------|-----------|
| **Vencido** | `status = active` y `ends_at < hoy` (ya existe) |
| **Por vencer** | `status = active` y `hoy ≤ ends_at ≤ hoy + 30` |
| **Activo** | `status = active` y (`ends_at` null o `ends_at > hoy + 30`) |
| **Finalizado** | `status = ended` (sin badge de vigencia) |

Prioridad de badge de fila / show: **Vencido > Por vencer > Activo/Finalizado**.

`ends_at = hoy` **no** es vencido; sí es por vencer.

### Model API (`Contract`)

```php
public const EXPIRING_SOON_DAYS = 30;

public function isExpiringSoon(?CarbonImmutable $today = null): bool;
public function daysUntilEnd(?CarbonImmutable $today = null): ?int;
```

- `isExpiringSoon`: active + `ends_at` not null + within `[hoy, hoy+30]`.
- `daysUntilEnd`: diferencia en días (`ends_at - hoy`); `null` si no hay `ends_at`.
  - `> 0` faltan días; `0` vence hoy; `< 0` vencido hace N días.

No migración. No cambio de `status` en BD.

## UX

### Listado (`contracts.index`)

Filtros de status (select):

- Activos (`active`)
- Por vencer (`expiring`) — nuevo
- Vencidos (`expired`) — existente
- Requieren atención (`attention`) — nuevo (vencidos + por vencer); destino del badge sidebar
- Finalizados (`ended`)
- Todos (`all`)

Columna nueva **Vencimiento** (entre Propiedad/Unidad y Próximo vencimiento de renta — distinta de `next_due`):

- Fecha con `<x-ui.display-date>` / `DateDisplay`.
- Subtítulo i18n:
  - «Faltan :days días»
  - «Vence hoy»
  - «Vencido hace :days días»
- Sin `ends_at` → guion / vacío.
- Sort por `ends_at` incluido en v1 (asc/desc).

Badge de fila: usa prioridad anterior; «Por vencer» con variant `warning`.

### Detalle (`contracts.show`)

- Banner ámbar si `isExpiringSoon()` (paralelo al banner de vencido).
- Status pill con la misma prioridad de etiquetas.

### Dashboard

- Stat-card: conteo de contratos **por vencer** (solo ventana 30 días, no incluye ya vencidos). Link a `contracts.index?status=expiring`.
- Tabla top-10 «Por vencer»: contrato, inquilino, unidad, `ends_at`, días restantes, acción Ver. Orden `ends_at` asc. Scope plaza actual.

### Sidebar

En el link «Contratos» (`@can('contracts.view')`):

- Pill a la derecha con conteo de contratos en **attention** (activos con `ends_at` no null y `ends_at ≤ hoy+30`).
- Color: **rojo** si `has_expired` (algún activo con `ends_at < hoy`); **ámbar** si el conteo > 0 y ninguno vencido.
- Conteo 0 → no renderizar badge.
- `href` → `route('contracts.index', ['status' => 'attention'])`.
- Scope: `organization_id` + plaza actual (`TenantContext::applyCurrentPlazaFilter` sobre `properties.plaza_id`), igual que el listado.

## Architecture

```text
Contract
  ├── isExpired()          (existente)
  ├── isExpiringSoon()     (nuevo)
  └── daysUntilEnd()       (nuevo)
        │
        ├── Contracts\Index   filtros expiring / attention + columna + sort
        ├── Contracts\Show    banner / status pill
        ├── Dashboard\Index   stat-card + top-10
        └── ContractAttentionNav  (Support) → sidebar pill
```

### Filtros en `Contracts\Index::applyFilters`

Hoy `expired` es caso especial; `active`/`ended` usan `where status`. Extender:

- `expiring`: active + `ends_at` between `hoy` and `hoy+30` (inclusive).
- `attention`: active + `ends_at` not null + `ends_at ≤ hoy+30`.
- `expired`: sin cambio.

### Sidebar data

`App\Support\ContractAttentionNav` (o nombre equivalente) con método que retorna:

```php
['count' => int, 'has_expired' => bool]
```

Compartido al partial del sidebar vía `View::composer` del layout autenticado (o share en el composer del sidebar), solo si el usuario puede `contracts.view`. Query barata: count + exists expired (o un solo aggregate). Sin Livewire en el layout.

### i18n

Claves nuevas en `lang/es|en/contracts.php` y `lang/es|en/dashboard.php` / `ui.php` según corresponda:

- `status_expiring`, `status_expiring_label`, `status_attention`
- `expiring_banner`
- `ends_in_days`, `ends_today`, `ended_days_ago`
- `end_date` / `expiration` para header de columna (reutilizar `end_date` si ya existe)
- Dashboard: título tabla + label stat-card

## Error / edge cases

| Caso | Comportamiento |
|------|----------------|
| `ends_at` null | No expired, no expiring; columna vacía |
| `status = ended` | Nunca por vencer / vencido UI |
| Hoy = `ends_at` | Por vencer, «Vence hoy», no vencido |
| Hoy = `ends_at + 31` días de margen | Activo (fuera de ventana) |
| Sin permiso `contracts.view` | Sin badge sidebar |
| Plaza filtrada | Conteos/listados solo de esa plaza |

## Testing

1. Unit: `isExpiringSoon` / `daysUntilEnd` — hoy, +30, +31, ayer, ended, null `ends_at`.
2. Feature index: badge «Por vencer»; columna fecha+días; filtros `expiring` y `attention`.
3. Feature show: banner por vencer; no banner si fuera de ventana.
4. Feature dashboard: stat count + top-10 ordenado; link al filtro.
5. Feature/layout: sidebar badge conteo; rojo vs ámbar; oculto en 0; link `status=attention`.
6. Plaza scope: contrato de otra plaza no cuenta en el badge.

## Files (expected touch list)

| File | Change |
|------|--------|
| `app/Models/Contract.php` | constante + helpers |
| `app/Support/ContractAttentionNav.php` | conteo sidebar (nuevo) |
| `app/Providers/AppServiceProvider.php` | view composer sidebar |
| `app/Livewire/Contracts/Index.php` | filtros + sort `ends_at` |
| `resources/views/livewire/contracts/index.blade.php` | columna, badges, opciones filtro |
| `resources/views/livewire/contracts/show.blade.php` | banner / pill |
| `app/Livewire/Dashboard/Index.php` | query + props |
| `resources/views/livewire/dashboard/index.blade.php` | stat + tabla |
| `resources/views/layouts/partials/sidebar.blade.php` | pill |
| `lang/es|en/contracts.php`, `dashboard.php`, `ui.php` | strings |
| Tests Feature/Unit correspondientes | cobertura |

## Success criteria

- Operador ve en cualquier pantalla si hay contratos que requieren atención (sidebar).
- En el listado puede filtrar por vencer / atención y ver fecha + días.
- Dashboard resume por vencer sin mezclar con ya vencidos en el stat-card (vencidos siguen en filtro `expired` / `attention`).
)