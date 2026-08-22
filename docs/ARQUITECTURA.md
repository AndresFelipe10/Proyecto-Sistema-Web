# Arquitectura

## Vista general
```
NÚCLEO PRINCIPAL (Laravel + MySQL)
        +
MÓDULO OPCIONAL DE IA (Gemini)
```
El núcleo es autosuficiente; el módulo IA es un añadido desactivable. Ver reglas de aislamiento en `.agents/rules/04-ai.md`.

## Stack
- Backend: PHP 8.3+, Laravel 12 (MVC, Eloquent, Migrations, Seeders, Factories, Form Requests, Middleware, Policies).
- Frontend: Blade + Bootstrap 5 + JS vanilla.
- BD: MySQL 8+.
- Infraestructura: Docker Compose (`app`, `nginx`, `db`).

## Despliegue
Desarrollo:
```
Docker
├── app (PHP-FPM + Laravel)
├── nginx
└── db (MySQL, volumen persistente)
```
Producción (documentado, no necesariamente desplegado en esta etapa académica):
```
Internet → Dominio/HTTPS → Nginx → PHP-FPM/Laravel → MySQL
```
La configuración de conexión (dev/testing/producción) se resuelve únicamente vía `.env`, sin tocar código de negocio.

## Estructura de carpetas (objetivo)
```
app/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Middleware/
├── Models/
├── Policies/
├── Services/
│   ├── Sales/
│   ├── Inventory/
│   └── AI/
└── AI/
    ├── Contracts/     (AiProviderInterface)
    ├── Providers/     (GeminiProvider)
    ├── DTOs/
    ├── Tools/          (un servicio por intent permitido)
    └── Exceptions/
database/
resources/
routes/
tests/
config/
public/
storage/
docs/
.agents/
```

## Flujo de información — ejemplo (venta)
```
Request (Controller) → Form Request (validación) → Policy (autorización + business_id)
   → Service de Ventas (transacción DB) → actualizar inventario → registrar movimiento
   → respuesta al usuario
```

## Flujo de información — módulo IA
Ver `docs/MODULO_IA.md` para el flujo completo con validación de intents.

## Responsabilidades por capa
- **Controllers**: delgados, orquestan Form Request + Service + respuesta.
- **Form Requests**: validación y autorización básica de entrada.
- **Services**: lógica de negocio no trivial (ventas, inventario, IA). No crear Services para operaciones CRUD simples que Eloquent resuelve directamente.
- **Policies**: autorización fina por recurso y `business_id`.
- **Models**: relaciones Eloquent, Global Scopes de tenancy.
