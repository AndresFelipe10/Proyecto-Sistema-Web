---
name: ui-ux-design
description: Directrices de ergonomía táctil para puntos de venta (POS), tamaños de toque mínimos (44px), jerarquía visual y accesibilidad en Bootstrap 5 para PuntoStock.
---

# Guía de Ergonomía Táctil y UI/UX en PuntoStock

## 1. Principio Fundamental
Las interfaces de PuntoStock operan frecuentemente en entornos de alta velocidad: tabletas táctiles de meseros, terminales de caja (POS) en restaurantes y mostradores de comercio. La ergonomía táctil prima sobre el minimalismo excesivo de escritorio.

## 2. Restricciones Inmutables del Sistema
- **Paleta de Colores Corporativa Inmutable:**
  - **Tinta:** `#1B2A49` (Azul medianoche profundo para textos principales, cabeceras y contrastes).
  - **Azafrán:** `#E8A317` (Dorado cálido para acentos, alertas de cocina, llamadas de atención).
  - **Hueso:** `#F6F4EF` (Fondo neutro cálido para descanso visual).
- **Prohibido Rediseñar la Estructura Base:** No alterar barras laterales, navegación superior ni sistemas de grid establecidos.
- **Sin Librerías Externas Pesadas:** Uso exclusivo de Bootstrap 5 nativo, utilidades CSS limpias y Vanilla JavaScript interactivo.
- **100% Español Puro:** Prohibido el uso de términos anglosajones en botones, tooltips, modales o mensajes informativos.

## 3. Estándares Ergonómicos para Pantallas Táctiles y POS
1. **Área Mínima de Toque (Touch Target Size):**
   - Cualquier botón o elemento interactivo primario en pantalla táctil debe tener un tamaño mínimo de **44px × 44px** (según pautas WCAG 2.2 y ergonomía móvil).
   - En controles de cantidad (`[ - ]` y `[ + ]`), los botones deben tener `padding` suficiente para evitar toques fallidos.
2. **Jerarquía Visual y Ubicación de Acciones:**
   - Botón de acción principal (ej. "Confirmar y Enviar a Cocina", "Cobrar / Facturar") con color prominente (`btn-primary` o `btn-success`), etiqueta clara y posición visible sin scroll excesivo.
   - Acciones secundarias o de adición (ej. "+ Agregar otro plato") con contraste nítido (`btn-outline-primary` con icono descriptivo), evitando botones grises o desapercibidos.
3. **Selectores y Búsquedas Rápidas:**
   - En cartas extensas (más de 20 platos o productos), un `<select>` plano es ineficiente. Se debe acompañar de búsqueda en vivo insensible a mayúsculas/acentos y desplegable de resultados directos a un clic.
   - Agrupación semántica estricta por categoría (`<optgroup>`).
4. **Espaciado y Legibilidad:**
   - Separación adecuada entre filas y botones destructivos (`btn-outline-danger` para eliminar ítem) para evitar toques accidentales durante el servicio rápido.
   - Textos con contraste de color suficiente (WCAG AA) y pesos tipográficos claros (`fw-semibold`, `fw-bold`).
