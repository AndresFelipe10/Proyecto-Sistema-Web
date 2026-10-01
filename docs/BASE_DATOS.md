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
| `restaurant_tables` | Catálogo y estado de mesas de salón, por `business_id` |
| `restaurant_orders` | Comandas y pedidos de salón/domicilio por tandas, por `business_id` |
| `restaurant_order_items` | Platos y productos por tanda de comanda, por `business_id` |

*(Nota: Las tablas `recipes` y `recipe_items` fueron retiradas de forma segura en la migración `2026_09_30_000008` conforme a la Decisión Técnica 60, simplificando el catálogo a ítems de menú directo).*

Agregar tablas nuevas solo si son estrictamente necesarias, justificándolo en `docs/DECISIONES_TECNICAS.md`.

## Campos clave por tabla (mínimos)
- **users**: nombre, correo, contraseña, `is_superadmin` (boolean, default false, flag de plataforma), `must_change_password` (boolean, default false, forzado de actualización).
- **businesses**: nombre, nit, teléfono, dirección, `business_type` (enum: `retail`, `restaurant`, default `retail` con CHECK constraint), `status` (varchar 20, default 'active': 'active' / 'inactive'), `subscription_starts_at` (dateTime nullable), `subscription_ends_at` (dateTime nullable, index).
- **business_user**: `user_id`, `business_id`, `role_id`, `is_active`. Restricción `UNIQUE(user_id)` que asegura que un usuario pertenece a un único negocio.
- **products**: nombre, descripción, SKU/código (único por `business_id`, autogenerado opcional `MNU-XXXXXX` en restaurantes), categoría (opcional en restaurantes), precio de compra (opcional en restaurantes), precio de venta, `product_type` (enum: `standard`, `raw_material`, `dish`, default `standard`, nullable), `base_unit` (enum: `unit`, `gram`, `milliliter`, default `unit`, nullable), stock actual (`decimal 12,3`, default `0.000`), stock mínimo (`decimal 12,3`, default `0.000`), estado, `business_id`.
- **customers**: `business_id`, nombre, `document` (varchar 30, normalizado sin espacios/puntos, único por tenant), `identification_number` (alias retrocompatible), email, teléfono, dirección, `is_active`.
- **sales**: usuario, `business_id`, `customer_id` (nullable, `NULL` para ventas a consumidor final), `customer_name` (varchar 150, snapshot inmutable del comprador), `customer_document` (varchar 30, snapshot inmutable del comprador), `restaurant_order_id` (FK restaurant_orders nullable), `order_type` (enum `retail`, `table`, `delivery`, `takeout` nullable), `delivery_fee` (decimal 12,2 default 0.00 con CHECK `delivery_fee >= 0`), `service_fee` (decimal 12,2 default 0.00 con CHECK `service_fee >= 0`), `tax_inc` (decimal 12,2 default 0.00 con CHECK `tax_inc >= 0`), fecha, subtotal, descuento (`discount` en pesos, `discount_percentage` en porcentaje 0-100 con CHECK constraint), total, método de pago (`cash`, `card`, `transfer`, `other`, `mixed`), status, notas.
- **sale_details**: venta, producto, cantidad, precio unitario, subtotal.
- **sale_payments**: `business_id` (FK), `sale_id` (FK cascade), `method` (`PaymentMethod`: `cash`, `card`, `transfer`, `other`), `amount` (decimal 12,2 con CHECK `amount > 0`), `reference` (varchar 60 nullable), `cash_received` (decimal 12,2 nullable), `change_given` (decimal 12,2 nullable, CHECK `cash_received IS NULL OR cash_received >= amount`).
- **expenses**: `business_id` (FK), `supplier_id` (FK nullable), `invoice_number` (varchar 50 nullable), `issue_date` (date), `due_date` (date nullable), `category` (enum: `merchandise`, `utilities`, `rent`, `supplies`, `payroll`, `other`), `description` (varchar 255 nullable), `amount` (decimal 12,2 con CHECK `amount > 0`), `status` (enum: `paid`, `pending` default `pending`), `paid_at` (date nullable), `payment_method` (`PaymentMethod` enum nullable), `attachment_path` (varchar 255 nullable), `attachment_original_name` (varchar 255 nullable), `created_by` (FK users), `deleted_at` (soft deletes) y timestamps.
- **inventory_movements**: producto, tipo (entrada/salida/ajuste), cantidad (`decimal 12,3`), previous_stock (`decimal 12,3`), new_stock (`decimal 12,3`), motivo, usuario, fecha, referencia a venta (si aplica).
- **restaurant_tables**: `id`, `business_id` (FK cascade), `name` (varchar 50), `capacity` (int unsigned default 4 con CHECK `capacity > 0`), `status` (enum: `available`, `occupied`, `billed` default `available`), `is_active` (boolean default true), timestamps. UNIQUE(`business_id`, `name`).
- **restaurant_orders**: `id`, `business_id` (FK cascade), `table_id` (FK restaurant_tables nullable), `user_id` (FK users), `sale_id` (FK sales nullable), `order_number` (varchar 30), `order_type` (enum: `table`, `delivery`, `takeout` default `table`), `status` (enum: `open`, `in_kitchen`, `dispatched`, `delivered`, `closed`, `cancelled` default `open`), `customer_id` (FK nullable), `customer_name` (varchar 150 nullable), `delivery_fee` (decimal 12,2 default 0.00 con CHECK `delivery_fee >= 0`), `subtotal` (decimal 12,2), `total` (decimal 12,2), `notes` (varchar 500 nullable), `closed_at` (timestamp nullable), timestamps. UNIQUE(`business_id`, `order_number`).
- **restaurant_order_items**: `id`, `business_id` (FK cascade), `order_id` (FK restaurant_orders cascade), `product_id` (FK products restrict), `quantity` (`decimal 12,3` con CHECK `quantity > 0`), `unit_price` (decimal 12,2), `subtotal` (decimal 12,2), `notes` (varchar 255 nullable), `status` (enum: `pending`, `kitchen`, `served`, `cancelled` default `pending`), `printed_to_kitchen` (boolean default false), `batch_number` (int unsigned default 1), timestamps.

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
- **Venta directa de ítems de menú en Restaurantes**: Al confirmar la liquidación de una comanda de restaurante, los productos y platos se procesan de forma directa en `SaleService::processSale` con bloqueo pesimista `lockForUpdate`. Las bebidas y platos con control de stock descuentan sus unidades directamente, permitiendo a negocios de comidas rápidas y restaurantes operar con agilidad sin requerir formulación de recetas ni insumos fraccionarios.
- **Regla contable de gastos**: El registro de gastos de categoría mercancía (`merchandise`) no genera movimientos de inventario ni altera el stock físico; este último se gestiona de forma estrictamente desacoplada en el módulo de Inventario.

## Índices y constraints mínimos
- FK con `ON DELETE RESTRICT` o `CASCADE` según corresponda (nunca eliminar en cascada datos financieros como `sales` o `expenses`).
- CHECK constraint en `businesses`: `business_type IN ('retail', 'restaurant')`.
- CHECK constraints en `products`: `product_type IN ('standard', 'raw_material', 'dish')` y `base_unit IN ('unit', 'gram', 'milliliter')`.
- CHECK constraints en `sale_payments`: `amount > 0` y `cash_received IS NULL OR cash_received >= amount`.
- CHECK constraint en `expenses`: `amount > 0`.
- CHECK constraint en `restaurant_tables`: `capacity > 0`.
- CHECK constraint en `restaurant_orders`: `delivery_fee >= 0`.
- CHECK constraint en `restaurant_order_items`: `quantity > 0`.
- CHECK constraint en `sales`: `delivery_fee >= 0`.
- ENUM en `restaurant_orders.status`: `('open', 'in_kitchen', 'dispatched', 'delivered', 'billed', 'closed', 'cancelled')`.
- Columnas de Restaurante en `sales`: `restaurant_order_id` (FK nullable), `order_type` (`retail`, `table`, `delivery`, `takeout`), `delivery_fee` (decimal 12,2).
- Índices en `sale_payments`: `(sale_id)`, `(business_id)`, y compuesto `(business_id, method)`.
- Índices en `expenses`: `(business_id, issue_date)`, `(business_id, status)`, `(business_id, supplier_id)`.
- Constraint UNIQUE compuesto en `expenses`: `(business_id, supplier_id, invoice_number)` cuando ambos existan.
- Constraint UNIQUE compuesto en `recipes`: `(business_id, product_id)`.
- Constraint UNIQUE compuesto en `recipe_items`: `(recipe_id, ingredient_id)`.
- Constraint UNIQUE compuesto en `restaurant_tables`: `(business_id, name)`.
- Constraint UNIQUE compuesto en `restaurant_orders`: `(business_id, order_number)`.
- Índices en `restaurant_tables`: `(business_id, status)`.
- Índices en `restaurant_orders`: `(business_id, status)` y `(business_id, order_type)`.
- Índices en `restaurant_order_items`: `(business_id)`, `(order_id)`, `(product_id)`.
- Índices en `recipe_items`: `(business_id)`, `(recipe_id)`, `(ingredient_id)`.
- Índices en `sales`: `(business_id, restaurant_order_id)` y `(business_id, order_type)`.
- Índice compuesto único en (`business_id`, `sku`) para productos.
- Índice compuesto único en (`business_id`, `document`) para clientes.
- Índice en `business_id` en toda tabla tenant-aware, dado que es el filtro más frecuente.
