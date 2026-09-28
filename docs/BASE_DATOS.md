# Base de Datos

## Motor
MySQL 8+, con integridad referencial, índices, constraints, transacciones y normalización apropiada (sin sobre-normalización innecesaria).

## Entidades mínimas
| Tabla | Propósito |
|---|---|
| `users` | Cuentas de usuario (login) |
| `businesses` | Emprendimientos (tenants) |
| `roles` | Catálogo de roles (Administrador, Empleado) |
| `business_user` | Relación N:M usuario–emprendimiento con rol |
| `categories` | Categorías de producto, por `business_id` |
| `products` | Productos, por `business_id` |
| `customers` | Clientes, por `business_id` |
| `suppliers` | Proveedores, por `business_id` |
| `sales` | Cabecera de venta, por `business_id` |
| `sale_details` | Detalle de venta (líneas) |
| `sale_payments` | Métodos de pago y vueltos aplicados a la venta, por `business_id` |
| `expenses` | Registro de facturas de compra y gastos operativos, por `business_id` |
| `inventory_movements` | Historial de movimientos de stock (entradas/salidas/ajustes) |

Agregar tablas nuevas solo si son estrictamente necesarias, justificándolo en `docs/DECISIONES_TECNICAS.md`.

## Campos clave por tabla (mínimos)
- **users**: nombre, correo, contraseña, `is_superadmin` (boolean, default false, flag de plataforma), `must_change_password` (boolean, default false, forzado de actualización).
- **businesses**: nombre, nit, teléfono, dirección, `status` (varchar 20, default 'active': 'active' / 'inactive'), `subscription_starts_at` (dateTime nullable), `subscription_ends_at` (dateTime nullable, index).
- **business_user**: `user_id`, `business_id`, `role_id`, `is_active`. Restricción `UNIQUE(user_id)` que asegura que un usuario pertenece a un único negocio.
- **products**: nombre, descripción, SKU/código (único por `business_id`), categoría, precio de compra, precio de venta, stock actual, stock mínimo, estado, `business_id`.
- **customers**: `business_id`, nombre, `document` (varchar 30, normalizado sin espacios/puntos, único por tenant), `identification_number` (alias retrocompatible), email, teléfono, dirección, `is_active`.
- **sales**: usuario, `business_id`, `customer_id` (nullable, `NULL` para ventas a consumidor final), `customer_name` (varchar 150, snapshot inmutable del comprador), `customer_document` (varchar 30, snapshot inmutable del comprador), fecha, subtotal, descuento (`discount` en pesos, `discount_percentage` en porcentaje 0-100 con CHECK constraint), total, método de pago (`cash`, `card`, `transfer`, `other`, `mixed`), status, notas.
- **sale_details**: venta, producto, cantidad, precio unitario, subtotal.
- **sale_payments**: `business_id` (FK), `sale_id` (FK cascade), `method` (`PaymentMethod`: `cash`, `card`, `transfer`, `other`), `amount` (decimal 12,2 con CHECK `amount > 0`), `reference` (varchar 60 nullable), `cash_received` (decimal 12,2 nullable), `change_given` (decimal 12,2 nullable, CHECK `cash_received IS NULL OR cash_received >= amount`).
- **expenses**: `business_id` (FK), `supplier_id` (FK nullable), `invoice_number` (varchar 50 nullable), `issue_date` (date), `due_date` (date nullable), `category` (enum: `merchandise`, `utilities`, `rent`, `supplies`, `payroll`, `other`), `description` (varchar 255 nullable), `amount` (decimal 12,2 con CHECK `amount > 0`), `status` (enum: `paid`, `pending` default `pending`), `paid_at` (date nullable), `payment_method` (`PaymentMethod` enum nullable), `attachment_path` (varchar 255 nullable), `attachment_original_name` (varchar 255 nullable), `created_by` (FK users), `deleted_at` (soft deletes) y timestamps.
- **inventory_movements**: producto, tipo (entrada/salida/ajuste), cantidad, motivo, usuario, fecha, referencia a venta (si aplica).

## Estrategia multi-tenant
Multi-tenancy **lógico** mediante columna `business_id` en toda tabla tenant-aware, sobre una única base compartida.
Componentes de implementación canónicos en el código:
- **Global Scope**: `App\Models\Scopes\TenantScope` (asociado mediante el trait `App\Models\Concerns\BelongsToTenant`).
- **Middleware**: `App\Http\Middleware\SetCurrentTenant` (administra el contexto mediante `App\Services\Tenant\TenantManager`).
- **Policies**: Verificación explícita de `business_id` y rol en `App\Policies\*`.
Reglas de aplicación (Global Scope + Middleware + Policy) → `.agents/rules/02-database.md`.

## Reglas de inventario y consistencia
- Toda modificación de inventario pasa por `inventory_movements` (trazabilidad).
- Operaciones críticas (venta, ajuste de stock) dentro de una transacción DB.
- Prevención de stock negativo salvo regla de negocio explícita.
- Bloqueo pesimista (`lockForUpdate`) en confirmación de venta cuando el stock disponible es límite, para evitar condiciones de carrera (ejemplo: stock=1, dos ventas simultáneas — solo una debe tener éxito).
- **Regla contable de gastos**: El registro de gastos de categoría mercancía (`merchandise`) no genera movimientos de inventario ni altera el stock físico; este último se gestiona de forma estrictamente desacoplada en el módulo de Inventario.

## Índices y constraints mínimos
- FK con `ON DELETE RESTRICT` o `CASCADE` según corresponda (nunca eliminar en cascada datos financieros como `sales` o `expenses`).
- Índices en `sale_payments`: `(sale_id)`, `(business_id)`, y compuesto `(business_id, method)`.
- CHECK constraints en `sale_payments`: `amount > 0` y `cash_received IS NULL OR cash_received >= amount`.
- Índices en `expenses`: `(business_id, issue_date)`, `(business_id, status)`, `(business_id, supplier_id)`.
- Constraint UNIQUE compuesto en `expenses`: `(business_id, supplier_id, invoice_number)` cuando ambos existan.
- CHECK constraint en `expenses`: `amount > 0`.
- Índice compuesto único en (`business_id`, `sku`) para productos.
- Índice compuesto único en (`business_id`, `document`) para clientes.
- Índice en `business_id` en toda tabla tenant-aware, dado que es el filtro más frecuente.
