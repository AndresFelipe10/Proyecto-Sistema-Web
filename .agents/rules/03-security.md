# Regla 03 — Seguridad

**Nivel de aplicación:** siempre.

Checklist completo y contexto → `docs/SEGURIDAD.md`

## Prohibiciones absolutas
- Prohibido `$guarded = []` en modelos Eloquent; usar siempre `$fillable` explícito o Form Requests.
- Prohibido interpolar input de usuario en queries crudas; usar exclusivamente Eloquent/Query Builder parametrizado.
- Prohibido `{!! !!}` en Blade con contenido proveniente de input de usuario.
- Prohibido omitir `@csrf` en formularios.
- Prohibido dejar `APP_DEBUG=true` en cualquier configuración de producción.
- Prohibido loguear contraseñas, tokens, API keys o datos sensibles.
- Prohibido hardcodear secretos; todo secreto vive en `.env`, nunca en el repositorio.

## Obligaciones
- Toda ruta de escritura y toda ruta sensible protegida por Policy o Gate correspondiente al rol.
- Rate limiting obligatorio en login y en el endpoint de consultas del asistente IA.
- Validación exhaustiva vía Form Requests en toda entrada de usuario.
- Páginas de error genéricas en producción; nunca stack traces visibles al usuario final.

## Específico del módulo IA
- La whitelist de intents (ver `.agents/rules/04-ai.md`) es la defensa real contra prompt injection, no las instrucciones dadas al modelo.
- Todo intent no reconocido se rechaza en código, nunca se ejecuta "por si acaso".
