# Seguridad

## Autenticación y autorización
- Laravel Auth nativo (sesión), sin frameworks adicionales.
- Autorización siempre en backend vía Policies/Gates; nunca confiar en ocultar elementos de UI.
- Roles mínimos: Administrador, Empleado/Vendedor (permisos detallados en `docs/PLAN_PROYECTO.md`).

## OWASP — controles obligatorios
| Riesgo | Control |
|---|---|
| SQL Injection | Eloquent/Query Builder parametrizado exclusivamente; prohibidas queries crudas con input interpolado |
| XSS | Escape por defecto de Blade (`{{ }}`); prohibido `{!! !!}` con input de usuario |
| CSRF | `@csrf` en todo formulario; verificación de token en todas las rutas POST/PUT/DELETE |
| Mass Assignment | `$fillable` explícito o Form Requests; prohibido `$guarded = []` |
| Broken Access Control | Policy en cada recurso sensible, verificada en backend |
| Secretos expuestos | Todo secreto en `.env`, nunca en el repositorio; `.env.example` con placeholders |
| Errores en producción | `APP_DEBUG=false`; páginas de error genéricas; sin stack traces visibles |
| Logs | Sin contraseñas, tokens, ni datos sensibles en logs |
| Fuerza bruta | Rate limiting en login |
| Registro no autorizado | Registro público `/register` eliminado (404); alta controlada por Superadmin |
| Bypass de TenantScope por Superadmin | Middleware `EnsureUserIsSuperadmin` redirige a `/superadmin` en rutas de negocio |
| Manipulación de membresías | Restricción `UNIQUE(user_id)` en `business_user` y resolución autoritativa de tenant |
| Asignación masiva de privilegios | `is_superadmin` protegido fuera de `$fillable` en `User` |
| Falsificación de Consumidor Final | Prohibición en Form Requests (`not_in:222222222222`) de registrar clientes con documento reservado |
| XSS en autocomplete y modal POS | Renderizado estricto mediante `textContent` en Vanilla JS; prohibido `innerHTML` con datos del usuario |
| Alteración de histórico de ventas | Snapshot inmutable en `sales` (`customer_name`, `customer_document`); edición de cliente no afecta ventas |
| Abuso y DoS en búsqueda de clientes | Rate limiting `throttle:60,1`, longitud mínima de 3 caracteres y escape de comodines SQL (`!`, `%`, `_`) |
| Discrepancia en suma de pagos | Validación autoritativa en centavos enteros (`round($amount * 100) === round($total * 100)`) en `SaleService` |
| Inyección de cambio o vueltos | Cálculo autoritativo en backend (`change_given = cash_received - amount`); el cliente solo sugiere |
| Duplicación de efectivo | Máximo una sola línea en efectivo (`cash`) permitida por venta |
| XSS en referencia de pago | Sanitización con `strip_tags()` y acotado a 60 caracteres en backend |
| Distorsión de ingresos | Ingresos calculados exclusivamente como `SUM(sale_payments.amount)` (nunca sumando `cash_received`) |
| Inconsistencia de pago/stock | Transaccionalidad estricta (`DB::transaction`): falla en pago revierte venta, ítems y stock |
| Subida de archivos maliciosos en adjuntos | Almacenamiento en disco privado (`storage/app/private/expenses`), nombre con hash aleatorio, validación estricta de MIME real (PDF, JPG, PNG <= 3MB), rechazo de SVG, HTML, PHP y doble extensión |
| Descarga insegura de comprobantes | Streaming bajo `ExpensePolicy` con `Content-Disposition` y cabecera obligatoria `X-Content-Type-Options: nosniff` |
| Fuga de métricas financieras a vendedores | Enmascaramiento autoritativo en `DashboardService` y `/api/dashboard`; vendedores no reciben montos de ventas, gastos ni utilidades |
| Acceso no autorizado a gastos | Módulo exclusivo para Administradores de negocio bajo `ExpensePolicy` y middleware `role:admin`; empleados reciben 403 Forbidden |

## Seguridad de Gastos, Adjuntos y Métricas Financieras (Bloque D)
- **Almacenamiento Privado de Facturas**: Los comprobantes se guardan en el disco `'local'` (`storage/app/private/expenses`), inaccesible directamente por el servidor web o por enlaces simbólicos públicos (`storage:link`). La ruta en disco utiliza nombres completamente aleatorios (`Str::random(40)`) sin conservar el nombre original del archivo en la ruta física.
- **Validación Estricta de Mime y Extensiones**: Se bloquean ejecutables, scripts web (PHP, HTML, JS), imágenes vectoriales SVG (vectores de XSS) y archivos con doble extensión (`factura.php.pdf`).
- **Descarga Segura con Headers de Protección**: La ruta `GET /expenses/{expense}/attachment` está protegida por `ExpensePolicy`, verificando que el usuario sea administrador del mismo tenant. Se emite streaming con `Content-Disposition: inline` y cabecera obligatoria `X-Content-Type-Options: nosniff`.
- **Limpieza Física de Archivos**: Al eliminar un gasto o al actualizarlo reemplazando o retirando el adjunto, el archivo físico anterior es eliminado inmediatamente del almacenamiento privado.
- **Control de Acceso Basado en Roles (RBAC)**: El módulo de gastos está restringido exclusivamente al rol Administrador del negocio. Los empleados y vendedores reciben 403 Forbidden en todas las operaciones.
- **Enmascaramiento de Dashboard**: El endpoint `/api/dashboard` y la vista Blade filtran todos los montos monetarios de ventas, gastos y utilidades para usuarios no administradores.

## Integridad de Pagos Mixtos y Vueltos (Bloque C)
- **Comparación en Centavos Enteros**: Para prevenir vulnerabilidades derivadas de la imprecisión de coma flotante IEEE 754, la suma de los montos de pago (`amount`) se compara contra el total de la venta convirtiendo ambos a centavos enteros: `(int) round($amt * 100)`.
- **Cálculo Autoritativo de Vueltos**: El cliente jamás determina el vuelto. Si se envía `cash_received`, se valida que sea mayor o igual al monto en efectivo asignado y el backend calcula `change_given = cash_received - amount`. Si no se envía `cash_received`, se asume igual al monto (`change_given = 0.00`).
- **Regla Estricta de Efectivo Único**: Se permite un máximo de 5 líneas de pago, pero estrictamente un máximo de una sola línea con método `cash` para evitar complejidades y ambigüedades en el arqueo de caja y cálculo de cambio.
- **Sanitización de Referencias**: El campo `reference` (código de voucher, ID de transferencia o Nequi) se limpia con `strip_tags()` tanto en `prepareForValidation` como en `SaleService`, acotado a 60 caracteres.
- **Reportes Contables Reales**: Todos los reportes, resúmenes y exportaciones CSV calculan los ingresos por método de pago a partir del monto real aplicado (`sale_payments.amount`), ignorando por completo el efectivo recibido (`cash_received`), el cual incluye el vuelto devuelto al comprador.

## Integridad de Clientes, Snapshot Histórico y Consumidor Final (DIAN)
- **Consumidor Final Seguro**: Las ventas a consumidor final mantienen `customer_id = NULL` sin crear registros ficticios en la tabla `customers`. El snapshot registra `CONSUMIDOR FINAL` y `222222222222` según `config/sales.php`.
- **Inmutabilidad del Comprador**: Toda vista de detalle e impresión (ticket/carta) lee exclusivamente los campos copiados en la venta (`customer_name` y `customer_document`), asegurando que futuras ediciones o eliminaciones lógicas del cliente no alteren el histórico contable.
- **Unicidad de Documento por Tenant**: Índice compuesto único `(business_id, document)` en `customers` y validación de formato numérico / NIT (`^\d{5,15}(-\d)?$`).
- **Búsqueda POS Segura y Aislada**: Endpoint `GET /customers/search` con `throttle:60,1`, mínimo 3 caracteres, escape de comodines `!` `%` `_` para SQLite y MySQL, y aislamiento estricto por `business_id` vía `TenantScope`.

## Integridad de Plataforma y Aislamiento Multi-Tenant (SaaS Cerrado)
- **Superadmin de plataforma**: Flag booleano `users.is_superadmin` exclusivo de plataforma. El superadmin no pertenece a ningún tenant; el middleware `SetCurrentTenant` lo redirige a `/superadmin` para evitar que consulte datos de negocios con el Global Scope deshabilitado. Usuarios normales reciben 404 al intentar ingresar a `/superadmin`.
- **Aislamiento por membresía única autoritativa**: `SetCurrentTenant` resuelve el tenant directamente desde la membresía activa del usuario en `business_user`. Nunca confía en inputs de la request o valores en sesión. Si el usuario o el negocio están inactivos, la sesión web se destruye y los tokens Sanctum de API se revocan.
- **Creación interactiva sin secretos**: El comando `php artisan superadmin:create` solicita contraseña por CLI oculto con validación de 12 caracteres mínimos, prohibiendo seeders con credenciales expuestas en el código.
- **Forzado de cambio de contraseña**: Usuarios creados o restablecidos por superadmin tienen `must_change_password = true`, siendo redirigidos por middleware a cambiar su contraseña antes de poder operar el sistema.

## Integridad financiera y cálculos autoritativos en backend
Los siguientes valores críticos NUNCA se confían al cliente y se calculan o resuelven exclusivamente en backend:
- **Precio unitario de productos**: El precio de cada línea de venta se obtiene directamente de `products.sale_price` en base de datos mediante bloqueo pesimista (`lockForUpdate`). Los inputs enviados por el navegador (`unit_price`) son ignorados en el cálculo.
- **Subtotales y Total de venta**: Calculados estrictamente en el servicio (`SaleService`) como `cantidad × precio_autoritativo`, sin aceptar valores derivados enviados desde el frontend.
- **Descuentos monetarios**: Se valida el porcentaje de descuento en rango (0-100) y se deriva el monto monetario en backend (`subtotal * (porcentaje / 100)`).
- **Descuento y verificación de Stock**: Validado bajo transacción con bloqueo pesimista (`lockForUpdate`) en `InventoryService`; se rechaza la transacción completa si el stock disponible es insuficiente.
- **Rango de cantidades**: La cantidad por ítem en venta está acotada numéricamente (`min: 1`, `max: 99999`) para prevenir desbordamientos o valores atípicos.

## Aislamiento entre emprendimientos
Ver `.agents/rules/02-database.md`. Debe estar cubierto por tests desde la Fase 5 del roadmap (`.agents/rules/05-testing.md`).

## Auditoría de Seguridad Integral — Run 4 (Restaurantes, RBAC y Reglas Financieras)

En septiembre de 2026 se ejecutó la cuarta auditoría integral de seguridad conforme a `.agents/skills/security-audit/SKILL.md`, focalizada en las superficies introducidas por el vertical de Restaurante (Bloques R-A a R-H), el aprovisionamiento de colaboradores desde SuperAdmin y las reglas tributarias/financieras.

### Matriz de Hallazgos y Remediaciones (Run 4)

| ID | Severidad | Vulnerabilidad / CWE | Componente / Archivo | Vector de Ataque | Mitigación Aplicada | Estado |
|---|---|---|---|---|---|---|
| SEC-RUN4-001 | Alta | Stored DOM XSS (CWE-79) | `resources/views/restaurant/orders/create-delivery.blade.php` | Inyección de payload script/HTML en nombre, dirección o teléfono de cliente devuelto por autocomplete renderizado mediante `innerHTML`. | Reemplazo total de interpolación `innerHTML` por nodos DOM seguros (`document.createElement`) y asignación estricta vía `textContent`. | Resuelto |
| SEC-RUN4-002 | Media | Stored DOM XSS (CWE-79) | `orders/create.blade.php`, `orders/create-delivery.blade.php`, `orders/show.blade.php` | Nombres o categorías de platos con caracteres HTML interpolados en dropdown reactivo vía `itemBtn.innerHTML`. | Construcción estructurada de elementos DOM con asignación de texto mediante `textContent` para nombres, categorías y precios. | Resuelto |
| SEC-RUN4-003 | Media | Manipulación Arbitraria de Impuestos y Propinas (CWE-319 / CWE-835) | `app/Services/Sales/SaleService.php` | Envío de `tax_inc` arbitrario o desproporcionado, o inclusión de propinas e INC sobre base de consumo cero (`netFoodBase <= 0`). | Validación matemática autoritativa en backend: si `netFoodBase <= 0`, `service_fee = 0` y `tax_inc = 0`. Si `tax_inc > 0`, se verifica que coincida con el 8% estatutario (Art. 512-1 E.T.) y se fija autoritativamente. | Resuelto |
| SEC-RUN4-004 | Baja | Defensa en Profundidad en Autorización (CWE-284) | `RestaurantOrderController::addItems` | Dependencia exclusiva de la verificación en `AddOrderItemsRequest` sin chequeo explícito en controlador. | Incorporación de `Gate::authorize('update', $order)` directo en el método del controlador antes de invocar la capa de servicio. | Resuelto |
| SEC-RUN4-005 | Informativa | Aislamiento Multi-Tenant en Comandas y Salón (CWE-200 / CWE-284) | `RestaurantOrderPolicy`, `RestaurantTablePolicy`, `TenantScope` | Intento de IDOR en rutas de comandas (`show`, `kitchen-ticket`, `pre-bill`, `dispatch-ticket`, `cancel-empty`, `status`) y mesas (`edit`, `update`, `destroy`). | Verificación bidireccional: `TenantScope` prepuesto a `SubstituteBindings` (devuelve 404) y cláusula de Policy obligatoria `$model->business_id !== session('current_business_id')` (devuelve 403). | Verificado Conforme |
| SEC-RUN4-006 | Informativa | Inmunidad a Escalada de Privilegios en SuperAdmin (CWE-269) | `StoreTenantUserRequest`, `BusinessController::storeUser` | Inyección de `is_superadmin=true` o `role=superadmin` al dar de alta colaboradores de negocio. | `is_superadmin` excluido de `$fillable`, `role` acotado a `admin|employee`, asignación explícita `is_superadmin = false` y `must_change_password = true`. | Verificado Conforme |
| SEC-RUN4-007 | Informativa | Escape en Plantillas de Impresión Térmica 80 mm (CWE-79) | `kitchen-ticket`, `pre-bill-ticket`, `dispatch-ticket`, `print-receipt` | Inyección de etiquetas maliciosas en notas de cocina, observaciones de mesa o nombres de clientes. | Uso exclusivo de directiva Blade `{{ }}` (escape nativo `htmlspecialchars`) y cero ocurrencias de `{!! !!}` con datos de usuario. | Verificado Conforme |

## Riesgos específicos del módulo IA
- **Prompt injection**: mitigado por la whitelist de intents en código, no por instrucciones al modelo.
- **Evasión de restricciones**: cualquier intent fuera de whitelist se rechaza sin ejecutar consulta.
- **Fuga entre tenants**: el `business_id` siempre lo inyecta Laravel, nunca el modelo ni el usuario en texto libre.
- **Alucinaciones**: la intención devuelta por el modelo siempre se valida antes de ejecutar cualquier acción.
- **Abuso de cuota**: rate limiting específico en el endpoint del asistente.

Detalle completo del módulo IA → `docs/MODULO_IA.md`.
