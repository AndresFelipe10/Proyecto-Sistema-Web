# Plan del Proyecto

> Esta es la **fuente principal de verdad** del proyecto. Ante cualquier duda de alcance u objetivo, este documento prevalece.

## Objetivo
Diseñar y desarrollar una aplicación web que permita a pequeños emprendimientos de Cali, Colombia gestionar de manera centralizada sus ventas e inventario, mejorar el control de sus productos y generar información útil para apoyar la toma de decisiones.

## Contexto
Proyecto académico de Ingeniería de Sistemas, construido con estándares suficientemente profesionales para uso real por pequeños emprendimientos — no una demo de escritorio. Los emprendimientos hoy gestionan sus operaciones con cuadernos, hojas de cálculo o herramientas sin integración; el sistema centraliza productos, inventario, ventas, clientes, proveedores, usuarios, reportes, indicadores y alertas.

## Alcance
- Aplicación web real, multiusuario, multiemprendimiento, persistente, segura, desplegable.
- Núcleo funcional independiente de cualquier proveedor de IA.
- Módulo adicional de consultas en lenguaje natural, opcional y desacoplado.
- Fuera de alcance en esta etapa: apps móviles nativas, microservicios, frontend SPA (React/Vue/Angular), múltiples proveedores de IA simultáneos.

## Usuarios
- **Superadministrador de plataforma**: gestión global de la plataforma SaaS (creación, edición, suspensión y reactivación de negocios y sus administradores; métricas globales). No pertenece a ningún negocio y no accede a rutas operativas de tenants.
- **Administrador de negocio**: gestión completa de su propio emprendimiento (datos de "Mi negocio", usuarios/equipo, productos, categorías, inventario, ventas, clientes, proveedores, reportes, dashboard, configuración). Un usuario pertenece a un único negocio.
- **Empleado/Vendedor**: consulta de productos, registro de ventas, consulta de clientes y disponibilidad.

## Módulos
1. Autenticación (SaaS cerrado con canal de WhatsApp y cambio forzado de contraseña inicial)
2. Mi negocio (administración del emprendimiento propio) y Portal Superadmin (`/superadmin`)
3. Usuarios y roles (gestión de equipo interno del negocio)
4. Categorías
5. Productos
6. Inventario
7. Ventas y Punto de Venta (con pagos mixtos y vueltos)
8. Clientes
9. Proveedores
10. Gastos y facturas de compra (exclusivo Administrador)
11. Dashboard gerencial y financiero
12. Reportes
13. Panel inteligente de inventario y alertas (reglas determinísticas)
14. Módulo de consultas en lenguaje natural (IA, opcional)

## Requisitos clave
- Multi-tenancy lógico vía `business_id` sobre una única base MySQL, con aislamiento garantizado en backend.
- Persistencia real en MySQL en desarrollo, pruebas y producción, configurable solo vía `.env`.
- El cliente final no instala PHP, MySQL, Laravel, XAMPP, Docker ni Nginx — solo navegador e internet.
- El núcleo debe operar al 100% con el módulo IA desactivado.

## Frontend y Experiencia de Usuario (UX)
- **Estructura de navegación**: Navbar superior (logo, emprendimiento activo, usuario y logout) + sidebar lateral izquierdo fijo con módulos según rol y scroll vertical interno (`overflow-y: auto`).
- **Diseño responsive**: Sidebar colapsable en móvil/tablet accionado mediante botón hamburguesa en la barra superior con componente Offcanvas de Bootstrap 5.
- **Jerarquía y legibilidad**: Tipografía moderna (Plus Jakarta Sans), paleta sobria con acento índigo (`#4f46e5`), resaltado visual del módulo activo y cards con sombras sutiles.


## Arquitectura general
Resumen ejecutivo: Docker (`app` + `nginx` + `db`) en desarrollo; en producción, Internet → HTTPS → Nginx → PHP-FPM/Laravel → MySQL. Detalle completo → `docs/ARQUITECTURA.md`.

## Roadmap
Fases 0 a 17, con dependencias y criterios de aceptación → `docs/ROADMAP.md`.

## Criterios de aceptación globales
El proyecto se considera exitoso cuando, con datos reales de prueba, se puede demostrar el flujo completo: registro → creación de emprendimiento → login → gestión de productos e inventario → ventas que actualizan stock correctamente → gestión de clientes → dashboard funcional → alertas de stock con recomendación de reposición → reportes → consultas en lenguaje natural resueltas en modo solo lectura y con aislamiento por `business_id` garantizado → sistema operando íntegramente aunque el proveedor de IA no esté disponible → base de datos persistente → sistema desplegable sin herramientas técnicas del lado del cliente → proveedor de IA reemplazable sin reescribir el núcleo.

## Correcciones pre-despliegue (posteriores a Fase 17)
Ajustes de UX y negocio detectados en la validación final, previos al despliegue a producción:

1. **Descuento en porcentaje**: Columna aditiva `sales.discount_percentage` con CHECK constraint MySQL (0-100). El formulario POS recibe porcentaje en vez de monto, el backend calcula el valor monetario. Ambos datos (% y $) visibles en todas las vistas.
2. **Etiqueta "Consumidor Final"**: Reemplazo del texto "Venta al mostrador" / "Mostrador" por "Consumidor Final" en todas las vistas, reportes, CSV y módulo IA. El mecanismo subyacente (`customer_id = null`) no se modifica.
3. **Impresión de comprobantes**: Dos vistas dedicadas (`print-invoice` para carta/A4 y `print-receipt` para ticket térmico 80mm) con CSS `@media print` y `window.print()`, sin librería PDF. Protegidas por `SalePolicy::view()`.
4. **Transformación SaaS Cerrado & Superadmin (Bloque A)**: Registro público `/register` desactivado (404); alta asistida vía soporte WhatsApp institucional; restricción `UNIQUE(user_id)` en membresías; rol Superadmin de plataforma desacoplado de tenants y flujo de forzado de cambio de primera contraseña (`must_change_password`).
5. **Clientes, Snapshot Histórico y Consumidor Final DIAN (Bloque B)**: Configuración centralizada `config/sales.php` (`default_customer_name = 'CONSUMIDOR FINAL'`, `default_customer_document = '222222222222'`); snapshot inmutable en `sales` (`customer_name`, `customer_document`); prohibición de registro manual de cliente con documento DIAN 222222222222; índice compuesto `(business_id, document)` en `customers`; autocomplete POS con debounce de 280ms y modal de alta rápida vía `fetch` seguro con renderizado `textContent`; rotulación legal de comprobantes con leyenda `config('sales.legal_disclaimer')`.
6. **Pagos Mixtos y Vueltos Autoritativos (Bloque C)**: Enum PHP `PaymentMethod`; tabla aditiva `sale_payments` con hasta 5 líneas combinadas; regla de máximo una sola línea de efectivo (`cash`); atajos rápidos de billetes en POS; cálculo autoritativo de vueltos en backend y validación de suma exacta en centavos enteros; backfill idempotente retrocompatible para ventas históricas.
7. **Gastos, Facturas de Compra, Adjuntos Privados y Dashboard Financiero (Bloque D)**: Módulo de gastos exclusivo para administradores (`ExpensePolicy`); regla contable que desacopla gastos de mercancía del inventario físico; almacenamiento seguro de adjuntos en disco privado local con nombres aleatorios y streaming con cabecera `nosniff`; acción rápida "Marcar como pagada"; estado dinámico "Vencida" para cuentas por pagar; integración de gastos y saldo pendiente en la vista del Proveedor; métricas financieras protegidas en Dashboard (`America/Bogota`) con ocultamiento total de ingresos, gastos y utilidad neta para vendedores; y respaldo de infraestructura consolidado empaquetando base de datos y adjuntos privados.
