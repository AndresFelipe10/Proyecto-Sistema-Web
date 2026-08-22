# Regla 00 — Proyecto y principio fundamental

**Nivel de aplicación:** siempre, en toda interacción con el repositorio.

## Qué es este proyecto
Sistema web para gestión de ventas e inventario en pequeños emprendimientos de Cali, Colombia. Proyecto académico construido con estándares profesionales de producción.

Contexto completo → `docs/PLAN_PROYECTO.md`

## Principio no negociable
```
NÚCLEO PRINCIPAL (Laravel + MySQL)
        +
MÓDULO OPCIONAL DE IA (Gemini)
```
El núcleo debe funcionar al 100% con la IA desactivada, sin API key, o con el proveedor caído. Nunca implementes ni modifiques el núcleo asumiendo que la IA está disponible.

## Reglas generales permanentes
- PROHIBIDO desarrollar más de una fase del roadmap a la vez (`docs/ROADMAP.md`).
- PROHIBIDO continuar a la siguiente fase si la fase actual tiene tests fallando.
- PROHIBIDO introducir tecnologías, paquetes o servicios no aprobados sin registrar la justificación en `docs/DECISIONES_TECNICAS.md` antes de usarlos.
- PROHIBIDO modificar una decisión arquitectónica fundamental sin documentarla como nueva entrada en `docs/DECISIONES_TECNICAS.md`.
- PROHIBIDO subir `.env`, API keys, tokens o cualquier secreto al repositorio.
- OBLIGATORIO analizar el workspace antes de escribir código (no asumir que el repo está vacío).
- OBLIGATORIO ejecutar pruebas, documentar y hacer commit al cierre de cada fase antes de avanzar.
- Ante ambigüedad, prioridad: **seguridad > integridad de datos > simplicidad > velocidad**.

## Decisiones fundamentales que NO se eliminan sin razón real y documentada
Laravel, PHP, MySQL, Docker, Nginx, Blade, Bootstrap, multiusuario/multiemprendimiento vía `business_id`, persistencia real en MySQL, dashboard, panel inteligente de inventario, alertas de reposición, módulo IA aislado y desactivable, Gemini como proveedor inicial, IA solo lectura, function calling / structured output, ausencia de SQL arbitrario generado por IA, fallback cuando la IA no esté disponible, capacidad futura de reemplazar Gemini.
