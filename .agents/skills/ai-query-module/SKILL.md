# Skill — Agregar un nuevo intent al módulo de IA

**Por qué existe esta skill:** agregar un nuevo intent al asistente de lenguaje natural es una operación que se repetirá múltiples veces durante la vida del proyecto (nuevas preguntas que el negocio quiera soportar) y tiene pasos fijos y riesgos de seguridad concretos si se hace mal (romper la whitelist, romper el aislamiento por `business_id`, o permitir escritura por accidente). Por eso se documenta como skill reutilizable en vez de reglas genéricas.

## Cuándo usar esta skill
Cuando se necesita que el asistente de IA soporte una nueva pregunta/intención que hoy no está en la whitelist de `docs/MODULO_IA.md`.

## Pasos
1. Definir el nombre del intent en `snake_case` (ej. `sales_by_customer`) y agregarlo a la whitelist en código (enum/registro central), nunca solo en la documentación.
2. Crear el DTO correspondiente en `app/AI/DTOs/` que represente la intención estructurada y sus filtros esperados.
3. Crear el Service del intent en `app/AI/Tools/` (o `app/Services/AI/`), **de solo lectura**: únicamente métodos `get()`, `first()`, `count()` o agregaciones. Prohibido cualquier método de escritura.
4. El Service debe recibir el `business_id` como parámetro obligatorio inyectado por Laravel — nunca debe leerlo de los filtros que vienen del modelo de IA.
5. Registrar el intent en el dispatcher de `AiQueryService` que traduce intención → Service.
6. Agregar validación de los parámetros/filtros del intent vía Form Request o validador equivalente antes de ejecutar el Service.
7. Escribir tests (obligatorio, sin excepción):
   - Caso feliz con datos de un `business_id`.
   - Caso de intento de acceso a datos de otro `business_id` (debe fallar/ignorarse).
   - Caso de filtros malformados (debe rechazarse con mensaje controlado).
8. Documentar el nuevo intent en `docs/MODULO_IA.md`, agregándolo a la tabla de whitelist.
9. Ejecutar la suite completa de tests del módulo IA (no solo el nuevo intent) antes de cerrar el cambio.

## Prohibido en esta skill
- Agregar un intent que requiera INSERT/UPDATE/DELETE.
- Permitir que el intent reciba un `business_id` desde el texto del usuario o desde el modelo de IA.
- Saltarse el paso de tests por considerarlo "un intent simple".
