# Decisiones Técnicas (ADR resumido)

Cada entrada documenta una decisión fundamental y su motivo. Nuevas decisiones relevantes se agregan aquí, nunca se cambian silenciosamente en el código.

| Decisión | Alternativa descartada | Motivo |
|---|---|---|
| Laravel 12 | PHP puro | Productividad, ecosistema maduro (Eloquent, Policies, testing), estándar de facto para este tipo de proyecto académico-profesional |
| Docker Compose | XAMPP | Reproducibilidad, paridad dev/producción, el cliente final no instala nada |
| Blade + Bootstrap 5 | React/Vue/Angular | El alcance no requiere SPA; menor complejidad, curva de aprendizaje y superficie de mantenimiento para el equipo actual |
| MySQL 8+ | PostgreSQL, SQLite | Estándar en hosting de bajo costo típico de pequeños emprendimientos; suficiente para el volumen de datos esperado |
| Multi-tenancy lógico (`business_id`) | Bases de datos separadas por tenant / schemas separados | Menor complejidad operativa y de costos para el volumen esperado de pequeños emprendimientos; el aislamiento se garantiza con Global Scope + Middleware + Policies |
| Módulo IA desacoplado (`AiProviderInterface`) | Acoplar directamente al SDK de Gemini | Permite sustituir de proveedor sin reescribir el núcleo; evita que la IA se convierta en dependencia crítica |
| Gemini como proveedor inicial | OpenAI, Claude, modelo local | Nivel gratuito disponible para etapa académica; decisión revisable, por eso la abstracción |
| IA en modo solo lectura | Permitir escritura vía IA desde el inicio | Reduce drásticamente la superficie de riesgo (alucinaciones, prompt injection) en la primera versión |
| Sin Redis/colas en el MVP | Procesamiento asíncrono de IA | La llamada síncrona con timeout corto es suficiente para el volumen esperado; se evita complejidad operativa innecesaria. Reevaluar solo si el tiempo de respuesta se vuelve un problema real medido en producción |
| Sin phpMyAdmin en el compose por defecto | Incluirlo siempre | No es necesario para el funcionamiento del sistema; puede agregarse como `docker-compose.override.yml` opcional si se requiere durante desarrollo |

> Cualquier decisión que reemplace o contradiga una fila de esta tabla debe agregarse como nueva fila, explicando el motivo del cambio — nunca se borra el historial de decisiones.
