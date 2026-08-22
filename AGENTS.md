# AGENTS.md — Punto de entrada para agentes de IA

Este archivo es la puerta de entrada. Léelo primero, siempre, antes de tocar cualquier código.

## Qué es este proyecto
Sistema web de gestión de ventas e inventario para pequeños emprendimientos de Cali, Colombia. Detalle completo en `docs/PLAN_PROYECTO.md`.

## Fuente de verdad
- **Alcance y objetivo del proyecto** → `docs/PLAN_PROYECTO.md`
- **Arquitectura y estructura de carpetas** → `docs/ARQUITECTURA.md`
- **Modelo de datos y multi-tenancy** → `docs/BASE_DATOS.md`
- **Seguridad** → `docs/SEGURIDAD.md`
- **Módulo de IA** → `docs/MODULO_IA.md`
- **Por qué se tomó cada decisión técnica** → `docs/DECISIONES_TECNICAS.md`
- **Plan de fases y qué falta** → `docs/ROADMAP.md`

## Cómo debe comportarse el agente
- Reglas permanentes obligatorias → `.agents/rules/` (léelas todas antes de escribir código; son cortas y accionables por diseño).
- Procesos repetibles → `.agents/workflows/` (usar `implementar-feature.md` para cualquier funcionalidad nueva, `ejecutar-tests.md` tras cualquier cambio, `revisar-codigo.md` antes de cerrar una fase, `preparar-produccion.md` en la Fase 16).
- Procedimiento específico para extender el asistente de IA → `.agents/skills/ai-query-module/SKILL.md`.

## Qué debes leer antes de modificar código
1. Este archivo.
2. Todas las reglas en `.agents/rules/`.
3. `docs/ROADMAP.md` para saber en qué fase está el proyecto y cuál sigue.
4. El documento de `docs/` específico del módulo que vayas a tocar.

## Reglas críticas (resumen — la fuente completa está en `.agents/rules/`)
- El núcleo funciona sin el módulo IA. Nunca al revés.
- Aislamiento multi-tenant por `business_id` es innegociable y se prueba con tests desde la Fase 5.
- El módulo IA es solo lectura y nunca ejecuta SQL arbitrario.
- No se avanza de fase con tests fallando.
- No se agregan tecnologías ni se cambian decisiones fundamentales sin documentarlo en `docs/DECISIONES_TECNICAS.md`.
