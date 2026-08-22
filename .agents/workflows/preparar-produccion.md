# Workflow — Preparar producción

Ejecutar antes de considerar el sistema listo para un despliegue real (Fase 16 del roadmap).

1. **`.env`**: confirmar que no hay secretos en el repositorio; `.env.example` actualizado con todas las variables necesarias y placeholders.
2. **Secretos**: API keys de Gemini y credenciales de MySQL solo en `.env` de producción, nunca versionadas.
3. **Docker**: revisar que `docker-compose.yml` use las imágenes y configuraciones apropiadas para producción (no montar código fuente como volumen de desarrollo si no corresponde).
4. **Migraciones**: confirmar que todas las migraciones corren limpio sobre una base vacía.
5. **Persistencia**: volumen de MySQL configurado correctamente; estrategia de backup documentada en `docs/ARQUITECTURA.md`.
6. **HTTPS / Nginx**: configuración de dominio y certificado documentada (aunque no se ejecute en esta etapa académica).
7. **Logs**: confirmar que no se registran datos sensibles; nivel de log apropiado para producción.
8. **Backups**: estrategia mínima de respaldo de MySQL documentada.
9. **Seguridad**: `APP_DEBUG=false`, rate limiting activo, checklist de `docs/SEGURIDAD.md` verificado por completo.
10. **Configuración de producción**: `APP_ENV=production`, caché de configuración/rutas de Laravel optimizada.
