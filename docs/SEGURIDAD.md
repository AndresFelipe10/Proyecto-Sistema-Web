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

## Integridad financiera y cálculos autoritativos en backend
Los siguientes valores críticos NUNCA se confían al cliente y se calculan o resuelven exclusivamente en backend:
- **Precio unitario de productos**: El precio de cada línea de venta se obtiene directamente de `products.sale_price` en base de datos mediante bloqueo pesimista (`lockForUpdate`). Los inputs enviados por el navegador (`unit_price`) son ignorados en el cálculo.
- **Subtotales y Total de venta**: Calculados estrictamente en el servicio (`SaleService`) como `cantidad × precio_autoritativo`, sin aceptar valores derivados enviados desde el frontend.
- **Descuentos monetarios**: Se valida el porcentaje de descuento en rango (0-100) y se deriva el monto monetario en backend (`subtotal * (porcentaje / 100)`).
- **Descuento y verificación de Stock**: Validado bajo transacción con bloqueo pesimista (`lockForUpdate`) en `InventoryService`; se rechaza la transacción completa si el stock disponible es insuficiente.
- **Rango de cantidades**: La cantidad por ítem en venta está acotada numéricamente (`min: 1`, `max: 99999`) para prevenir desbordamientos o valores atípicos.

## Aislamiento entre emprendimientos
Ver `.agents/rules/02-database.md`. Debe estar cubierto por tests desde la Fase 5 del roadmap (`.agents/rules/05-testing.md`).

## Riesgos específicos del módulo IA
- **Prompt injection**: mitigado por la whitelist de intents en código, no por instrucciones al modelo.
- **Evasión de restricciones**: cualquier intent fuera de whitelist se rechaza sin ejecutar consulta.
- **Fuga entre tenants**: el `business_id` siempre lo inyecta Laravel, nunca el modelo ni el usuario en texto libre.
- **Alucinaciones**: la intención devuelta por el modelo siempre se valida antes de ejecutar cualquier acción.
- **Abuso de cuota**: rate limiting específico en el endpoint del asistente.

Detalle completo del módulo IA → `docs/MODULO_IA.md`.
