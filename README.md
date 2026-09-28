# PuntoStock — Sistema Web de Gestión de Ventas e Inventario para Pequeños Emprendimientos

[![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?style=flat-square&logo=php&logoColor=white)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-12.x-FF2D20?style=flat-square&logo=laravel&logoColor=white)](https://laravel.com/)
[![MySQL](https://img.shields.io/badge/MySQL-8.4-4479A1?style=flat-square&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Docker Compose](https://img.shields.io/badge/Docker%20Compose-3%20Servicios-2496ED?style=flat-square&logo=docker&logoColor=white)](https://www.docker.com/)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat-square&logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![Tests](https://img.shields.io/badge/Tests-218%20passed-brightgreen?style=flat-square)]()
[![Security](https://img.shields.io/badge/OWASP-Hardened-blue?style=flat-square)]()

**PuntoStock** es un sistema web integral, multiusuario y multi-tenant diseñado específicamente para centralizar y optimizar la gestión comercial, ventas, inventario y toma de decisiones en pequeños emprendimientos de **Cali, Colombia**, bajo un modelo SaaS cerrado de alta seguridad. 

Desarrollado como proyecto de Ingeniería de Sistemas bajo estrictos estándares profesionales de arquitectura, seguridad perimetral, integridad transaccional y capacidad de despliegue en producción.

---

## Principio Fundamental del Sistema

```
┌─────────────────────────────────────────────────────────┐
│        NÚCLEO PRINCIPAL AUTOSUFICIENTE                  │
│        (Laravel 12 + MySQL 8.4 + Docker)                │
│  Operación comercial, transaccional y de reportes 100%  │
└───────────────────────────┬─────────────────────────────┘
                            │
                            ▼ (Opcional y Desacoplado)
┌─────────────────────────────────────────────────────────┐
│       MÓDULO DE IA EN LENGUAJE NATURAL (Gemini)         │
│  Consultas en lenguaje natural, solo lectura, whitelist │
│  y fallback automático ante indisponibilidad            │
└─────────────────────────────────────────────────────────┘
```

El sistema opera al 100% de su capacidad sin depender de servicios externos de inteligencia artificial. El módulo de IA es un complemento opcional, desacoplado mediante contratos (`AiProviderInterface`), seguro y desactivable en cualquier momento vía variables de entorno.

---

## Características y Módulos Principales

### 1. Multi-tenancy Lógico y Aislamiento Estricto
- Cada registro de negocio está aislado mediante la clave de tenant `business_id`.
- Protección en tres capas: **Eloquent Global Scope** (filtro automático en consultas), **SetCurrentTenant Middleware** (contexto de sesión activo) y **Laravel Policies** (autorización por recurso).

### 2. Control de Acceso Basado en Roles (RBAC)
- **Administrador**: Control total del emprendimiento, gestión de usuarios, catálogo de productos, compras, movimientos, reportes financieros, auditoría y panel de control.
- **Empleado / Vendedor**: Registro ágil de ventas en mostrador, consulta de disponibilidad de catálogo y gestión básica de clientes.

### 3. Catálogo Inteligente y Control de Inventario
- Productos clasificados por categoría con SKU único por emprendimiento.
- Control de stock con precios de costo y precios de venta.
- **Integridad Transaccional**: Registro obligatorio de todo cambio de stock en la tabla de auditoría `inventory_movements` (entradas, salidas y ajustes manuales).
- **Concurrencia Pesimista**: Bloqueo con `lockForUpdate()` en operaciones de venta para evitar sobreventa y condiciones de carrera.

### 4. Panel Inteligente de Inventario y Alertas de Reposición
- Clasificación determinística del estado de inventario:
  - **Normal**: Stock por encima del mínimo.
  - **Bajo Stock**: Stock igual o inferior al umbral mínimo (genera alerta preventiva).
  - **Agotado**: Stock en 0 o negativo (genera alerta crítica).
- Recomendación automatizada de cantidad de reposición para compras.

### 5. Punto de Venta, Pagos Mixtos y Comprobantes (DIAN Compliant)
- Registro de ventas con soporte para **Consumidor Final DIAN** (`222222222222` / `customer_id = NULL`) predeterminado o clientes registrados mediante selector interactivo con búsqueda en tiempo real (debounce 280ms) y modal de creación rápida seguro.
- **Snapshot inmutable del comprador**: En cada venta se clonan `customer_name` y `customer_document`, garantizando que futuras ediciones o desactivaciones de clientes no alteren el histórico contable ni los comprobantes.
- **Pagos Mixtos y Vueltos Autoritativos**: Soporte de 1 a 5 líneas de pago combinadas (`cash`, `card`, `transfer`, `other`) persistidas en la tabla `sale_payments`. Atajos rápidos de billetes ($10k, $20k, $50k, $100k, Exacto) para efectivo, cálculo de vueltos estricto en backend y validación de suma en centavos enteros para evitar errores de coma flotante.
- Generación de numeración de comprobante consecutiva por negocio (`FAC-000001`) con bloqueo pesimista y constraint UNIQUE en base de datos.
- Impresión nativa de comprobantes en dos formatos (Carta y Ticket térmico 80mm) con desglose de métodos de pago aplicados, efectivo recibido, cambio/vuelto y rotulación legal no DIAN.
- Opción de anulación controlada por administradores con reversión automática del stock al inventario.

### 6. Directorio Comercial
- Gestión centralizada de **Clientes** y **Proveedores**.
- Índice compuesto único `(business_id, document)` en clientes, garantizando unicidad por tenant y validación de formato numérico / NIT (`^\d{5,15}(-\d)?$`).
- Prohibición estricta de registro del documento DIAN `222222222222` en el directorio.
- Validación de borrado seguro: clientes con historial de ventas no se eliminan físicamente sino que se desactivan para preservar la integridad contable.

### 7. Gastos, Facturas de Compra y Almacenamiento Seguro (Exclusivo Administrador)
- Registro y control de egresos categorizados (`merchandise`, `utilities`, `rent`, `supplies`, `payroll`, `other`) con montos positivos validados en backend y CHECK en base de datos.
- **Regla contable estricta**: Los gastos de mercancía no alteran stock ni inventario (el stock se gestiona exclusivamente por el módulo de Inventario).
- Vínculo opcional con proveedores del mismo tenant y control de duplicidad por `(business_id, supplier_id, invoice_number)`.
- Estado dinámico de mora ("Vencida") para cuentas por pagar sin alterar el esquema relacional (`status = pending` y `due_date < hoy`).
- Acción rápida de pago con registro obligatorio de método de pago y fecha.
- **Almacenamiento Seguro de Adjuntos**: Comprobantes y facturas en PDF/JPG/PNG almacenados en disco privado (`storage/app/private/expenses`) con nombres de hash aleatorio; descarga exclusivamente bajo `ExpensePolicy` con streaming protegido y cabeceras `nosniff`. Limpieza física automática al actualizar o eliminar.

### 8. Dashboard Gerencial y Reportes Financieros
- **KPIs en Tiempo Real**: Ventas del día, ingresos mensuales, productos críticos y ticket promedio.
- **Métricas Financieras Protegidas (Exclusivas Administrador)**: Ventas del mes, gastos del mes (con desglose de pendientes) y utilidad neta estimada. Estas cifras se ocultan automáticamente a los vendedores tanto en la interfaz web como en el endpoint `/api/dashboard`.
- **Reportes Especializados**:
  - Reporte de ventas filtrable por rango de fechas y método de pago.
  - Reporte de valoración de inventario (costo total vs. valor potencial de venta y margen estimado).
  - Ranking de productos más vendidos y rentables.
  - **Exportación en CSV** respetando el aislamiento multi-tenant.

### 9. Asistente de Consultas en Lenguaje Natural (IA)
- Permite a los usuarios consultar datos operativos en lenguaje cotidiano (*"¿Cuáles son los productos con stock bajo?"*, *"¿Cuánto vendimos este mes?"*).
- **Seguridad Garantizada**: Implementa *Function Calling / Structured Output* con una estricta lista blanca de intenciones permitidas. No ejecuta SQL arbitrario, no tiene permisos de escritura y el `business_id` es inyectado por Laravel.
- **Fallback Automático**: Mensajes controlados si la cuota de la API se agota o hay cortes de red.

### 10. Endurecimiento de Seguridad OWASP
- Middleware perimetral de cabeceras HTTP (`X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Referrer-Policy`).
- Protección CSRF activa en todos los formularios y rutas de escritura.
- Rate Limiting contra ataques de fuerza bruta en inicio de sesión (5 intentos / min) y abuso de IA (30 peticiones / min).
- Modelos Eloquent protegidos contra Mass Assignment (`$fillable` estricto en todos los modelos).
- Páginas de error seguras y personalizadas (`403`, `404`, `419`, `500`) sin exposición de stack traces ni metadatos del servidor.

---

## Stack Tecnológico

| Capa | Tecnología | Justificación |
|---|---|---|
| **Backend** | PHP 8.3 FPM + Laravel 12 | Estándar de la industria, robustez en Eloquent ORM, migraciones, policies y ecosistema de testing. |
| **Frontend** | Blade + Bootstrap 5.3 + Vanilla JS | Rápido, responsivo, sin la sobrecarga ni complejidad de compilación de un SPA (React/Vue). |
| **Base de Datos** | MySQL 8.4 LTS | Motor relacional estándar con soporte para transacciones ACID, bloqueo pesimista e integridad referencial. |
| **Servidor Web** | Nginx Alpine | Proxy inverso perimetral ligero con compresión Gzip, caché de estáticos y soporte SSL/TLS. |
| **Contenedores** | Docker Compose | Paridad exacta entre entornos, despliegue reproducible en 1 comando; el cliente final no instala dependencias locales. |
| **IA (Opcional)** | Google Gemini API | Proveedor accesible para entornos académicos, integrado mediante abstracción desacoplada. |

---

## Estructura del Proyecto

```
├── app/
│   ├── AI/                 # Módulo de IA aislado (Contratos, Providers, DTOs, Tools)
│   ├── Http/
│   │   ├── Controllers/    # Controladores delgados
│   │   ├── Middleware/     # Tenancy, seguridad y verificación
│   │   └── Requests/       # Form Requests con validación estricta
│   ├── Models/             # Modelos Eloquent con Global Scopes de Tenancy
│   ├── Policies/           # Autorización granular por rol y tenant
│   └── Services/           # Servicios de negocio (Sales, Inventory, AI)
├── database/
│   ├── migrations/         # 13 migraciones versionadas
│   └── seeders/            # Seeder con datos de prueba realistas
├── docker/
│   ├── nginx/              # Configuraciones de Nginx para dev y prod
│   ├── php/                # Dockerfiles y archivos .ini (dev y prod)
│   └── certbot/            # Directorios para certificados SSL
├── docs/                   # Documentación viva del proyecto
│   ├── ARQUITECTURA.md     # Arquitectura del sistema y Guía de Despliegue Reproducible
│   ├── BASE_DATOS.md       # Diccionario de datos, relaciones y multi-tenancy
│   ├── DECISIONES_TECNICAS.md # Registro de decisiones arquitectónicas (ADR)
│   ├── MODULO_IA.md        # Especificación del módulo de lenguaje natural
│   ├── PLAN_PROYECTO.md    # Fuente de verdad, alcance y criterios globales
│   ├── ROADMAP.md          # Registro de fases y criterios de aceptación
│   └── SEGURIDAD.md        # Checklist de controles OWASP y endurecimiento
├── resources/views/        # Vistas Blade responsivas con Bootstrap 5
├── routes/                 # Rutas web agrupadas por autenticación y tenant
├── scripts/                # Scripts de automatización (backup, restore, deploy)
├── tests/                  # 125 pruebas automatizadas (Unit y Feature)
├── docker-compose.yml      # Entorno de desarrollo local
└── docker-compose.prod.yml # Entorno inmutable para producción
```

---

## Puesta en Marcha en Desarrollo (Paso a Paso)

### Prerrequisitos
- [Git](https://git-scm.com/) instalado.
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Windows/macOS) o Docker Engine + Compose (Linux) en ejecución.

### 1. Clonar el repositorio
```bash
git clone <URL_DEL_REPOSITORIO>
cd "Proyecto Sistema Web"
```

### 2. Configurar el archivo de entorno
```bash
cp .env.example .env
```
*(Nota: El archivo `.env.example` viene configurado por defecto con las credenciales correspondientes a los servicios de Docker).*

### 3. Levantar los contenedores
```bash
docker compose up -d --build
```
Este comando construirá y levantará los 3 servicios del sistema:
- `sistema_app` (PHP 8.3 FPM)
- `sistema_nginx` (Servidor web en http://localhost:8080)
- `sistema_db` (MySQL 8.4 accesible internamente y en puerto host 3308)

### 4. Inicializar la base de datos y generar clave
```bash
# Generar clave de cifrado
docker compose exec app php artisan key:generate

# Ejecutar migraciones y poblar datos de demostración
docker compose exec app php artisan migrate --seed
```

### 5. Acceder a la Aplicación
Abre tu navegador web en:
👉 **[http://localhost:8080](http://localhost:8080)**

---

## Credenciales de Demostración Preconfiguradas

El seeder (`DatabaseSeeder`) crea automáticamente una empresa ficticia en Cali (*"Emprendimiento Demo Cali"*) con productos, movimientos, clientes, proveedores y una venta de ejemplo:

| Rol | Correo Electrónico | Contraseña | Permisos |
|---|---|---|---|
| **Administrador** | `admin@demo.com` | `password123` | Acceso total (Configuración, Usuarios, Inventario, Reportes, Dashboard, IA) |
| **Empleado** | `empleado@demo.com` | `password123` | Acceso operativo (Registrar ventas, consultar catálogo y clientes) |

*Nota: También puedes registrar un usuario nuevo desde la pantalla de `/register`; el sistema te guiará para crear un nuevo emprendimiento y te asignará automáticamente el rol de Administrador.*

---

## Configuración del Módulo de IA (Opcional)

Por defecto, el sistema funciona con el módulo de IA desactivado (`AI_MODULE_ENABLED=false`). Para activarlo:
1. Obtén una clave de API gratuita en [Google AI Studio](https://aistudio.google.com/).
2. En tu archivo `.env`, actualiza las siguientes líneas:
   ```ini
   AI_MODULE_ENABLED=true
   AI_PROVIDER=gemini
   GEMINI_API_KEY=tu_clave_real_de_gemini_aqui
   AI_TIMEOUT_SECONDS=10
   ```
3. Reinicia la caché de configuración en el contenedor:
   ```bash
   docker compose exec app php artisan config:clear
   ```
4. Ingresa al menú superior **"Asistente IA"** para formular consultas en lenguaje natural.

---

## Ejecución de Pruebas Automatizadas

El proyecto cuenta con una cobertura integral de pruebas unitarias y de integración que validan el aislamiento multi-tenant, seguridad, concurrencia de stock y reglas de negocio:

```bash
docker compose exec app php artisan test
```

### Resumen de la Suite de Pruebas:
- **Tenant Isolation**: Verificación de aislamiento en modelos, creación de negocios y cambios de tenant.
- **Catalog & Inventory**: CRUD de productos y categorías con SKU único por tenant; concurrencia pesimista en stock.
- **Sales & Orders**: Actualización de stock, rechazo ante saldo insuficiente, anulación y facturación.
- **Smart Panel**: Clasificación correcta de stock agotado/bajo/normal y cálculo de reposición.
- **Reports**: Aislamiento en reportes financieros y exportación a CSV sin fuga entre negocios.
- **Security Checklist**: Validación de los 7 controles OWASP (CSRF, Rate Limiting, Cabeceras HTTP, Mass Assignment, etc.).
- **AI Module**: Pruebas con mocks de Gemini para intents permitidos, rechazo de intentos maliciosos, aislamiento y fallback sin conexión.

**Resultado:** **`171 tests passed (686 assertions)`** con 0 fallos.

---

## Administración de Plataforma y Modelo SaaS Cerrado

PuntoStock opera bajo un modelo de **SaaS cerrado**:
- El registro público está deshabilitado (`/register` devuelve 404). Los interesados en adquirir la plataforma son canalizados vía WhatsApp institucional (`SUPPORT_WHATSAPP=573163765939`).
- Cada usuario pertenece exclusivamente a un único negocio (`UNIQUE(user_id)`).
- El aprovisionamiento de negocios y cuentas de administrador es realizado exclusivamente por el **Superadministrador de plataforma** desde `/superadmin`.

### Creación de Superadministrador en Producción
Para crear la primera cuenta de superadministrador de forma interactiva y segura (sin seeders ni contraseñas expuestas en repositorios):

```bash
docker compose exec app php artisan superadmin:create admin@puntostock.co --name="Super Administrador"
```
El comando solicitará la contraseña mediante entrada oculta con confirmación (mínimo 12 caracteres).

### Variables de Entorno Relevantes
En el archivo `.env`:
```ini
# WhatsApp de soporte y canal comercial
SUPPORT_WHATSAPP=573163765939
```

---

## Despliegue en Producción

El proyecto incluye configuraciones listas para despliegue en servidores VPS (Ubuntu 22.04/24.04 LTS) con imágenes Docker inmutables y scripts de automatización:

1. **Configuración de Producción:** [`docker-compose.prod.yml`](docker-compose.prod.yml) y [`docker/php/Dockerfile.prod`](docker/php/Dockerfile.prod).
2. **Script de Despliegue:** [`scripts/deploy.sh`](scripts/deploy.sh) (ejecuta respaldo preventivo, modo mantenimiento, compilación, migraciones con `--force` y optimización de cachés).
3. **Respaldo y Restauración del Sistema:**
   - Respaldo automatizado consolidado (Base de Datos MySQL + Adjuntos privados en `app_storage`): `bash scripts/backup.sh` (genera archivo `.tar.gz` con política de retención de 7 días).
   - Respaldo exclusivo de base de datos: `bash scripts/backup-db.sh`
   - Restauración interactiva de base de datos: `bash scripts/restore-db.sh backups/dump_archivo.sql.gz`

Para consultar los requisitos de hardware, configuración de dominio, certificados SSL con Let's Encrypt y buenas prácticas operativas, consulta la **[Guía de Despliegue Reproducible en docs/ARQUITECTURA.md](docs/ARQUITECTURA.md#gu%C3%ADa-de-despliegue-reproducible-en-producci%C3%B3n)**.

> **Nota sobre variables de entorno**: Tras modificar `APP_NAME`, `SUPPORT_WHATSAPP` u otras variables en `.env`, es necesario reconstruir la imagen Docker (`docker compose -f docker-compose.prod.yml build app`) y ejecutar `php artisan config:cache` para que la configuración se actualice en los contenedores de producción.

---

## Identidad Visual

El sistema adopta la identidad de marca **PuntoStock** con una paleta cromática profesional y contrastada:

- **Tinta (`#1B2A49`)**: Color primario de marca, textos destacados, barras de navegación y meta theme-color.
- **Azafrán (`#E8A317`)**: Color de acento comercial y punto característico del imagotipo.
- **Hueso (`#F6F4EF`)**: Tono de fondo complementario y contraste secundario.

### Ubicación de Assets de Marca
- **Assets servidos al cliente (`public/`)**:
  - `public/favicon.svg`: Ícono vectorial SVG principal para navegadores modernos.
  - `public/favicon.ico`: Favicon multipropósito ICO (16, 32 y 48 px).
  - `public/favicon-16x16.png` y `public/favicon-32x32.png`: Variantes estándar PNG.
  - `public/apple-touch-icon.png`: Ícono para dispositivos móviles Apple (180x180 px).
  - `public/images/brand/logo-mark.svg`: Símbolo "P.S" con punto azafrán y viewBox ajustado (proporción ancho:alto ≈ 0.73).
- **Archivos maestros de diseño (`resources/branding/`)**:
  - `resources/branding/logo-master.svg` y `favicon-master.svg`: Vectores originales de referencia (no se sirven directamente).
- **Vistas y componentes Blade (`resources/views/`)**:
  - `resources/views/partials/brand-head.blade.php`: Inclusión unificada de favicons con versionado cache buster (`?v=1`).
  - `resources/views/components/brand.blade.php`: Componente `<x-brand size="sm|lg" />` con texto HTML real y logo vectorial.

---

## Índice de Documentación del Proyecto

Toda la documentación técnica se mantiene versionada y actualizada dentro del directorio `docs/`:

- **[Plan del Proyecto (docs/PLAN_PROYECTO.md)](docs/PLAN_PROYECTO.md)**: Alcance, objetivos, usuarios y criterios globales de aceptación.
- **[Arquitectura (docs/ARQUITECTURA.md)](docs/ARQUITECTURA.md)**: Estructura de capas, flujos de datos, infraestructura Docker y Guía de Despliegue.
- **[Base de Datos (docs/BASE_DATOS.md)](docs/BASE_DATOS.md)**: Modelo relacional, campos clave, integridad transaccional y multi-tenancy.
- **[Seguridad (docs/SEGURIDAD.md)](docs/SEGURIDAD.md)**: Matriz de controles OWASP, autenticación y manejo seguro de errores.
- **[Módulo de IA (docs/MODULO_IA.md)](docs/MODULO_IA.md)**: Especificación de intents, contratos, desacoplamiento y privacidad.
- **[Decisiones Técnicas (docs/DECISIONES_TECNICAS.md)](docs/DECISIONES_TECNICAS.md)**: Architectural Decision Records (ADR) con justificaciones de diseño.
- **[Roadmap de Desarrollo (docs/ROADMAP.md)](docs/ROADMAP.md)**: Detalle del avance de las Fases 0 a 17 y sus criterios cumplidos.
- **[Instrucciones para Agentes de IA (AGENTS.md)](AGENTS.md)**: Punto de entrada, reglas y workflows repetibles en `.agents/`.

---

## Licencia

Este proyecto fue desarrollado con propósitos académicos y de aplicación profesional bajo la licencia [MIT](LICENSE).
