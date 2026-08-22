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

## Aislamiento entre emprendimientos
Ver `.agents/rules/02-database.md`. Debe estar cubierto por tests desde la Fase 5 del roadmap (`.agents/rules/05-testing.md`).

## Riesgos específicos del módulo IA
- **Prompt injection**: mitigado por la whitelist de intents en código, no por instrucciones al modelo.
- **Evasión de restricciones**: cualquier intent fuera de whitelist se rechaza sin ejecutar consulta.
- **Fuga entre tenants**: el `business_id` siempre lo inyecta Laravel, nunca el modelo ni el usuario en texto libre.
- **Alucinaciones**: la intención devuelta por el modelo siempre se valida antes de ejecutar cualquier acción.
- **Abuso de cuota**: rate limiting específico en el endpoint del asistente.

Detalle completo del módulo IA → `docs/MODULO_IA.md`.
