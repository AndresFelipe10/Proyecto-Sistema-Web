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

## Despliegue y Operaciones

### Arquitectura de Despliegue

```
DESARROLLO (docker-compose.yml):
Host / Dev Machine
└── Docker Compose (puerto 8080 host → 80 nginx, 3308 host → 3306 db)
    ├── app (PHP-FPM 8.3 + Laravel, bind mount de código fuente para recarga en vivo)
    ├── nginx (Proxy web + servidor de estáticos, bind mount)
    └── db (MySQL 8.4, volumen persistente mysql_data, expuesto en puerto 3308 para depuración)

PRODUCCIÓN (docker-compose.prod.yml):
Internet → DNS / Dominio → Router / Firewall (Puertos 80, 443)
    ↓
Nginx Container (sistema_nginx_prod)
    ├── Terminación SSL / TLS (Certbot Let's Encrypt o Cloudflare Origin)
    ├── Compresión Gzip + Caché perimetral de estáticos (30 días)
    ├── Cabeceras de seguridad HTTP (X-Frame-Options, X-Content-Type-Options, etc.)
    └── FastCGI Pass (app:9000)
    ↓
PHP-FPM Container (sistema_app_prod)
    ├── Imagen inmutable con código y dependencias empacadas (Dockerfile.prod)
    ├── Composer optimizado (--no-dev --optimize-autoloader)
    ├── Zend OPcache activo y pre-compilado en memoria (validate_timestamps=0)
    ├── Caché de Laravel compilada (config, routes, views)
    └── Conexión de red interna a MySQL (sin exposición de puertos a Internet)
    ↓
MySQL Container (sistema_db_prod)
    ├── MySQL 8.4 Server
    ├── Volumen local persistente: mysql_prod_data
    └── Backups automatizados diarios vía scripts/backup-db.sh
```

La separación dev/producción se gestiona mediante variables de entorno en `.env` y archivos compose dedicados (`docker-compose.yml` para desarrollo, `docker-compose.prod.yml` para producción), asegurando paridad estricta y portabilidad total.

---

### Guía de Despliegue Reproducible en Producción

#### 1. Requisitos Mínimos del Servidor / VPS
- **Sistema Operativo:** Ubuntu 22.04 LTS o 24.04 LTS (o cualquier distribución Linux moderna con kernel 5.4+).
- **Recursos de Hardware:**
  - CPU: 1 vCPU mínimo (2 vCPU recomendado para picos de concurrencia).
  - RAM: 1.5 GB RAM mínimo (2 GB o más recomendado; con 1 GB se debe habilitar 2 GB de SWAP).
  - Disco: 20 GB SSD con soporte de almacenamiento persistente.
- **Software Base del Servidor Host:**
  - Docker Engine (v24.0+ o v26.0+)
  - Docker Compose Plugin (v2.20+)
  - Git, Curl, Bash

#### 2. Preparación de Secretos y Variables de Entorno
En el servidor de producción nunca se versiona el archivo `.env`. Se genera una copia limpia a partir de `.env.example`:

```bash
# En el servidor remoto / VPS
cd /var/www/sistema-web
cp .env.example .env
```

Configurar los valores críticos de seguridad en `.env`:
```ini
APP_NAME="Sistema de Ventas e Inventario"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio-emprendimiento.com
APP_TIMEZONE=America/Bogota
APP_LOCALE=es
LOG_LEVEL=error

# Conexión interna Docker MySQL (sin exponer puerto al host)
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=sistema_web_prod
DB_USERNAME=sistema_prod_user
DB_PASSWORD=GENERAR_PASSWORD_MUY_SEGURO_AQUI
DB_ROOT_PASSWORD=GENERAR_ROOT_PASSWORD_MUY_SEGURO_AQUI

# Seguridad en sesiones y cookies (bajo HTTPS)
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true

# Módulo de Consultas en Lenguaje Natural (IA)
AI_MODULE_ENABLED=false # Habilitar solo si se cuenta con GEMINI_API_KEY
AI_PROVIDER=gemini
GEMINI_API_KEY=tu_api_key_de_produccion_aqui
AI_TIMEOUT_SECONDS=10

# Configuración de Puertos en Producción
PROD_PORT=80
PROD_SSL_PORT=443
BACKUP_RETENTION_DAYS=7
```

#### 3. Despliegue Inicial Paso a Paso (Instalación Limpia)

1. **Clonar el repositorio:**
   ```bash
   git clone <URL_DEL_REPOSITORIO> /var/www/sistema-web
   cd /var/www/sistema-web
   ```

2. **Crear y configurar el archivo `.env`:**
   ```bash
   cp .env.example .env
   nano .env # Ajustar contraseñas y APP_URL
   ```

3. **Construir y levantar los contenedores de producción:**
   ```bash
   docker compose -f docker-compose.prod.yml up -d --build
   ```

4. **Generar la clave de cifrado de la aplicación:**
   ```bash
   docker compose -f docker-compose.prod.yml exec app php artisan key:generate --force
   ```

5. **Ejecutar migraciones en base de datos vacía:**
   ```bash
   docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
   ```
   *(Opcional: Si es la primera instalación y se desean datos base de prueba/roles: `php artisan db:seed --force`)*

6. **Optimizar Laravel para producción:**
   ```bash
   docker compose -f docker-compose.prod.yml exec app php artisan config:cache
   docker compose -f docker-compose.prod.yml exec app php artisan route:cache
   docker compose -f docker-compose.prod.yml exec app php artisan view:cache
   ```

7. **Ajustar permisos de almacenamiento:**
   ```bash
   docker compose -f docker-compose.prod.yml exec app chown -R www-data:www-data storage bootstrap/cache
   docker compose -f docker-compose.prod.yml exec app chmod -R 775 storage bootstrap/cache
   ```

#### 4. Configuración de Dominio y Certificados SSL/HTTPS

##### Opción A: Let's Encrypt / Certbot Automatizado
1. Apuntar el registro DNS tipo `A` del dominio (ej. `app.miemprendimiento.com`) a la dirección IP pública del VPS.
2. Nginx incluye en `docker/nginx/nginx.prod.conf` la ruta para el reto ACME:
   ```nginx
   location /.well-known/acme-challenge/ {
       root /var/www/certbot;
   }
   ```
3. Ejecutar Certbot para obtener el certificado:
   ```bash
   docker run -it --rm --name certbot \
       -v "/var/www/sistema-web/docker/certbot/conf:/etc/letsencrypt" \
       -v "/var/www/sistema-web/docker/certbot/www:/var/www/certbot" \
       certbot/certbot certonly --webroot -w /var/www/certbot \
       -d app.miemprendimiento.com --email admin@miemprendimiento.com --agree-tos --no-eff-email
   ```
4. Descomentar en `nginx.prod.conf` el bloque `listen 443 ssl` con las rutas de los certificados generados (`/etc/letsencrypt/live/.../fullchain.pem` y `privkey.pem`) y recargar Nginx:
   ```bash
   docker compose -f docker-compose.prod.yml exec nginx nginx -s reload
   ```

##### Opción B: Cloudflare SSL (Full / Strict)
Utilizar Cloudflare como proxy reverso gestionado: Cloudflare se encarga de la negociación TLS y el certificado Origin CA se instala directamente en el VPS.

#### 5. Estrategia de Copias de Seguridad (Backups) y Recuperación

El proyecto incluye dos utilidades operativas listas para producción:
- [`scripts/backup-db.sh`](file:///c:/Users/andre/Desktop/Proyectos/Proyecto%20Sistema%20Web/scripts/backup-db.sh): Genera dumps consistentes de MySQL con `--single-transaction`, compresión `gzip` y rotación automática (retención por defecto de 7 días).
- [`scripts/restore-db.sh`](file:///c:/Users/andre/Desktop/Proyectos/Proyecto%20Sistema%20Web/scripts/restore-db.sh): Restaura copias de seguridad de forma segura tras validación previa.

##### Automatización con Cron en el Host:
Editar el crontab del servidor (`crontab -e`) para programar un respaldo diario a las 2:00 AM:
```cron
0 2 * * * /bin/bash /var/www/sistema-web/scripts/backup-db.sh >> /var/log/sistema_backup.log 2>&1
```

##### Procedimiento de Recuperación ante Desastres:
1. Identificar el archivo de respaldo deseado en `/var/www/sistema-web/backups/`.
2. Ejecutar el script de restauración:
   ```bash
   bash scripts/restore-db.sh backups/dump_sistema_web_20260912_020000.sql.gz
   ```

#### 6. Procedimiento de Actualización y Despliegue Continuo

Para aplicar nuevas versiones en producción sin inconsistencias ni fallos en caliente, se utiliza el script automatizado:
```bash
bash scripts/deploy.sh
```

El script ejecuta automáticamente el flujo recomendado en `.agents/workflows/preparar-produccion.md`:
1. Respaldo preventivo automático de la base de datos MySQL.
2. Activación de modo mantenimiento temporal (`php artisan down`).
3. Reconstrucción y actualización de contenedores (`docker compose -f docker-compose.prod.yml up -d --build`).
4. Ejecución de migraciones pendientes con `--force`.
5. Re-generación de cachés de configuración, rutas y vistas (`config:cache`, `route:cache`, `view:cache`).
6. Validación de permisos de `storage` y `bootstrap/cache`.
7. Reactivación inmediata del servicio (`php artisan up`).

#### 7. Monitoreo, Registros (Logs) y Comprobación de Salud
- **Estado de contenedores:**
  ```bash
  docker compose -f docker-compose.prod.yml ps
  ```
- **Monitoreo de logs de la aplicación:**
  ```bash
  docker compose -f docker-compose.prod.yml logs -f app
  ```
- **Monitoreo de accesos web y errores de Nginx:**
  ```bash
  docker compose -f docker-compose.prod.yml logs -f nginx
  ```
- **Logs de Laravel:** Ubicados en `storage/logs/laravel.log`. En producción solo se registran eventos de nivel `ERROR`, `CRITICAL` o `ALERT` para no degradar I/O de disco.


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
├── views/
│   ├── layouts/       (app.blade.php, sidebar.blade.php, auth.blade.php)
│   ├── auth/
│   ├── businesses/
│   ├── categories/
│   ├── customers/
│   ├── dashboard.blade.php
│   ├── errors/
│   ├── inventory/
│   ├── products/
│   ├── reports/
│   ├── sales/
│   ├── suppliers/
│   └── users/
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
