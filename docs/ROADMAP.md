# Roadmap del Proyecto

Fases en orden de desarrollo. Ninguna fase comienza sin que la anterior tenga sus criterios de aceptación cumplidos y sus tests pasando (`.agents/rules/05-testing.md`).

## Estado de Ejecución de Fases

| # | Fase | Depende de | Criterio de aceptación | Estado | Entregable / Verificación |
|---|---|---|---|:---:|---|
| 0 | Análisis y planificación | — | Workspace inspeccionado, `docs/` y `.agents/` existentes y coherentes | ✅ Completada | Estructura base de `docs/` y `.agents/` configurada y alineada con el alcance. |
| 1 | Inicialización del repositorio | 0 | Repo limpio, `.env.example` presente, sin secretos | ✅ Completada | Repositorio Git limpio, sin secretos versionados, `.env.example` con placeholders. |
| 2 | Docker + Laravel + Nginx + MySQL | 1 | `docker compose up` levanta los 3 servicios; Laravel responde; MySQL persistente | ✅ Completada | Contenedores `app`, `nginx`, `db` funcionales con volumen persistente en MySQL. |
| 3 | Modelo de datos + migraciones | 2 | Migraciones corren sin error; relaciones documentadas en `docs/BASE_DATOS.md` | ✅ Completada | 13 migraciones ejecutadas sin errores; entidades e integridad referencial documentadas. |
| 4 | Autenticación | 3 | Registro/login/logout/recuperación probados con feature tests | ✅ Completada | Flujos de autenticación completos y seguros probados con Feature Tests. |
| 5 | Emprendimientos + multi-tenancy | 4 | **Bloqueante**: tests de aislamiento entre emprendimientos pasando | ✅ Completada | Tenancy lógico por `business_id` garantizado con Eloquent Global Scope, Middleware y Policies. |
| 6 | Usuarios + roles | 5 | Empleado no accede a rutas de administrador (test de autorización) | ✅ Completada | RBAC con roles `admin` y `employee` verificados mediante tests de autorización. |
| 7 | Productos + categorías | 5 | CRUD y aislamiento cubiertos por tests | ✅ Completada | Catálogo completo con SKU único por tenant y validación estricta en Form Requests. |
| 8 | Inventario | 7 | Test de concurrencia (stock=1, dos ventas simultáneas) pasando | ✅ Completada | Historial de auditoría en `inventory_movements` y concurrencia pesimista (`lockForUpdate`). |
| 9 | Clientes + proveedores | 5 | CRUD y aislamiento cubiertos por tests | ✅ Completada | Directorio comercial aislado y protección contra eliminación accidental de clientes con ventas. |
| 10 | Ventas | 8, 9 | Venta actualiza stock correctamente; venta sin stock suficiente es rechazada | ✅ Completada | Transacciones atómicas de venta, validación de stock, numeración consecutiva y anulación. |
| 11 | Dashboard | 10 | Datos mostrados corresponden únicamente al `business_id` activo | ✅ Completada | Indicadores clave (KPIs), ventas del día, top productos y alertas operativas por tenant. |
| 12 | Panel inteligente de inventario | 8 | Clasificación normal/bajo/agotado correcta en casos de prueba | ✅ Completada | Clasificación determinística (Normal, Bajo Stock, Agotado) y cálculo de reposición. |
| 13 | Reportes | 10, 12 | Reportes respetan aislamiento (verificado con tests) | ✅ Completada | Reportes financieros, valoración de inventario y exportación a formato CSV sin fuga de datos. |
| 14 | Módulo de IA en lenguaje natural | 5, 7, 8, 9, 10, 12, 13 | Tests obligatorios de IA pasando; módulo desactivable sin romper el núcleo | ✅ Completada | Integración desacoplada (`AiProviderInterface`), whitelist de 9 intents, solo lectura y fallback. |
| 15 | Seguridad + testing integral | 14 | Checklist de `docs/SEGURIDAD.md` completo y verificado | ✅ Completada | Cabeceras HTTP, rate limiting anti brute-force, protección CSRF y vistas de error seguras. |
| 16 | Deployment | 15 | Guía de despliegue reproducible documentada en `docs/ARQUITECTURA.md` | ✅ Completada | `docker-compose.prod.yml`, `Dockerfile.prod`, scripts de backup/deploy y guía paso a paso. |
| 17 | Documentación final | 16 | `docs/` y `.agents/` consolidados y consultables sin contexto previo | ✅ Completada | `README.md` completo, `DatabaseSeeder` demo, ADRs actualizados y enlaces auditados. |
| B-A | Bloque A — SaaS Cerrado & Superadmin | 17 | Registro cerrado (/register 404), WhatsApp CTA, UNIQUE(user_id), Superadmin global, forzado de cambio de clave | ✅ Completada | Plataforma cerrada con aprovisionamiento interactivo seguro y redirección de roles sin bypass. |
| B-B | Bloque B — Clientes y Consumidor Final (DIAN) | B-A | Consumidor Final DIAN (222222222222), snapshot inmutable en sales, búsqueda/modal POS, rotulación legal | ✅ Completada | Snapshot histórico en ventas, índice compuesto (business_id, document), autocompletado POS XSS-free. |
| B-C | Bloque C — Múltiples Métodos de Pago por Venta | B-B | Tabla sale_payments, pagos mixtos, validación autoritativa en backend, reportes | ✅ Completada | Enum PaymentMethod, tabla sale_payments aditiva con backfill idempotente, pagos mixtos (hasta 5 líneas, máx 1 efectivo), cálculo autoritativo de cambio/vueltos, interfaz POS reactiva, comprobantes y reportes basados en montos aplicados. |
| B-D | Bloque D — Gastos, facturas de compra y dashboard | B-C | CRUD gastos, almacenamiento privado con hash, descarga segura nosniff, estado vencida dinámico, métricas financieras protegidas en dashboard, 403 vendedores, backup consolidado | ✅ Completada | Módulo de Gastos exclusivo admin, regla contable de inventario desacoplado, almacenamiento seguro de adjuntos, integración en detalle proveedor, métricas protegidas en Dashboard y API, respaldo consolidado y 218 tests pasando. |
| R-A | Bloque R-A — Perfil Tenant, Motor de Insumos/Recetas y Precisión Decimal | B-D | Perfil `business_type` (retail vs restaurant), `product_type`, `DECIMAL(12,3)` en stock y movimientos, recetas e insumos con deducción transaccional atómica en `SaleService`, reversión simétrica en anulación, 0 stock negativo y 254 tests en verde | ✅ Completada | Perfil restaurante desacoplado sin regresión para retail, precisión decimal de 3 dígitos, motor de recetas/fórmulas por porción, deducción atómica pesimista de insumos al vender platos, reversión automática en anulaciones y aislamiento multi-tenant estricto. |
| R-B | Bloque R-B — Salón, Mesas, Comandas y Ticket Térmico de Cocina (80 mm) | R-A | Tablas `restaurant_tables`, `restaurant_orders`, `restaurant_order_items`, mapa de salón responsivo, comanda por tandas, ticket térmico 80 mm sin precios, middleware `EnsureBusinessIsRestaurant` (403 a retail) y 267 tests en verde | ✅ Completada | Mapa visual interactivo de mesas, apertura secuencial de comandas (`ORD-XXXX`), adición de platos agrupados por tandas de cocina (`batch_number`), ticket de cocina térmico nativo (80 mm) sin precios ni datos fiscales, y restricción 403 para comercios retail. |
| R-C | Bloque R-C — Módulo de Domicilios, Para Llevar y Tirilla de Despacho (80 mm) | R-B | Formulario de pedidos delivery/takeout, recargo autoritativo de envío (`delivery_fee`), tablero Kanban de despacho, tirilla térmica 80 mm con datos de entrega y contraentrega, y 275 tests en verde | ✅ Completada | Captura ágil de pedidos a domicilio y para llevar, validación de fletes sin montos negativos, tablero operativo en 3 etapas (En Cocina, Despachado, Entregado), tirilla térmica de despacho (80 mm) con total contraentrega y aislamiento multi-tenant estricto. |
| R-D | Bloque R-D — Pre-cuenta, Liquidación en SaleService, Cierre de Mesa y Cuadre de Caja | R-C | Pre-cuenta informativa 80 mm con leyenda legal, liquidación atómica en SaleService con pagos mixtos y deducción de recetas, cierre de comanda y liberación de mesa, cuadre de caja discriminado por canal y flete, tarjetas operativas en dashboard y 283 tests en verde | ✅ Completada | Módulo completo de pre-cuenta y facturación formal integrada a SaleService, cierre atómico de comanda y mesa, cuadre de caja adaptativo por canal de venta (Salón, Domicilios, Para Llevar) y fletes de repartidores, conciliación de caja físico vs digital y cero regresión en Retail. |

---

## Verificación de Criterios de Aceptación Globales

Según [`docs/PLAN_PROYECTO.md`](PLAN_PROYECTO.md), el éxito del proyecto se evalúa frente al siguiente checklist global:

| Criterio Global | Estado | Evidencia de Cumplimiento |
|---|:---:|---|
| **Flujo comercial completo con datos reales** | ✅ Cumplido | Registro → creación de emprendimiento → login → productos → ventas con descuento y stock actualizado → clientes. |
| **Aislamiento multi-tenant por `business_id`** | ✅ Cumplido | Verificado con suite de tests de aislamiento (`TenantIsolationTest`, `ProductTest`, `SaleTest`, `RecipeManagementTest`, `RestaurantTableTest`, `KitchenOrderTest`, `DeliveryOrderTest`, `PreBillAndSettlementTest`, `RestaurantCashRegisterReportTest`). Ningún tenant accede a datos de otro. |
| **Concurrencia y consistencia de inventario** | ✅ Cumplido | Bloqueo pesimista probado en `InventoryMovementTest` y `RecipeInventoryDeductionTest` (stock límite/fraccional, ventas concurrentes: una aprobada, una rechazada sin saldo negativo). |
| **Alertas de reposición determinísticas** | ✅ Cumplido | Panel inteligente clasifica en Normal, Bajo Stock y Agotado con cálculo de reposición automática. |
| **Reportes y exportación CSV aislados** | ✅ Cumplido | Generación de reportes de ventas y valoración de stock con exportación CSV validada por tests. |
| **Consultas en lenguaje natural (IA)** | ✅ Cumplido | Módulo de IA con whitelist de intents, solo lectura, sin SQL arbitrario, `business_id` inyectado por backend y fallback controlado ante fallos. |
| **Independencia del núcleo frente a IA** | ✅ Cumplido | El núcleo funciona al 100% con `AI_MODULE_ENABLED=false` o sin API key de Gemini. |
| **Base de datos persistente** | ✅ Cumplido | Volumen Docker `mysql_data` (dev) y `mysql_prod_data` (prod) con scripts automatizados de respaldo y restauración. |
| **Despliegue sin herramientas técnicas en cliente** | ✅ Cumplido | El usuario final únicamente interactúa mediante navegador web bajo HTTP/HTTPS; toda la infraestructura corre contenerizada. |
| **Suite global de pruebas pasando** | ✅ Cumplido | **283 tests pasando (1364 assertions)** con 0 errores y 0 fallos. |
