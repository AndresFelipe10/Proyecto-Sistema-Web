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
- **Administrador**: gestión completa del emprendimiento (usuarios, productos, categorías, inventario, ventas, clientes, proveedores, reportes, dashboard, configuración).
- **Empleado/Vendedor**: consulta de productos, registro de ventas, consulta de clientes y disponibilidad.

## Módulos
1. Autenticación
2. Emprendimientos
3. Usuarios y roles
4. Categorías
5. Productos
6. Inventario
7. Ventas
8. Clientes
9. Proveedores
10. Dashboard
11. Reportes
12. Panel inteligente de inventario y alertas (reglas determinísticas)
13. Módulo de consultas en lenguaje natural (IA, opcional)

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
