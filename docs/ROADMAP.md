# Roadmap

Fases en orden recomendado. Pueden reordenarse solo si se detecta una dependencia técnica mejor, documentando el motivo en `docs/DECISIONES_TECNICAS.md`. Ninguna fase comienza sin que la anterior tenga sus criterios de aceptación cumplidos y sus tests pasando (`.agents/rules/05-testing.md`).

| # | Fase | Depende de | Criterio de aceptación resumido |
|---|---|---|---|
| 0 | Análisis y planificación | — | Workspace inspeccionado, `docs/` y `.agents/` existentes y coherentes |
| 1 | Inicialización del repositorio | 0 | Repo limpio, `.env.example` presente, sin secretos |
| 2 | Docker + Laravel + Nginx + MySQL | 1 | `docker compose up` levanta los 3 servicios; Laravel responde; MySQL persistente |
| 3 | Modelo de datos + migraciones | 2 | Migraciones corren sin error; relaciones documentadas en `docs/BASE_DATOS.md` |
| 4 | Autenticación | 3 | Registro/login/logout/recuperación probados con feature tests |
| 5 | Emprendimientos + multi-tenancy | 4 | **Bloqueante**: tests de aislamiento entre emprendimientos pasando |
| 6 | Usuarios + roles | 5 | Empleado no accede a rutas de administrador (test de autorización) |
| 7 | Productos + categorías | 5 | CRUD y aislamiento cubiertos por tests |
| 8 | Inventario | 7 | Test de concurrencia (stock=1, dos ventas simultáneas) pasando |
| 9 | Clientes + proveedores | 5 | CRUD y aislamiento cubiertos por tests |
| 10 | Ventas | 8, 9 | Venta actualiza stock correctamente; venta sin stock suficiente es rechazada |
| 11 | Dashboard | 10 | Datos mostrados corresponden únicamente al `business_id` activo |
| 12 | Panel inteligente de inventario | 8 | Clasificación normal/bajo/agotado correcta en casos de prueba |
| 13 | Reportes | 10, 12 | Reportes respetan aislamiento (verificado con tests) |
| 14 | Módulo de IA en lenguaje natural | 5, 7, 8, 9, 10, 12, 13 | Todos los tests obligatorios de IA pasando; módulo desactivable sin romper el núcleo |
| 15 | Seguridad + testing integral | 14 | Checklist de `docs/SEGURIDAD.md` completo y verificado |
| 16 | Deployment | 15 | Guía de despliegue reproducible documentada en `docs/ARQUITECTURA.md` |
| 17 | Documentación final | 16 | `docs/` y `.agents/` consolidados y consultables sin contexto previo |

Criterio final de éxito del proyecto completo → `docs/PLAN_PROYECTO.md`, sección "Criterios de aceptación globales".
