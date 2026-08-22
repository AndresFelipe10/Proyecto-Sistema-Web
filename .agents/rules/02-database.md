# Regla 02 — Base de datos y multi-tenancy

**Nivel de aplicación:** siempre que se cree una migración, modelo, query o controlador que toque datos de negocio.

Modelo relacional completo, entidades y relaciones → `docs/BASE_DATOS.md`

## Multi-tenancy (crítico, defensa en profundidad obligatoria)
1. Global Scope de Eloquent que filtra automáticamente por `business_id` en todo modelo tenant-aware.
2. Middleware que resuelve el `business_id` activo desde el usuario autenticado antes de cualquier controlador.
3. Policy explícita en cada acción sensible (ver/editar/eliminar) que valide pertenencia al `business_id` del usuario, como capa independiente del scope.
4. PROHIBIDO construir queries manuales que puedan omitir el filtro de `business_id`. Cualquier excepción real debe justificarse por escrito.

Un usuario de un emprendimiento **nunca** puede leer, escribir, contar o reportar datos de otro emprendimiento, ni manipulando IDs en la URL o en peticiones.

## Reglas de integridad
- Operaciones críticas de inventario y ventas SIEMPRE dentro de una transacción de base de datos.
- Prohibido dejar stock negativo salvo regla de negocio explícita y documentada.
- Usar bloqueo pesimista (`lockForUpdate` o equivalente) en la confirmación de venta cuando el stock disponible sea límite, para evitar condiciones de carrera entre ventas simultáneas.
- No crear tablas nuevas fuera de las once mínimas (`users`, `businesses`, `roles`, `business_user`, `categories`, `products`, `customers`, `suppliers`, `sales`, `sale_details`, `inventory_movements`) sin justificar la necesidad en `docs/DECISIONES_TECNICAS.md`.
