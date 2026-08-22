# Regla 05 — Testing

**Nivel de aplicación:** al cierre de cada fase del roadmap y ante cualquier cambio en modelos, controladores, Policies o el módulo IA.

## Reglas
- PROHIBIDO avanzar de fase si existen tests fallando.
- Los tests de **aislamiento multi-tenant** son obligatorios desde la Fase 5 en adelante (no se pueden posponer al final del proyecto).
- Todo nuevo endpoint de escritura requiere al menos un feature test de autorización (rol correcto/incorrecto) y uno de aislamiento (`business_id` correcto/incorrecto).
- El módulo IA requiere, como mínimo y sin excepción, los siguientes casos de prueba:
  - Pregunta válida → intent correcto → datos solo del `business_id` del usuario.
  - Pregunta pidiendo datos sensibles → rechazada sin ejecutar consulta.
  - Pregunta que intenta referirse a otro emprendimiento → denegada.
  - Intent fuera de whitelist o entrada malformada → rechazada con mensaje controlado, nunca error 500.
  - Proveedor IA caído/timeout → fallback activado correctamente.
- Ante un test fallido: detener el desarrollo de la fase actual, diagnosticar, corregir, volver a ejecutar toda la suite (no solo el test corregido) antes de continuar.
