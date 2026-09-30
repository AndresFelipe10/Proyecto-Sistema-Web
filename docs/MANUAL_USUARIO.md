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

## Gastos y Facturas de Compra (solo administradores)

1. En el sidebar, haz clic en **Gastos** (ubicado inmediatamente después de Proveedores).
2. Haz clic en **"Registrar Gasto"** para abrir el formulario de captura.
3. Completa los datos requeridos:
   - **Fecha de emisión**: Día en que se generó la compra o cobro del servicio.
   - **Fecha de vencimiento (opcional)**: Para facturas a crédito o servicios por pagar.
   - **Categoría**: Mercancía, Servicios públicos, Arriendo, Insumos / Papelería, Nómina u Otros.
     > **Nota contable**: El registro de un gasto de categoría *Mercancía* asienta el egreso financiero en caja/cuentas por pagar, pero **NO altera el inventario físico ni el stock de los productos**. La recepción y conteo físico de existencias se administra exclusivamente en el módulo de **Inventario**.
   - **Proveedor (opcional)**: Selecciona un proveedor registrado si aplica.
   - **Número de factura (opcional)**: Para trazabilidad contable.
   - **Monto ($)**: Valor total del gasto (debe ser mayor a $0).
   - **Estado**: *Pendiente* (cuenta por pagar) o *Pagada*. Si seleccionas *Pagada*, debes indicar la fecha y el método de pago utilizado.
   - **Comprobante / Factura adjunta (opcional)**: Puedes adjuntar un archivo digital en formato PDF, PNG o JPG (máximo 3 MB). El archivo se almacena en almacenamiento privado seguro y solo puede ser descargado por administradores autorizados.
4. **Acción rápida "Marcar como pagada"**: Desde el listado principal o la vista de detalle, puedes hacer clic en el botón de pago verde para registrar la fecha y método de pago en un solo paso.
5. **Cuentas vencidas**: Si una factura pendiente supera su fecha de vencimiento, el sistema la identificará automáticamente con el distintivo rojo **"Vencida"**.
6. **Gastos por Proveedor**: Al consultar el detalle de un proveedor en el menú **Proveedores**, los administradores encontrarán una pestaña especial con todas las facturas asociadas y el acumulado total **"Por pagar"**.

## Dashboard Gerencial y Financiero

1. Al iniciar sesión o hacer clic en **Dashboard**, los usuarios acceden al panel de control adaptado a su rol:
   - **Administradores**: Visualizan tarjetas financieras clave del mes actual (en horario de Colombia):
     - **Ventas del Mes**: Total acumulado de ventas completadas.
     - **Gastos / Compras del Mes**: Egresos totales del mes con el desglose secundario de montos pendientes por pagar.
     - **Utilidad neta estimada**: Margen operativo resultante (`Ventas - Gastos`), resaltado en rojo si es negativo.
     - **Stock Crítico y Catálogo**: Alertas de reposición inmediata y total de clientes.
   - **Vendedores / Empleados**: Visualizan un panel 100% operativo (número de ventas realizadas hoy, ventas del mes en transacciones y productos con existencias críticas), manteniendo en reserva confidencial todos los valores monetarios, ingresos y márgenes del negocio.

## Módulo de Restaurante y Gastronomía (solo comercios tipo Restaurante)

Si tu negocio tiene habilitado el perfil de **Restaurante**, dispondrás de módulos y herramientas especializadas:

### 1. Salón y Gestión de Mesas
1. En el menú lateral, accede a **Salón**.
2. Podrás visualizar en tiempo real el mapa de mesas con su estado operativo:
   - **LIBRE (Verde)**: Mesa desocupada lista para clientes. Haz clic en **"Abrir Mesa"** para tomar la primera comanda.
   - **OCUPADA (Azul)**: Mesa con consumo activo. Puedes consultar los platos ordenados y agregar nuevas tandas de cocina.
   - **EN COBRO (Amarillo)**: Mesa que ha solicitado la pre-cuenta informativa.
3. **Comandas por Tandas**: Cada adición de platos se agrupa cronológicamente en tandas (Tanda #1, Tanda #2, etc.), permitiendo enviar únicamente los ítems pendientes a cocina con el botón **"Enviar a Cocina (80 mm)"**.

### 2. Emisión de Pre-cuenta Informativa (80 mm)
1. Cuando el cliente en mesa solicita la cuenta antes de pagar, haz clic en **"Pre-cuenta (80 mm)"**.
2. El sistema cambia automáticamente el estado de la mesa y la comanda a **"En Cobro" (`billed`)** y abre la tirilla térmica en formato 80 mm.
3. **Aviso Legal**: La pre-cuenta incluye la leyenda obligatoria: *"ESTADO DE CONSUMO / PRE-CUENTA — DOCUMENTO INTERNO NO VÁLIDO COMO FACTURA O COMPROBANTE DE VENTA"*. No posee numeración consecutiva fiscal ni validez tributaria.

### 3. Domicilios y Pedidos Para Llevar
1. En el menú lateral, accede a **Domicilios**.
2. **Nuevo Pedido**: Haz clic en **"Nuevo Domicilio / Llevar"** e ingresa el cliente, teléfono, dirección de entrega, observaciones y el valor pactado del **Flete de Domicilio ($)**.
3. **Tablero Kanban de Despacho**: Gestiona las órdenes activas en tres columnas operativas:
   - **En Cocina / Preparación**
   - **En Despacho / Con Repartidor**
   - **Entregado (Pendiente de Liquidar)**
4. **Tirilla de Despacho (80 mm)**: Genera el ticket térmico con recuadro destacado de entrega y monto total contraentrega para el domiciliario.

### 4. Liquidación, Cobro y Cierre de Mesa
1. Al momento de cobrar una comanda de salón o domicilio, haz clic en **"Cobrar / Facturar"**.
2. Se desplegará la pasarela integrada de cobro:
   - **Cliente**: Asigna un cliente registrado o déjalo por defecto como **Consumidor Final DIAN (`222222222222`)**.
   - **Descuentos**: Ingresa el porcentaje de descuento si aplica.
   - **Flete de Domicilio**: Se adiciona automáticamente al total en órdenes de tipo delivery.
   - **Pasarela de Pagos Mixtos**: Puedes dividir la cuenta entre 1 y 5 líneas de pago (Efectivo, Nequi/Transferencia, Tarjeta o Datafono). Para el efectivo dispones de atajos rápidos ($20.000, $50.000, $100.000, Exacto) y cálculo automático del cambio/vuelto.
3. Al hacer clic en **"Confirmar Pago y Generar Factura"**:
   - Se crea la factura legal consecutiva inmutable (`VTA-YYYYMM-XXXX`).
   - Se descuentan automáticamente los insumos e ingredientes del inventario de acuerdo con las recetas de cada plato.
   - La comanda se cierra (`closed`) y la mesa se libera automáticamente a **LIBRE (`available`)**.

### 5. Cuadre de Caja Diario Adaptativo para Restaurantes
1. En el menú **Cuadre de Caja**, los restaurantes disfrutan de un desglose especializado:
   - **Discriminación por Canal de Venta**: Total vendido en *Ventas Salón (Mesas)*, *Ventas Domicilios* y *Ventas Para Llevar*.
   - **Recaudo de Domicilios / Fletes**: Fila dedicada con la suma exacta de los fletes cobrados en el día/turno, facilitando la rendición de cuentas con los mensajeros.
   - **Conciliación de Arqueo**: Total en *Efectivo Físico en Gaveta* (restando vueltos) frente a *Dinero Digital* (Nequi, Daviplata, Tarjetas).

## Soporte y Recuperación de Clave

- Si olvidaste tu contraseña o requieres soporte para tu negocio, utiliza el enlace directo de **WhatsApp** en la pantalla de inicio de sesión o comunícate con la línea oficial de soporte.
