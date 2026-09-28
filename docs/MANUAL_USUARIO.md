# Manual de Usuario

> Guía básica de uso de **PuntoStock**, sistema de gestión de ventas e inventario para pequeños emprendimientos.

## Registrar una venta

1. En el sidebar, haz clic en **Ventas** y luego en **Nueva Venta**.
2. Busca productos por nombre o SKU en la barra de búsqueda — haz clic para agregarlos al carrito.
3. Ajusta la cantidad de cada producto según la venta real.
4. **Selección del cliente**:
   - Por defecto está seleccionado el chip **"Consumidor Final — 222222222222"** (conforme a los estándares de la DIAN).
   - Para asociar un cliente registrado, escribe al menos 3 caracteres de su nombre o documento en el buscador interactivo y selecciónalo de los resultados.
   - Si el cliente no existe aún, haz clic en el botón **"+"** para abrir el modal de creación rápida, completa los datos y se vinculará automáticamente.
   - Para volver a Consumidor Final, haz clic en el botón **"X"** del chip del cliente.
5. Si aplica un descuento, ingresa el **porcentaje (%)** en el campo "Descuento" — el sistema calcula automáticamente el monto en pesos y lo muestra debajo del campo.
6. **Métodos de pago y cálculo de vuelto**:
   - **Pago simple en efectivo (1 clic)**: Por defecto, el sistema asigna el total completo a la línea de Efectivo.
   - **Cálculo de vuelto / cambio**: En la línea de efectivo, ingresa el monto entregado por el cliente en el campo "Paga con / Recibido" o utiliza los botones de atajo rápido (**Exacto**, **$10.000**, **$20.000**, **$50.000**, **$100.000**). El sistema mostrará un indicador dinámico en verde con el vuelto a entregar o en rojo si el dinero es insuficiente.
   - **Pagos mixtos (múltiples formas de pago)**: Haz clic en **"+ Agregar método de pago"** para registrar combinaciones (por ejemplo, $30.000 por Transferencia/Nequi con su respectivo código de comprobante y $15.000 en Efectivo). Puedes registrar hasta 5 líneas de pago distintas.
   - El sistema valida en tiempo real que la suma de todos los montos cubra el 100% exacto del total de la venta; el botón **Registrar Venta** se habilitará únicamente cuando el monto esté totalmente cubierto.
7. Haz clic en **Registrar Venta**. El sistema descuenta automáticamente el stock, guarda una copia histórica inalterable de los datos del cliente y genera los registros de pago correspondientes.

## Imprimir comprobante de venta

Después de registrar una venta (o desde el detalle de cualquier venta completada), puedes imprimir un comprobante en **dos formatos** (ambos desglosan los métodos de pago aplicados, efectivo recibido y vueltos, e incluyen la leyenda legal obligatoria: *"Comprobante interno de venta. No reemplaza la factura electrónica de venta ante la DIAN."*):

### 🖨️ Comprobante de venta (tamaño carta / A4)
- Haz clic en el botón **"Imprimir comprobante (carta)"** (ícono de impresora).
- Se abre en una nueva pestaña con el formato formal: encabezado del negocio (nombre, NIT, dirección), datos del comprador, tabla de productos con columnas amplias, totales destacados y pie con la leyenda legal.
- El navegador abrirá automáticamente el diálogo de impresión. Selecciona tu impresora o "Guardar como PDF".
- **Úsala cuando**: el cliente solicita un documento formal, para archivo contable, o para entregar una copia al cliente con membrete del negocio.

### 🧾 Comprobante de venta (ticket térmico 80mm)
- Haz clic en el botón **"Imprimir comprobante (ticket)"** (ícono de recibo).
- Se abre en una nueva pestaña con formato compacto: ancho de 80mm, tipografía monoespaciada, layout vertical de una columna — optimizado para impresoras POS térmicas.
- El navegador abrirá automáticamente el diálogo de impresión. Selecciona tu impresora térmica.
- **Úsalo cuando**: estés en el punto de venta del día a día y necesites un recibo rápido para el cliente.

> **Nota**: Si no tienes impresora térmica, puedes verificar el formato del ticket usando la opción "Guardar como PDF" y configurando un tamaño de papel personalizado de 80mm de ancho en el diálogo de impresión de Chrome/Edge.

## Consultar ventas anteriores

1. En el sidebar, haz clic en **Ventas** para ver el historial completo.
2. Usa los filtros (fecha, método de pago, estado, búsqueda) para encontrar ventas específicas.
3. Haz clic en **"Ver"** para acceder al detalle y los botones de impresión.

## Mi Negocio (solo administradores)

1. En el sidebar, haz clic en **Mi negocio**.
2. Puedes consultar y actualizar el **nombre comercial**, el **NIT** y el **teléfono de contacto** de tu emprendimiento.
3. El estado de la cuenta es gestionado por la plataforma; si requieres cambios adicionales o soporte, contacta a la administración.

## Equipo de Trabajo

1. En el sidebar, haz clic en **Equipo**.
2. Como administrador puedes crear cuentas para tus vendedores/empleados.
3. Cada usuario debe tener un correo electrónico único que no esté en uso.
4. Puedes activar o desactivar el acceso de tus empleados cuando sea necesario.

## Soporte y Recuperación de Clave

- Si olvidaste tu contraseña o requieres soporte para tu negocio, utiliza el enlace directo de **WhatsApp** en la pantalla de inicio de sesión o comunícate con la línea oficial de soporte.
