# Módulo de IA — Consultas en Lenguaje Natural

> **El módulo IA es opcional y está desacoplado del núcleo.** Puede desactivarse por completo (`AI_MODULE_ENABLED=false` en `.env`) sin afectar ningún otro módulo del sistema.

## Objetivo
Permitir que el usuario formule preguntas en lenguaje natural (ej. *"¿Qué productos tienen stock bajo?"*) y reciba una respuesta útil, sin necesidad de conocer SQL, manteniendo el mismo nivel de seguridad y aislamiento que el resto del sistema.

## Arquitectura desacoplada
```
AiQueryService
       ↓
AiProviderInterface   (app/AI/Contracts)
       ↓
GeminiProvider          (app/AI/Providers)
```
El resto del sistema depende únicamente de `AiProviderInterface`. Esto permite sustituir Gemini por otro proveedor comercial, un modelo local u Ollama sin reescribir el núcleo de negocio.

## Flujo obligatorio
```
Usuario → pregunta en lenguaje natural
   ↓
AiQueryService → GeminiProvider (function calling / structured output)
   ↓
Intención estructurada (JSON), ej: {"intent": "low_stock_products", "filters": {...}}
   ↓
Laravel: validación contra whitelist de intents
   ↓
Laravel: autorización (usuario, rol, business_id inyectado por el sistema)
   ↓
Servicio del intent específico (solo lectura) → Eloquent/Query Builder
   ↓
MySQL
   ↓
Resultado → respuesta formateada y amigable
```

## Intenciones permitidas (whitelist — únicas soportadas en esta versión)
`list_customers`, `count_customers`, `list_products`, `low_stock_products`, `out_of_stock_products`, `top_selling_products`, `sales_summary`, `sales_by_period`, `inventory_summary`.

Cualquier intención fuera de esta lista se rechaza en código, sin excepción, aunque el modelo la devuelva.

## Restricciones no negociables
- Solo lectura: prohibido INSERT/UPDATE/DELETE/DROP/ALTER/TRUNCATE desde cualquier servicio invocado por el módulo IA.
- Sin acceso directo a MySQL, credenciales, ni SQL arbitrario generado por el modelo.
- El `business_id` de cada consulta lo determina Laravel a partir del usuario autenticado; nunca lo aporta el modelo ni el texto del usuario.
- Minimización de datos enviados al proveedor: solo la pregunta, el esquema de intents permitidos y metadatos mínimos — nunca contraseñas, hashes, tokens ni datos reales de clientes en bruto.

## Fallback
Si el proveedor no responde, hay timeout (8–10s) o error de cuota:
```
Usuario → pregunta → Gemini no disponible → mensaje controlado
```
Mensaje de referencia: *"El asistente de consultas no está disponible temporalmente. Puedes utilizar los módulos tradicionales de clientes, ventas e inventario."* Nunca se muestra un error técnico o stack trace al usuario final.

## Historial (si se implementa)
Guardar únicamente: pregunta, intención detectada, fecha, usuario, `business_id`, estado, tiempo de respuesta. Nunca la respuesta cruda del proveedor con datos sensibles. Debe respetar aislamiento por `business_id` igual que cualquier otra tabla.

## Costos, límites y privacidad
- Se usa el nivel gratuito de Gemini durante desarrollo; no se asume que sea permanente.
- Deben manejarse explícitamente: límites de cuota, timeout, rate limiting propio en el endpoint del asistente.
- Antes de usar datos de clientes reales en producción, revisar la política de privacidad vigente del proveedor de IA elegido y decidir la configuración contractual adecuada.

## Sustitución futura del proveedor
La arquitectura permite migrar de `Gemini Free` → `Gemini Paid` → otro proveedor → modelo local, implementando una nueva clase que satisfaga `AiProviderInterface`, sin tocar `AiQueryService` ni los Services de cada intent.
