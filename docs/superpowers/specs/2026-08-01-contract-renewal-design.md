# Renovación de contrato + PDF + envío — Design Spec

**Date:** 2026-08-01  
**Status:** Approved (brainstorming)  
**Related:** `Contract`, `ProcessContractSettlementAction`, `RegisterDepositHoldAction`, `GenerateMonthlyRentChargesAction`, `PaymentReceiptShareUrl` / `PaymentReceiptMail`, `OrganizationSettingsService`, documento base `CONTRATO DE ARRENDAMIENTO 2023.docx`

## Goal

Permitir **renovar** un contrato vencido (o por vencer) creando un **contrato nuevo** vinculado al historial del inquilino, con:

1. Cierre automático del contrato anterior **sin finiquito completo**.
2. Transferencia contable del depósito (salida en el viejo → hold nuevo en el nuevo).
3. Diferencia de depósito si sube la renta (cargo + opción de marcarlo recibido).
4. Generación de **PDF** del contrato (DomPDF/Blade; contenido legal del DOCX de referencia).
5. Envío por **email** (si hay correo) y opción **WhatsApp** (mismo patrón que pagos).
6. UI de estado **Vencido** cuando `status=active` pero `ends_at < hoy`.

## Decisions (brainstorming)

| Tema | Decisión |
|------|----------|
| Modelo de datos | Contrato **nuevo** (nuevo id); link al anterior |
| Contrato anterior | `ended` automático al renovar (sin settlement wizard) |
| Saldo pendiente | **Bloquear** renovación si hay adeudo operativo |
| Documento | **Solo PDF** (DomPDF + Blade); DOCX solo como fuente legal |
| Arrendador | Configurable por org en Settings |
| Depósito | No reasignar holds: `DEPOSIT_TRANSFER_OUT` en viejo + nuevo `DEPOSIT_HOLD` en nuevo |
| Diferencia depósito | Crear hold por diferencia; wizard puede marcarlo “ya recibido” |
| Renta al renovar | Generar RENT del mes en curso (hooks/`ensure` existentes) |
| Email / WhatsApp | Como pagos: mailable + PDF; `wa.me` + link firmado |

## Out of Scope (v1)

- Renovación masiva / batch.
- Firma electrónica / e-sign.
- Edición libre del texto legal por contrato (solo datos variables).
- API WhatsApp Business (solo deep link `wa.me`).
- Migrar contratos históricos a “vencido” automático en DB (`status` no se cambia solo por fecha).
- Finiquito completo en el flujo de renovación (si hay daños/salida real → settlement aparte).
- Incremento automático del 7% del texto legal (el usuario captura la nueva renta en el wizard).

## Behavior

### Cuándo se puede renovar

Elegible si:

- Mismo `tenant_id` + `unit_id` del contrato origen.
- Origen `status=active` **o** ya `ended` sin otro activo en la unidad (caso: vencido mal etiquetado / recién cerrado).
- Origen debe estar `active` (incl. badge Vencido). Si ya está `ended`, v1 **no** permite este wizard (evitar doble cierre / depósito ya liquidado); alta manual si aplica.
- **No** hay saldo operativo pendiente en el origen (`DepositBalanceService::outstandingBalanceExcludingDepositHold` = 0).
- No existe **otro** contrato `active` en la unidad distinto del origen.
- Origen **sin** `meta.settlement_batch_id` (si ya hubo finiquito, no renovar por este flujo).

### Flujo `RenewContractAction`

Transacción única:

1. **Validar** guards (saldo, unidad, org, fechas).
2. **Crear** contrato nuevo:
   - Copia: `organization_id`, `unit_id`, `tenant_id`, `due_day`, `grace_days`, `penalty_rate_daily` (editables en wizard).
   - Captura: `rent_amount`, `deposit_amount` (default = depósito disponible a transferir; **no** se iguala solo a la nueva renta), `starts_at`, `ends_at`, `status=active`.
   - `meta.renewed_from_contract_id` = id origen.
   - Origen: `meta.renewed_to_contract_id` = id nuevo.
3. **Cerrar origen** (sin finiquito):
   - `status=ended`.
   - `ends_at` = día previo a `starts_at` del nuevo (si aún no estaba en el pasado), sin pisar un `ends_at` ya menor salvo que el wizard lo pida.
4. **Transferir depósito** (ver abajo).
5. **Diferencia de depósito** si aplica (ver abajo).
6. **RENT mes en curso**: el hook `created` de `Contract` ya llama `ensureCurrentMonthForContract` (incluye due-soon abiertos tras fix reciente). No duplicar lógica fuera del action salvo re-ensure explícito si el hook no corre en el path de tests.
7. **PDF**: generar, guardar Document en el contrato nuevo (`variant=contract` / categoría contrato).
8. Retornar DTO: nuevo contrato, ids de cargos, path/url PDF, flags de envío.

### Depósito — transferencia (mejor práctica)

No mover filas `DEPOSIT_HOLD` del contrato viejo.

| Paso | Contrato | Tipo | Efecto |
|------|----------|------|--------|
| 1 | Viejo | `DEPOSIT_TRANSFER_OUT` | Concepto de **salida** por el monto `availableDepositAmount` del origen → garantía disponible del viejo queda en **0** |
| 2 | Nuevo | `DEPOSIT_HOLD` | Nuevo registro por ese monto; `meta` con origen (`transferred_from_contract_id`, `transfer_out_charge_id`, opcional refs a holds previos) |

Analogía con finiquito: es una **salida de garantía** al cerrar el contrato viejo, pero el destino no es `DEPOSIT_APPLY` (adeudo) ni egreso de devolución (cash), sino el hold del contrato nuevo.

Reglas:

- `availableDepositAmount` del origen debe incluir la resta de `DEPOSIT_TRANSFER_OUT` (además de apply/refund existentes).
- Excluir `DEPOSIT_TRANSFER_OUT` de ingresos operativos / cobranza (junto a `DEPOSIT_HOLD` / `DEPOSIT_APPLY`).
- Almacenamiento: **monto negativo** (igual que `DEPOSIT_APPLY`), p.ej. `-9500.00`, para que la salida reduzca disponible con la misma familia de reglas; UI muestra el valor absoluto como “Transferencia de depósito”.
- Si depósito disponible = 0: no crear transfer out ni hold heredado; solo diferencia si el wizard registra depósito nuevo.

### Diferencia de depósito

```
diff = max(nuevo.deposit_amount - transferred_amount, 0)
```

- Si `diff > 0`: crear (o dejar pendiente vía wizard) un `DEPOSIT_HOLD` adicional por `diff`.
- Wizard opción **“Ya recibí la diferencia”**: registra el hold en el mismo flujo (reutilizar `RegisterDepositHoldAction` / reglas de tope).
- Si no: el nuevo contrato queda con `remainingDepositHoldAmount` = diff hasta que lo registren en UI de depósito.

### PDF del contrato

- Vista: `resources/views/pdf/lease-agreement.blade.php` (texto del DOCX 2023 + variables).
- Generador: DomPDF existente + `StreamsPdfResponse` / patrón de recibos.
- Variables mínimas:

| Variable | Fuente |
|----------|--------|
| Fecha documento (ciudad, día, mes, año) | Wizard / now Tijuana |
| ARRENDADOR | `organization_settings` (nombre / representante) |
| ARRENDATARIO | `tenants.name` |
| INE / ID | `tenants.ine_clave` |
| Dirección inmueble | property + unit |
| Vigencia (texto + fechas inicio/fin) | contrato nuevo |
| Renta $ y letra | `rent_amount` + helper número→letra |
| Día de pago | `due_day` |
| Depósito | `deposit_amount` / transferido + diff |
| Fecha devolución inmueble | `ends_at` |

- Guardar PDF en storage + `documents` morph al contrato.
- Ruta descarga autenticada + **URL firmada** (~7 días) para share (espejo de `PaymentReceiptShareUrl`).

### Settings (por organización)

Ampliar `organization_settings` (o JSON meta existente):

- `landlord_name` (ARRENDADOR).
- Opcional: `landlord_rep`, domicilio fiscal corto.
- `contract_email_template`, `contract_whatsapp_template` (vars: `tenant_name`, `unit_name`, `contract_id`, `shared_contract_url`, fechas, renta).

Sin plantilla de arrendador: UI avisa y bloquea generación/envío hasta completar Settings (renovar puede exigir `landlord_name`).

### Email y WhatsApp

- `ContractAgreementMail`: body desde plantilla org; adjunta PDF.
- Enviar automáticamente al terminar renovación **solo si** `tenant.email` presente; si no, skip silencioso + mensaje UI.
- WhatsApp: botón post-renovación y en `Contracts\Show` → `https://wa.me/{phone}?text=...` con plantilla + link firmado (no API).
- Permiso envío: reutilizar `receipts.send` en v1 (mismo acto de compartir documento al inquilino); si el seeder ya tiene permiso de contratos más preciso, preferirlo sin inventar nombres nuevos.

### UI estado “Vencido”

- Badge: si `status===active` && `ends_at` date `< today (America/Tijuana)` → **Vencido** (no “Activo”).
- Index: filtro/segmento vencidos; CTA Renovar.
- Show: banner + botón Renovar.
- `status` en DB **no** se auto-cambia a ended solo por fecha (evita side effects); el cierre formal es renovación o settlement/edit.

## Architecture

```text
Contracts\Show / Index
        │ Renovar
        ▼
RenewContractWizard (Livewire)
        │
        ▼
RenewContractAction  ──┬── end old contract
                       ├── DEPOSIT_TRANSFER_OUT (old)
                       ├── DEPOSIT_HOLD transfer (new)
                       ├── DEPOSIT_HOLD diff (optional register)
                       ├── create Contract (hooks → RENT)
                       └── GenerateLeaseAgreementPdfAction
                                │
                                ├── Document store
                                ├── ContractAgreementMail (optional)
                                └── ContractAgreementShareUrl + WhatsApp CTA
```

### Componentes nuevos (orientativos)

| Pieza | Rol |
|-------|-----|
| `RenewContractAction` | Caso de uso transaccional |
| `GenerateLeaseAgreementPdfAction` | Render DomPDF + persist Document |
| `ContractAgreementShareUrl` | Signed URL |
| `ContractAgreementMail` | Email |
| `RenewContractWizard` (Livewire) | UI |
| `Charge::TYPE_DEPOSIT_TRANSFER_OUT` | Tipo ledger |
| Ajustes `DepositBalanceService` / reportes | Excluir transfer out de operativos; restar de disponible |
| Settings fields | Arrendador + plantillas contrato |

## Error handling

| Caso | Comportamiento |
|------|----------------|
| Saldo pendiente | Abort 422/flash; listar monto |
| Otro activo en unidad | Abort |
| `landlord_name` vacío | Abort generación PDF / bloquear confirmación |
| Mes cerrado bloquea cargo | Abort con mensaje MonthCloseGuard |
| Sin email | No envía; ofrece WhatsApp/descarga |
| Sin teléfono | Oculta/deshabilita WhatsApp |
| Doble submit | Idempotencia: lock por `contract_id` origen o unique meta |

## Test plan

- Renovación feliz: nuevo id, origen `ended`, `meta` links, RENT mes actual, Document PDF.
- `DEPOSIT_TRANSFER_OUT` deja disponible=0 en origen; nuevo hold = monto transferido.
- Diferencia: hold extra; “ya recibido” vs pendiente.
- Bloqueo con saldo pendiente.
- Badge Vencido en index/show.
- Email enviado si hay email; no crash si no hay.
- WhatsApp URL contiene share link.
- Tipos depósito excluidos de operating income.
- Contrato #3-like fixture: `active` + `ends_at` pasado → renovable tras saldar.

## Source document

Plantilla legal de referencia (contenido a portar a Blade):

`/Users/jc/Downloads/CONTRATO DE ARRENDAMIENTO 2023.docx`

Copiar una versión versionada al repo en implementación, p.ej. `docs/legal/contrato-arrendamiento-referencia.docx` (solo referencia; runtime = PDF Blade).

## Docs to update (implementación)

- `docs/AI_ONBOARDING.md`: renovación, `DEPOSIT_TRANSFER_OUT`, PDF contrato, envío.
- README corto si aplica comandos/permisos nuevos.
