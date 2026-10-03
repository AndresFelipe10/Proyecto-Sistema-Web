---
name: ui-ux-design
description: Directrices de ergonomía táctil para puntos de venta (POS), tamaños de toque mínimos (44px), jerarquía visual y accesibilidad en Bootstrap 5 para PuntoStock.
---

# Guía de Ergonomía Táctil y UI/UX Mobile POS en PuntoStock

## 1. Principio Fundamental
Las interfaces de PuntoStock operan frecuentemente en entornos de alta velocidad: tabletas táctiles de meseros, terminales de caja (POS) en restaurantes y mostradores de comercio, así como smartphones de administradores y cajeros. La ergonomía táctil y la scannability en movilidad priman sobre el minimalismo pasivo de escritorio, sin alterar jamás la experiencia de escritorio aprobada.

## 2. Restricciones Inmutables del Sistema
- **Paleta de Colores Corporativa Inmutable:**
  - **Tinta:** `#1B2A49` (Azul medianoche profundo para textos principales, cabeceras y contrastes).
  - **Azafrán:** `#E8A317` (Dorado cálido para acentos, alertas de cocina, llamadas de atención).
  - **Hueso:** `#F6F4EF` (Fondo neutro cálido para descanso visual).
  - **Superficies y Fondos:** Blanco `#FFFFFF` y fondo suave `#F8FAFC`.
- **Invarianza Absoluta de Escritorio (Desktop Invariance):** Queda terminantemente prohibido alterar la estructura, disposición o diseño en resoluciones `>= 992px` (`d-md-*` / `d-lg-*`). El diseño de escritorio ya está validado y aprobado.
- **Sin Librerías Externas Pesadas:** Uso exclusivo de Bootstrap 5 nativo, utilidades CSS limpias y Vanilla JavaScript interactivo. Cero dependencias pesadas de npm ni frameworks reactivos adicionales.
- **100% Español Neutro:** Prohibido el uso de términos anglosajones en botones, tooltips, modales, tablas o mensajes informativos.

## 3. Estándares Ergonómicos Mobile POS (UI/UX Pro Max & Impeccable)

### 3.1. Zona del Pulgar (Thumb Zone) y Tap Targets
1. **Dimensiones Mínimas de Toque (WCAG 2.2 AAA / Apple HIG / Material Design):**
   - Todo botón principal de acción rápida (ej. "+ Nueva Venta", "Cobrar", "Ver Comprobante", "Confirmar") debe tener una altura táctil mínima de **44px a 48px**.
   - Los controles de cantidad (`[ - ]` y `[ + ]` en steppers) deben medir mínimo **40px × 40px** con padding táctil amplio para evitar pulsaciones erróneas.
2. **Espaciado Anti-Error:**
   - Separación mínima de **8px** entre elementos interactivos táctiles adyacentes (`g-2` o `gap-2`).
   - Los botones destructivos (ej. "Anular", "Eliminar") deben situarse lejos del flujo táctil principal del pulgar o requerir confirmación modal explícita.

### 3.2. Top App Bar Limpio y Compacto (`< 768px`)
1. **Cero Fragmentación Vertical:**
   - La barra de navegación superior en móviles no debe partirse en múltiples filas torpes ni desalineadas.
2. **Estructura de 3 Elementos en Línea Única:**
   - **Izquierda:** Botón menú hamburguesa (apertura de offcanvas lateral).
   - **Centro:** Logotipo vectorial `PuntoStock` con tamaño proporcional (`size="sm"`).
   - **Derecha:** Avatar/botón desplegable de usuario unificado:
     - Al tocarlo, despliega un menú Bootstrap con: Negocio activo (`🏪 Nombre`), Usuario y Rol (`👤 Nombre - Administrador / Empleado`), Divisor y Cerrar Sesión en rojo (`🚪 Salir`).

### 3.3. Filtros Progresivos (Collapsible Filters Pattern)
1. **Economía de Pantalla en el Primer Pantallazo:**
   - Los formularios de filtros extensos (Buscar, Desde, Hasta, Método, Estado) consumen el 100% del viewport en un teléfono si se presentan abiertos.
2. **Estrategia Adaptativa:**
   - **En escritorio (`d-none d-md-block`):** Mantener la tarjeta de filtros horizontal abierta e interactiva.
   - **En móvil (`d-md-none`):**
     - Barra de búsqueda rápida de un toque con botón de envío y limpieza.
     - Botón colapsable secundario (`[🔍 Filtros avanzados (X activos)]`) con acordeón Bootstrap que expone selectores de fechas y estados bajo demanda.
     - Permite que el listado o las tarjetas de datos sean visibles inmediatamente sin scroll previo.

### 3.4. Adaptación Tabla a Tarjeta (Table-to-Card Pattern)
1. **Problema del Scroll Horizontal:**
   - En pantallas móviles (`< 768px`), las tablas anchas con múltiples columnas (`table-responsive`) obligan a desplazamientos laterales incómodos donde el cajero pierde el contexto del consecutivo o cliente.
2. **Patrón de Implementación Híbrido:**
   - **En escritorio (`d-none d-md-block`):** Conservar la tabla HTML completa con sus cabeceras, alineaciones monetarias y columnas completas.
   - **En móvil (`d-md-none`):** Renderizar una lista vertical de tarjetas táctiles (`card card-custom p-3`):
     - **Cabecera de Tarjeta:** Consecutivo/Código en tipografía monoespaciada negrita (`VTA-202610-0005`) + Badge de estado contextual (`Completada`, `Anulada`).
     - **Cuerpo:** Datos esenciales ordenados jerárquicamente: Cliente y Fecha/Hora legible.
     - **Pie:** Badge de método de pago, Total destacado en negrita (`fs-5` o `$25.000`) y botón de acción táctil ancho completo (`[Ver Comprobante]`) con altura mínima de 42px-44px.

## 4. Búsqueda y Navegación Rápida
- En cartas o catálogos extensos (más de 20 ítems), implementar búsqueda instantánea insensible a acentos/mayúsculas y agrupación semántica (`<optgroup>`).
- En pestañas de catálogo híbrido (Restaurante: Menú vs. Mercancía), proveer atajos de venta directa (`Vender Mercancía`) para acortar la fricción operativa.
