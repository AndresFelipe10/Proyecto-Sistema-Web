# Workflow — Implementar una feature

Proceso a ejecutar cada vez que se implementa una funcionalidad nueva o se modifica una existente.

1. Consultar `.agents/rules/` relevantes a la feature (mínimo `00-project.md` y `01-architecture.md`; agregar `02-database.md`, `03-security.md` o `04-ai.md` según corresponda).
2. Consultar la documentación relevante en `docs/` (`PLAN_PROYECTO.md` para alcance, `ARQUITECTURA.md` para estructura, `BASE_DATOS.md` si toca datos, `MODULO_IA.md` si toca el asistente).
3. Inspeccionar el código existente relacionado antes de escribir nada nuevo (no asumir que no existe).
4. Comprender las dependencias: modelos, Policies, Services, rutas involucradas.
5. Implementar la funcionalidad siguiendo las reglas de arquitectura y seguridad aplicables.
6. Ejecutar la suite de pruebas relevante (ver `.agents/workflows/ejecutar-tests.md`).
7. Revisar seguridad: autorización, validación, aislamiento por `business_id` si aplica (ver `.agents/rules/03-security.md`).
8. Actualizar la documentación afectada en `docs/` si la feature cambia comportamiento documentado.
9. Verificar contra el criterio de aceptación de la fase correspondiente en `docs/ROADMAP.md`.
10. Preparar un resumen breve de los cambios (qué se implementó, qué tests se ejecutaron, qué documentación se actualizó) antes de hacer commit.
