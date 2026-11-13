# Guía de instalación y despliegue

**Sistema Web Integral de Gestión de la Gaceta Universitaria — Universidad Pública de El Alto**

Versión 1.0 · Noviembre de 2026

---

## Contenido

- [Parte A. Entorno de desarrollo (Windows)](#parte-a-entorno-de-desarrollo-windows)
- [Parte B. Servidor de producción (Linux)](#parte-b-servidor-de-producción-linux)
- [Parte C. Respaldos y restauración](#parte-c-respaldos-y-restauración)
- [Parte D. Actualizar el sistema](#parte-d-actualizar-el-sistema)
- [Parte E. Solución de problemas](#parte-e-solución-de-problemas)

---

## Parte A. Entorno de desarrollo (Windows)

### A.1 Requisitos

| Programa | Versión mínima | Comprobar |
|---|---|---|
| PHP (extensiones `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `fileinfo`, `zip`, `intl`) | 8.3 | `php -v` y `php -m` |
| Composer | 2.x | `composer --version` |
| Node.js y npm | Node 20 | `node -v` |
| Docker Desktop | reciente | `docker --version` |
| Git | reciente | `git --version` |

En `php.ini` (ruta: `php --ini`), para PDF de hasta 20 MB:

```ini
upload_max_filesize = 20M
post_max_size = 25M
```

### A.2 Instalación

```powershell
git clone https://github.com/heberlampa123/Gaceta_UPEA.git
cd Gaceta_UPEA

composer install
npm install
npm run build

Copy-Item .env.example .env
php artisan key:generate
```

Edite `.env` y complete al menos: `DB_PASSWORD`, `PGADMIN_PASSWORD` y `ADMIN_CONTRASENA`
(use solo letras y números en las contraseñas del `.env`).

> El PostgreSQL de Docker usa el puerto **5434** de Windows (los puertos 5432 y 5433 suelen estar ocupados).
> Si 5434 también está ocupado, cambie `DB_PORT` en `.env`.

### A.3 Base de datos

```powershell
docker compose up -d                       # PostgreSQL 17 y pgAdmin
php artisan migrate --seed                 # tablas + datos iniciales + administrador
php artisan db:seed --class=DemoSeeder     # opcional: usuarios y 20 resoluciones de demostración
```

| Servicio | Dirección | Credenciales |
|---|---|---|
| pgAdmin | http://localhost:5050 | `PGADMIN_EMAIL` / `PGADMIN_PASSWORD`; servidor «Gaceta UPEA (Docker)» con `DB_PASSWORD` |
| PostgreSQL (desde Windows) | `127.0.0.1:5434` | `DB_USERNAME` / `DB_PASSWORD` |

### A.4 Ejecutar

```powershell
php artisan serve        # http://localhost:8000
```

| Usuario | Contraseña | Rol |
|---|---|---|
| `admin` | valor de `ADMIN_CONTRASENA` (se cambia al primer ingreso) | Administrador |
| `encargado` | `password` (solo con `DemoSeeder`) | Encargado de documentos |
| `revisor` | `password` (solo con `DemoSeeder`) | Revisor / Publicador |

### A.5 Pruebas

```powershell
php artisan test
```

Usan la base `gaceta_upea_test`, que se crea automáticamente al crear el volumen de Docker. Si no existe:

```powershell
docker exec gaceta_postgres createdb -U gaceta -O gaceta gaceta_upea_test
```

### A.6 Comandos frecuentes

| Comando | Para qué |
|---|---|
| `docker compose up -d` / `docker compose stop` | Encender / apagar la base de datos (sin perder datos). |
| `npm run build` | Compilar estilos y scripts después de modificarlos. |
| `php artisan config:clear` | Aplicar cambios del `.env`. |
| `php artisan migrate` | Aplicar migraciones nuevas sin borrar datos. |
| `php artisan migrate:fresh --seed` | **Borra todo** y recrea la base (solo desarrollo). |
| `php artisan gaceta:respaldo` | Respaldar la base de datos y los PDF. |

---

## Parte B. Servidor de producción (Linux)

Configuración de referencia: **Ubuntu Server 24.04 LTS, Nginx, PHP-FPM 8.3+ y PostgreSQL 17**, con un dominio y HTTPS.
Los nombres en `<…>` deben reemplazarse por los valores reales.

### B.1 Paquetes

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx git unzip curl \
  php8.3-fpm php8.3-cli php8.3-pgsql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-zip php8.3-intl php8.3-bcmath

# Composer
curl -sS https://getcomposer.org/installer | php && sudo mv composer.phar /usr/local/bin/composer

# PostgreSQL 17 (repositorio oficial de PostgreSQL)
sudo apt install -y postgresql-common && sudo /usr/share/postgresql-common/pgdg/apt.postgresql.org.sh
sudo apt install -y postgresql-17 postgresql-client-17

# Node.js (solo para compilar los estilos; también puede compilarse en otro equipo)
sudo apt install -y nodejs npm
```

### B.2 Base de datos

```bash
sudo -u postgres psql <<'SQL'
CREATE USER gaceta WITH PASSWORD '<contraseña-segura>';
CREATE DATABASE gaceta_upea OWNER gaceta;
\c gaceta_upea
CREATE EXTENSION IF NOT EXISTS pg_trgm;
SQL
```

> La extensión `pg_trgm` acelera las búsquedas. Se crea como usuario `postgres` porque requiere permisos de administrador.

### B.3 Código de la aplicación

```bash
sudo mkdir -p /var/www/gaceta && sudo chown $USER:www-data /var/www/gaceta
git clone https://github.com/heberlampa123/Gaceta_UPEA.git /var/www/gaceta
cd /var/www/gaceta

composer install --no-dev --optimize-autoloader
npm ci && npm run build

cp .env.example .env
php artisan key:generate
```

### B.4 Configuración (.env de producción)

```ini
APP_NAME="Gaceta Universitaria UPEA"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<dominio-de-la-gaceta>
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=gaceta_upea
DB_USERNAME=gaceta
DB_PASSWORD=<contraseña-segura>

SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=<dominio-de-la-gaceta>

ADMIN_USUARIO=admin
ADMIN_CORREO=<correo-del-administrador>
ADMIN_CONTRASENA=<contraseña-temporal>

GACETA_PDF_MAX_MB=20
GACETA_RESPALDO_DIAS=30
GACETA_RESPALDO_CONTENEDOR=
```

> **Nunca** use `APP_DEBUG=true` en producción: mostraría información interna ante un error.

### B.5 Instalar la base y optimizar

```bash
php artisan migrate --force
php artisan db:seed --force          # solo datos iniciales y administrador (NO ejecutar DemoSeeder)

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### B.6 PHP

En `/etc/php/8.3/fpm/php.ini`:

```ini
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
expose_php = Off
```

```bash
sudo systemctl restart php8.3-fpm
```

### B.7 Nginx

`/etc/nginx/sites-available/gaceta`:

```nginx
server {
    listen 80;
    server_name <dominio-de-la-gaceta>;
    root /var/www/gaceta/public;
    index index.php;

    client_max_body_size 25M;   # PDF de hasta 20 MB

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    }

    location ~ /\.(?!well-known) { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/gaceta /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### B.8 HTTPS

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d <dominio-de-la-gaceta>
```

### B.9 Tareas programadas (respaldo diario)

```bash
sudo crontab -u www-data -e
```

Agregar:

```
* * * * * cd /var/www/gaceta && php artisan schedule:run >> /dev/null 2>&1
```

El respaldo se ejecuta a diario a las 02:00. Verifique con `php artisan schedule:list`.

### B.10 Primer ingreso

1. Abra `https://<dominio-de-la-gaceta>/login` e ingrese con `admin` y la contraseña temporal.
2. Cambie la contraseña cuando el sistema lo pida.
3. Cree las cuentas del personal (**Usuarios**) y entregue a cada uno su contraseña temporal.
4. Borre `ADMIN_CONTRASENA` del `.env` y ejecute `php artisan config:cache`.

### B.11 Lista de verificación

- [ ] `APP_ENV=production` y `APP_DEBUG=false`.
- [ ] HTTPS activo y `SESSION_SECURE_COOKIE=true`.
- [ ] `SANCTUM_STATEFUL_DOMAINS` con el dominio real (la campana de notificaciones lo necesita).
- [ ] Límites de PHP (20M / 25M) y de Nginx (`client_max_body_size 25M`).
- [ ] `storage/` y `bootstrap/cache/` con permisos para `www-data`.
- [ ] Extensión `pg_trgm` creada.
- [ ] Tarea programada (`schedule:run`) activa y un respaldo de prueba realizado.
- [ ] Respaldos copiados periódicamente fuera del servidor.
- [ ] El puerto de PostgreSQL (5432) **no** está expuesto a Internet.
- [ ] No se ejecutó `DemoSeeder` en producción.

---

## Parte C. Respaldos y restauración

### C.1 Respaldo manual

```bash
php artisan gaceta:respaldo                  # base de datos + PDF
php artisan gaceta:respaldo --sin-archivos   # solo base de datos
```

Resultado en `storage/app/respaldos/AAAA-MM-DD_HHMMSS/`:

| Archivo | Contenido |
|---|---|
| `base_datos.dump` | Base de datos completa (formato de `pg_dump`). |
| `documentos.zip` | Todos los PDF. |
| `respaldo.json` | Fecha, cantidad de PDF, tamaños y huellas SHA-256. |

Los respaldos con más de `GACETA_RESPALDO_DIAS` días se eliminan automáticamente.

> **Copie los respaldos a otro equipo o almacenamiento externo.** Un respaldo que solo está en el mismo servidor
> no protege ante una falla del disco.

### C.2 Restaurar un respaldo

```bash
cd /var/www/gaceta
php artisan down                                     # modo mantenimiento

# 1. Base de datos
pg_restore --clean --if-exists --no-owner -h 127.0.0.1 -U gaceta -d gaceta_upea \
  storage/app/respaldos/<carpeta>/base_datos.dump

# 2. Archivos PDF
unzip -o storage/app/respaldos/<carpeta>/documentos.zip -d storage/app/private/
sudo chown -R www-data:www-data storage

php artisan up
```

En desarrollo con Docker, restaurar la base:

```powershell
Get-Content -AsByteStream .\storage\app\respaldos\<carpeta>\base_datos.dump |
  docker exec -i gaceta_postgres pg_restore --clean --if-exists --no-owner -U gaceta -d gaceta_upea
```

---

## Parte D. Actualizar el sistema

```bash
cd /var/www/gaceta
php artisan gaceta:respaldo      # respaldo previo
php artisan down

git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force

php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan up
```

---

## Parte E. Solución de problemas

| Síntoma | Causa probable | Solución |
|---|---|---|
| Página sin estilos | No se compilaron los estilos o quedó activo `npm run dev` | `npm run build`; borrar `public/hot` si existe. |
| Error al subir un PDF de más de 2 MB | Límites de PHP | `upload_max_filesize` y `post_max_size` (A.1 / B.6) y `client_max_body_size` en Nginx. |
| «Database connection [psql] not configured» | Error de escritura en `.env` | `DB_CONNECTION=pgsql`. |
| «Bind for 0.0.0.0:5434 failed: port is already allocated» | Puerto ocupado por otro contenedor | Cambiar `DB_PORT` en `.env` y `docker compose up -d`. |
| «The environment file is invalid» | Un valor con espacios sin comillas en `.env` | Encerrar entre comillas, por ejemplo `APP_NAME="Gaceta Universitaria UPEA"`. |
| La campana de notificaciones no se actualiza | El dominio o IP no está en `SANCTUM_STATEFUL_DOMAINS` | Agregar el dominio con su puerto y `php artisan config:clear` (o `config:cache` en producción). |
| Error 419 «La sesión expiró» | Página abierta mucho tiempo | Recargar la página. |
| Error 500 en producción | Ver el detalle en `storage/logs/laravel.log` | Corregir la causa; nunca activar `APP_DEBUG` en producción. |
| El respaldo falla: «pg_dump no pudo respaldar…» | `pg_dump` no instalado o contenedor incorrecto | Instalar `postgresql-client-17` o revisar `GACETA_RESPALDO_CONTENEDOR`. |
| Cambios en `.env` sin efecto en producción | Configuración en caché | `php artisan config:cache`. |
