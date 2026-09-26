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
| `inventory_movements` | Historial de movimientos de stock (entradas/salidas/ajustes) |

Agregar tablas nuevas solo si son estrictamente necesarias, justificándolo en `docs/DECISIONES_TECNICAS.md`.

## Campos clave por tabla (mínimos)
- **products**: nombre, descripción, SKU/código (único por `business_id`), categoría, precio de compra, precio de venta, stock actual, stock mínimo, estado, `business_id`.
- **sales**: usuario, `business_id`, cliente, fecha, subtotal, descuento (`discount` en pesos, `discount_percentage` en porcentaje 0-100 con CHECK constraint), total, método de pago.
- **sale_details**: venta, producto, cantidad, precio unitario, subtotal.
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

## Índices y constraints mínimos
- FK con `ON DELETE RESTRICT` o `CASCADE` según corresponda (nunca eliminar en cascada datos financieros como `sales`).
- Índice compuesto o único en (`business_id`, `sku`) para productos.
- Índice en `business_id` en toda tabla tenant-aware, dado que es el filtro más frecuente.
