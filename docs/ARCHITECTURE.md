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
