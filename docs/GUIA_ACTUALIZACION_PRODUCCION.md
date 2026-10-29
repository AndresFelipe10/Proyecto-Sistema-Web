# Guía de Actualización y Despliegue en Producción (PuntoStock SaaS)

Esta guía documenta el procedimiento oficial para actualizar el servidor de producción con los últimos commits del repositorio, aplicando de forma segura las correcciones de arquitectura, blindaje de datos, nuevo formato ejecutivo de impresión de Cuadre de Caja y la reactivación del **Módulo Asistente IA (Gemini)**.

---

## 1. Causas del Error de IA en Producción

En desarrollo (`localhost:8080`), el volumen `.:/var/www/html` monta el archivo `.env` directamente en el contenedor. En producción, por seguridad, `.dockerignore` excluye el archivo `.env` de la imagen compilada. 

Anteriormente:
- `docker-compose.prod.yml` no declaraba `env_file: .env` ni montaba `./.env:/var/www/html/.env:ro`.
- Al ejecutarse `php artisan config:cache` durante el despliegue, `config/ai.php` congelaba la clave de Gemini en `null`.
- Al no tener clave configurada, `GeminiProvider` lanzaba una excepción que activaba el mensaje de fallback:
  > *"El asistente de consultas no está disponible temporalmente. Puedes utilizar los módulos tradicionales de clientes, ventas e inventario."*

Con los cambios actuales:
- `docker-compose.prod.yml` inyecta `env_file: .env` y monta `./.env:/var/www/html/.env:ro`.
- Se añadieron servidores DNS públicos (`8.8.8.8`, `1.1.1.1`) para garantizar la resolución de `generativelanguage.googleapis.com` en redes Docker bridge sobre VPS Linux.
- `docker/php/Dockerfile.prod` instala y actualiza `ca-certificates` para garantizar handshakes TLS sin errores de certificados SSL.
- Se incorpora el comando de diagnóstico `php artisan ai:check`.

---

## 2. Requisitos Previos en el Servidor de Producción

1. Acceso por SSH con privilegios de ejecución (o `sudo`) en el servidor donde corre PuntoStock SaaS.
2. Contar con una clave válida de Gemini API de [Google AI Studio](https://aistudio.google.com/app/apikey).
3. Verificar que los servicios estén activos en el directorio del proyecto (por ejemplo `/var/www/sistema-web` o donde resida la aplicación).

---

## 3. Procedimiento de Actualización Paso a Paso

### Paso 1: Conectarse al Servidor y Ubicarse en el Proyecto
```bash
ssh usuario@tu-servidor-ip
cd /var/www/sistema-web   # O la ruta donde se encuentra el proyecto
```

### Paso 2: Descargar los Últimos Cambios del Repositorio
```bash
git fetch origin
git pull origin main
```
> Esto descargará los commits con las correcciones de integridad, el rediseño de impresión y las mejoras de infraestructura Docker.

### Paso 3: Configurar las Variables de IA en el Archivo `.env` de Producción
Abre tu archivo `.env` en el servidor:
```bash
nano .env
```
Asegúrate de que las siguientes variables estén configuradas con tu clave real:
```dotenv
# Módulo de Consultas en Lenguaje Natural (IA)
AI_MODULE_ENABLED=true
AI_PROVIDER=gemini
GEMINI_API_KEY=tu_clave_real_de_google_ai_studio_aqui
GEMINI_MODEL=gemini-flash-lite-latest
AI_TIMEOUT_SECONDS=10
```
> **Nota:** Guarda los cambios con `Ctrl + O`, presiona `Enter` y sal con `Ctrl + X`.

### Paso 4: Ejecutar el Script Automatizado de Despliegue
Ejecuta el script oficial de producción:
```bash
bash scripts/deploy.sh
```
El script realizará automáticamente:
1. Respaldo preventivo de base de datos (`scripts/backup-db.sh`).
2. Activación del modo mantenimiento temporal (`php artisan down`).
3. Reconstrucción y arranque de los contenedores Docker (`docker compose -f docker-compose.prod.yml up -d --build`).
4. Ejecución de migraciones pendientes con `--force` (incluyendo soporte de cantidades decimales).
5. Limpieza y re-compilación de cachés de configuración, rutas y vistas (`config:cache`, `route:cache`, `view:cache`).
6. Ajuste de permisos en directorios `storage/` y `bootstrap/cache/`.
7. Desactivación del modo mantenimiento (`php artisan up`).
8. Verificación automática de salud de la IA (`php artisan ai:check`).

---

## 4. Verificación y Diagnóstico Directo

Para comprobar en cualquier momento el estado de la IA dentro del contenedor de producción, ejecuta:
```bash
docker compose -f docker-compose.prod.yml exec app php artisan ai:check
```

### Salida esperada cuando todo está correcto:
```text
================================================================
 Diagnóstico de Conectividad y Salud: Módulo de IA (Gemini)
================================================================

  [✓] Módulo habilitado: AI_MODULE_ENABLED=true
  [✓] Proveedor configurado: gemini
  [✓] API Key: Configurada (AQ.Ab8...y-xw)
  [✓] Modelo: gemini-flash-lite-latest
  [✓] Base URL: https://generativelanguage.googleapis.com/v1beta
  [✓] Timeout: 10s
  [✓] Resolución DNS (generativelanguage.googleapis.com): OK (142.251.x.x)
  [✓] Handshake HTTPS / SSL (generativelanguage.googleapis.com): Conectado (HTTP 200)
  [>] Enviando consulta de prueba: "¿Cuántos clientes tenemos?"...
  [✓] Respuesta de Gemini recibida en 840ms:
      • Intención detectada: count_customers
      • Filtros extraídos: []
      • Confianza: 1

================================================================
 ¡ÉXITO! El Módulo de IA está saludable y listo para operar.
================================================================
```

---

## 5. Resolución de Problemas Comunes en Producción

| Error en `ai:check` | Causa | Solución |
| :--- | :--- | :--- |
| `API Key: NO CONFIGURADA` | La clave está vacía o el contenedor no la cargó. | Verifica que `GEMINI_API_KEY` esté en el `.env` del host y ejecuta `docker compose -f docker-compose.prod.yml exec app php artisan config:cache`. |
| `API Key: VALOR POR DEFECTO` | El `.env` contiene el texto de ejemplo `tu_api_key_de_gemini_aqui`. | Edita el archivo `.env` y coloca tu clave real generada en Google AI Studio. |
| `Resolución DNS: FALLÓ` | La red Docker del VPS no puede resolver nombres de Internet. | Verifica que `docker-compose.prod.yml` tenga la directiva `dns: [8.8.8.8, 1.1.1.1]` en el servicio `app` y re-ejecuta `docker compose -f docker-compose.prod.yml up -d`. |
| `Handshake HTTPS / SSL: FALLÓ` | Certificados CA desactualizados o faltantes en Alpine. | Reconstruye la imagen con `docker compose -f docker-compose.prod.yml build --no-cache app`. |
| `Llamada falló con HTTP 400 o 403` | La API Key fue ingresada incorrectamente o carece de permisos. | Genera una nueva API Key en Google AI Studio y actualiza el `.env`. |
| `Llamada falló con HTTP 429` | Se excedió la cuota de peticiones por minuto de la cuenta gratuita de Google. | Espera 60 segundos o revisa las cuotas asignadas a tu proyecto en Google Cloud Console. |

