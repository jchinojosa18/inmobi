# Arquitectura Funcional y Convenciones

## Objetivo
Definir la arquitectura base del SaaS de administración inmobiliaria y alinear convenciones de dominio antes de implementar código.

## Módulos funcionales
1. Catálogos
- Entidades maestras: propiedades, unidades, arrendadores, arrendatarios, conceptos de cobro, bancos, métodos de pago.
- Debe centralizar validaciones y evitar duplicidad de datos maestros.

2. Contratos
- Alta, renovación, terminación y estados de contrato.
- Versionado de condiciones contractuales y relación con documentos adjuntos.

3. Cargos
- Generación de cargos periódicos y extraordinarios.
- Estados: pendiente, parcial, pagado, vencido, cancelado.

4. Pagos
- Registro de pagos totales/parciales.
- Aplicación contra cargos bajo reglas de prioridad de negocio.

5. Multas diarias
- Cálculo diario por mora sobre saldos vencidos.
- Trazabilidad de fórmula, tasa y periodo aplicado.

6. Depósitos
- Control de depósitos en garantía.
- Movimientos: retención, devolución total/parcial, aplicación por daños o adeudos.

7. Egresos
- Registro de gastos operativos por propiedad/unidad.
- Clasificación contable y asociación opcional a comprobantes.

8. Documentos
- Gestión documental por entidad (contrato, pago, egreso, cierre).
- Versionado y metadatos mínimos para auditoría.

9. Reportes
- Reportes operativos, financieros y de cobranza.
- Exportables (CSV/PDF) con filtros por periodo y propiedad.

10. Cierres
- Cierre mensual operativo/financiero por propiedad.
- Bloqueo de movimientos retroactivos tras cierre, salvo reapertura autorizada.
- Reapertura permitida solo para rol `Admin`.

## Principios de arquitectura
- Auditoría completa: todo movimiento financiero debe registrar `quién`, `cuándo`, `qué cambió`, `origen`.
- Cierres mensuales: los cierres son frontera de control; modificar periodos cerrados requiere reapertura explícita.
- Movimientos inmutables: priorizar ledger/eventos de movimientos en lugar de sobreescritura de saldos.
- Reglas explícitas: multas, aplicación de pagos y políticas de depósito deben ser configurables y versionables.
- Trazabilidad documental: cada operación relevante puede referenciar evidencia documental.

## Auditoria tecnica
- La base de auditoria y trazabilidad se implementa con `spatie/laravel-activitylog`.
- El detalle operativo y convenciones de uso estan en `docs/AUDIT_TRAIL.md`.

## Almacenamiento de documentos
- La base tecnica de almacenamiento de evidencias/documentos esta documentada en `docs/DOCUMENT_STORAGE.md`.
- Desarrollo usa almacenamiento local y produccion queda lista para S3 compatible via variables de entorno.

## Generacion de PDF
- La base tecnica para renderizar recibos/finiquitos en PDF esta en `docs/PDF_GENERATION.md`.
- Existe una ruta demo protegida para validar renderizado sin logica financiera: `/pdf/sample-receipt`.

## Operacion diaria
- La base tecnica de scheduler + queue para tareas diarias esta en `docs/DAILY_OPERATIONS.md`.
- Existe un comando placeholder `inmo:daily` y un job de ejemplo para validar el pipeline operativo.

## Control de acceso (RBAC)
- Se utiliza `spatie/laravel-permission` como mecanismo central de roles y permisos.
- Roles base iniciales:
  - `Admin`: acceso total y administración de seguridad.
  - `Capturista`: captura y actualización operativa sin privilegios administrativos.
  - `Lectura`: acceso de consulta sin escritura.
- Convención de permisos por módulo (cuando se habiliten): `<modulo>.<accion>`.
  - Ejemplos: `contratos.view`, `contratos.create`, `pagos.apply`, `reportes.export`.
- Protección de rutas:
  - Middleware por rol: `role:Admin`.
  - Middleware por permiso: `permission:<permiso>`.
  - Middleware combinado: `role_or_permission:<rol>|<permiso>`.
- Seeder de seguridad:
  - Crea roles `Admin`, `Capturista`, `Lectura`.
  - Asigna `Admin` al primer usuario del sistema.
- Endpoint técnico actual: `/admin/health` protegido con `role:Admin`.

## Configuración por organización
- Pantalla: `/settings` (auth).
- Edición: solo rol `Admin` (usuarios sin `Admin` tienen vista de solo lectura).
- Persistencia:
  - `organization_settings` (1:1 con `organizations`).
  - `expense_categories` (1:N con `organizations`).
- Defaults seguros (backward-compatible):
  - Si no existe registro de `organization_settings`, el sistema conserva comportamiento actual.
  - Folio default: modo `annual`, prefijo `REC`, padding `6` (`REC-YYYY-######`).
  - Plantillas default para WhatsApp y email se aplican cuando no hay configuración.
- Variables soportadas en plantillas:
  - `{tenant_name}`, `{unit_name}`, `{amount_due}`, `{shared_receipt_url}`.
- Categorías de egresos:
  - CRUD simple por organización.
  - Se usan como catálogo sugerido para captura/filtro de egresos sin romper categorías históricas.
- Política de multas:
  - Se documenta en configuración, pero no altera el motor actual.
  - El redondeo operativo permanece en 2 decimales.

## Multi-tenant (Organization/Account)
- El tenant principal es `Organization` (equivalente a Account).
- Relacion base:
  - `User belongsTo Organization`.
  - Todo modelo de dominio futuro (`Propiedad`, `Unidad`, `Contrato`, etc.) debe incluir `organization_id`.
- Scoping obligatorio:
  - Middleware `SetTenantOrganization` fija el `organization_id` activo del usuario autenticado por request.
  - Trait reusable `BelongsToOrganization` aplica `Global Scope` por `organization_id` para modelos de dominio.
  - Base recomendada para dominio: `App\Domain\Shared\OrganizationScopedModel` (extiende Eloquent + trait de tenant).
- Politica anti-fugas:
  - Si falta contexto de tenant en una request HTTP, el scope se comporta en modo *fail closed* (`1 = 0`) para evitar lecturas cruzadas.
  - Para procesos internos controlados (jobs/comandos/reportes cross-tenant), remover scope de forma explícita con `withoutOrganizationScope()` o `withoutGlobalScope('organization')`.
- Seeder inicial:
  - Se crea `Organization` con nombre `Default`.
  - El primer usuario queda asociado a esa organización.
- Regla de implementacion futura:
  - Ningun query de modelos de dominio debe ejecutarse sin scoping de `organization_id`.
  - Evitar `DB::table(...)` directo para entidades multi-tenant sin filtro explícito por organización.

## Nucleo ledger (movimientos auditables)
- Base de dominio enfocada en ledger: los saldos se derivan de movimientos (`charges`, `payments`, `payment_allocations`) y no de sobreescritura de campos acumulados.
- Todas las entidades de dominio incluyen `organization_id` y usan `OrganizationScopedModel` para filtrar por tenant activo.
- Borrado logico obligatorio:
  - Entidades del dominio usan `softDeletes`.
  - No se contempla borrado fisico para historial operativo/financiero.

### Modelo Casa Standalone
- Se mantiene el modelo base `Property -> Unit` para todo el sistema.
- Una casa se modela como:
  - `Property.kind = standalone_house`
  - `Unit.kind = house`
- Regla funcional:
  - Una `Property` standalone debe tener exactamente una `Unit` asociada.
  - La captura UX de "Nueva casa" crea ambas entidades en una sola transacción para evitar estados intermedios.
- Edificios/departamentos continúan usando:
  - `Property.kind = building`
  - `Unit.kind = apartment` (una o múltiples unidades).

### Relaciones (diagrama textual)
```text
Organization 1---* User
Organization 1---* Property
Property     1---* Unit
Organization 1---* Tenant

Unit         1---* Contract
Tenant       1---* Contract
Contract     1---* Charge
Unit         1---* Charge

Contract     1---* Payment
Payment      1---* PaymentAllocation *---1 Charge

Unit         1---* Expense (unit_id nullable para gasto general)

Document     *---1 documentable (morph: Contract | Payment | Expense | Unit)

Organization 1---* MonthClose
MonthClose   *---1 User (closed_by_user_id)
Contract     1---1 CreditBalance (saldo a favor acumulado)
```

### Reglas tecnicas del ledger
- Tipos de `Charge` relevantes para operaciones financieras:
  - Operativos: `RENT`, `SERVICE`, `PENALTY`, `DAMAGE`, `CLEANING`, `OTHER`, `MOVEOUT`, `ADJUSTMENT`.
  - Depósito garantía:
    - `DEPOSIT_HOLD`: depósito recibido (pasivo, no ingreso operativo).
    - `DEPOSIT_APPLY`: aplicación de depósito al finiquito (monto negativo/credito).
    - `MOVEOUT`: cargos de salida; el detalle va en `meta.subtype` (daño, limpieza, adeudos, etc).
- `Contract`:
  - Restriccion de contrato activo por unidad mediante `unique(unit_id, active_lock)`.
  - `active_lock` se mantiene en `1` para `status=active` y `null` para `status=ended`.
- `PaymentAllocation`:
  - `unique(payment_id, charge_id)` para evitar duplicar asignaciones del mismo pago al mismo cargo.
  - Garantiza determinismo de aplicacion "pago -> cargos".
- `Payment`:
  - `receipt_folio` unico por organización (`unique(organization_id, receipt_folio)`).
- `MonthClose`:
  - Un cierre por mes y organización (`unique(organization_id, month)`).
  - `snapshot` JSON almacena totales congelados del cierre.
  - Snapshot actual incluye:
    - `ingresos_operativos` (estricto por `PaymentAllocation` en tipos operativos configurados)
    - `ingresos_operativos_por_tipo`
    - `egresos`
    - `neto`
    - `cartera`
    - `conteos` (`contratos_activos`, `pagos`, `egresos`)
  - El snapshot deja trazado `income_source = strict_allocations_by_charge_type`.
- `CreditBalance`:
  - Saldo a favor por contrato (`unique(organization_id, contract_id)`).
  - `last_payment_id` referencia el ultimo pago que incrementó el saldo.

### Aplicacion de pagos (sin UI)
- Caso de uso: `ApplyPaymentAction` (transaccional e idempotente).
- Prioridad de aplicación:
  1. `RENT` pendiente mas antiguo primero.
  2. `SERVICE` marcado como reembolsable (`meta.refundable = true`).
  3. `PENALTY`.
  4. Otros (`DAMAGE`, `CLEANING`, `ADJUSTMENT`, `OTHER`, o `SERVICE` no reembolsable).
- Soporte esperado:
  - Pagos parciales (genera `PaymentAllocation` por monto aplicado).
  - Pagos adelantados (si hay cargos `RENT` futuros pendientes, tambien se aplican).
  - Excedente: se registra en `credit_balances` como saldo a favor del contrato.
- Razon de estrategia de saldo a favor:
  - Se usa tabla dedicada `credit_balances` en lugar de cargos negativos para no romper la semantica de estado de `Charge` (pending/partial/paid) y mantener trazabilidad contable mas clara.
- Folio de recibo (`receipt_folio`) en MVP:
  - Estrategia configurable por organización (vía `/settings`):
    - `annual`: `PREFIJO-YYYY-####...`
    - `continuous`: `PREFIJO-####...`
  - Defaults: `annual` + `REC` + padding `6`.
  - Secuencia consecutiva por organización en el scope del modo elegido.
- Constraint de seguridad: `unique(organization_id, receipt_folio)`.

### Motor de multas diarias
- Comando: `inmo:penalties:run --date=YYYY-MM-DD [--from-date=YYYY-MM-DD]`.
- Regla de unicidad fuerte:
  - `UNIQUE(contract_id, penalty_date, type)` en `charges` para `PENALTY`.
  - `penalty_date` solo se usa para `type=PENALTY` (otros cargos mantienen `NULL`).
- Cálculo diario por contrato:
  - corte = `D-1 23:59:59` en `America/Tijuana`.
  - base = cargos del contrato hasta corte
    menos allocations aplicadas con `payments.paid_at <= corte`
    menos saldo a favor.
  - solo genera multa si existe al menos un `RENT` vencido con saldo pendiente.
- Timezone de consulta:
  - el corte se calcula en `America/Tijuana` y se convierte a timezone de almacenamiento (`config('app.timezone')`, típicamente UTC) para comparar con `payments.paid_at`.

### Cierre mensual y bloqueo de periodos
- Pantalla operativa:
  - Ruta: `/month-closes` (auth).
  - Acciones: `Cerrar mes` y `Reabrir mes` (solo `Admin`).
  - Se muestra `quien` y `cuando` del cierre.
- Enforcements al cerrar un mes:
  - Se bloquea crear/editar/eliminar en ese `YYYY-MM` para:
    - `Payment` (usa `paid_at`)
    - `Expense` (usa `spent_at`)
    - `Charge` (usa `charge_date` o `period`)
    - `Document` cuando está asociado a `Payment`, `Expense` o `Charge` del mes cerrado.
- Mensaje de bloqueo:
  - Error funcional visible en UI con referencia al mes bloqueado (`Mes bloqueado: YYYY-MM`).
- Estrategia de corrección:
  - No se reescribe historial de meses cerrados.
  - Se permite registrar `Charge` tipo `ADJUSTMENT` con:
    - `amount` (+/-)
    - `meta.reason` obligatorio
    - `meta.linked_to` y `meta.comment` opcionales para trazabilidad.
  - Los ajustes quedan auditados via `activitylog`.

### Depósito y finiquito
- Registro de depósito:
  - Se registra en contrato como `Charge::DEPOSIT_HOLD`.
  - Puede liquidarse con `Payment` normal (vía `PaymentAllocation`) para conservar evidencia y recibo.
  - No se contabiliza como ingreso operativo en reportes de flujo.
- Finiquito:
  - El wizard de finiquito crea cargos `MOVEOUT` por concepto.
  - Calcula adeudo total pendiente del contrato.
  - Aplica depósito disponible con `DEPOSIT_APPLY` (monto negativo) hasta cubrir adeudo.
  - Si sobra depósito, se genera `Expense` categoría `Refund deposit` para devolución real.
  - El contrato se marca `ended` con `ends_at` en la fecha de salida.
  - Se guarda `settlement_batch_id` en metadatos para trazabilidad y PDF de finiquito.

### Reporte de flujo (MVP)
- Reporte operativo `Flujo por rango`:
  - `Ingresos`: suma de `PaymentAllocation.amount` por `paid_at` del pago en el rango seleccionado.
  - Tipos operativos configurables por defecto:
    - `RENT`, `PENALTY`, `SERVICE`, `OTHER`, `ADJUSTMENT`.
  - Exclusiones operativas:
    - `DEPOSIT_HOLD` y `DEPOSIT_APPLY`.
  - `Egresos`: suma de `Expense.amount` por `spent_at` en el rango seleccionado.
  - `Neto`: `Ingresos - Egresos`.
- Exportación MVP:
  - Formato CSV con:
    - detalle de ingresos por allocation
    - resumen de ingresos por tipo
    - detalle de egresos
    - líneas de resumen (`TOTAL_INGRESOS`, `TOTAL_EGRESOS`, `NETO`).

### Anti-fugas de datos (tenant)
- Todo query de modelos de dominio se ejecuta con global scope por `organization_id`.
- Para procesos administrativos cross-tenant (reportes globales, soporte), remover scope solo de forma explicita:
  - `->withoutOrganizationScope()` o `->withoutGlobalScope('organization')`.
- Evitar consultas directas sin scope (`DB::table`) salvo casos controlados con filtro explicito por organización.

## Convenciones de naming

### Models
- Singular en `StudlyCase`.
- Ejemplos: `Contrato`, `Cargo`, `Pago`, `MultaDiaria`, `CierreMensual`.
- Si aplica DDD táctico, usar sufijos solo cuando aclaren intención (`PagoAplicado`, `DepositoMovimiento`).

### Migrations
- Convención Laravel estándar: `YYYY_MM_DD_HHMMSS_<accion>_<tabla>`.
- Usar verbos consistentes: `create`, `add`, `drop`, `rename`.
- Ejemplos:
  - `2026_03_03_120000_create_contratos_table`
  - `2026_03_03_121000_add_estado_to_cargos_table`

### Livewire Components
- Clases en `StudlyCase`: `ContratosIndex`, `PagosForm`, `CierresMensualesPanel`.
- Vistas en `kebab-case` por contexto:
  - `resources/views/livewire/contratos/index.blade.php`
  - `resources/views/livewire/pagos/form.blade.php`
- Nombrar por intención de pantalla (`Index`, `Show`, `Form`, `Wizard`, `Panel`).

## Estructura recomendada (documental)

```text
app/
  Domain/
    Catalogos/
      Models/
      ValueObjects/
      Policies/
    Contratos/
      Models/
      Services/
      Policies/
    Cargos/
      Models/
      Calculators/
      Policies/
    Pagos/
      Models/
      Services/
      Policies/
    Multas/
      Models/
      Calculators/
    Depositos/
      Models/
      Services/
    Egresos/
      Models/
      Services/
    Documentos/
      Models/
      Services/
    Reportes/
      Queries/
      DTOs/
    Cierres/
      Models/
      Services/

  Actions/
    Contratos/
      CrearContratoAction.php
      RenovarContratoAction.php
    Cargos/
      GenerarCargoMensualAction.php
    Pagos/
      RegistrarPagoAction.php
      AplicarPagoAction.php
    Multas/
      CalcularMultaDiariaAction.php
    Depositos/
      RegistrarDepositoAction.php
      AplicarDepositoAction.php
    Egresos/
      RegistrarEgresoAction.php
    Cierres/
      EjecutarCierreMensualAction.php
      ReabrirCierreMensualAction.php
```

## Criterios de implementación futuros
- Cada `Action` representa un caso de uso atómico y transaccional.
- Los módulos de `Domain` no dependen de UI (Livewire) ni de infraestructura externa.
- Reportes deben construirse con consultas optimizadas y explícitas (evitar lógica crítica en vistas).
