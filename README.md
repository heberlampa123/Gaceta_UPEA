# Sistema Web Integral de Gestión de la Gaceta Universitaria — UPEA

Plataforma web para **registrar, revisar, aprobar, publicar y consultar** los documentos oficiales de la
Universidad Pública de El Alto. La primera versión gestiona las **Resoluciones del Honorable Consejo Universitario (HCU)**
y permite incorporar nuevos tipos documentales desde el panel, sin modificar el sistema.

## Funciones principales

| Módulo | Descripción |
|---|---|
| Panel administrativo | Gestión de documentos, revisión y publicación, tipos documentales, usuarios, roles, auditoría y notificaciones. |
| Portal público | Búsqueda con filtros, consulta y descarga de los documentos publicados (sin iniciar sesión). |
| Flujo documental | Borrador → Pendiente de revisión → (Observado ↔ Pendiente) → Aprobado → Publicado. |
| Versiones | Cada PDF conserva sus versiones anteriores, con su huella SHA-256 para verificar su integridad. |
| Seguridad | Roles y permisos por código, bloqueo por intentos fallidos, auditoría de todas las operaciones. |

## Tecnologías

Laravel 13 · PHP 8.3+ · PostgreSQL 17 · Eloquent · Blade + Bootstrap 5 · Axios · API REST con Sanctum · Docker (desarrollo) · Git.

## Inicio rápido (desarrollo)

```bash
composer install
npm install && npm run build
cp .env.example .env          # completar contraseñas
php artisan key:generate
docker compose up -d          # PostgreSQL (puerto 5434) y pgAdmin (http://localhost:5050)
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder   # opcional: datos de demostración
php artisan serve             # http://localhost:8000
```

Pruebas automáticas: `php artisan test` (usan la base `gaceta_upea_test`).

## Documentación

| Documento | Contenido |
|---|---|
| [Manual de usuario](docs/manuales/MANUAL_USUARIO.md) | Uso del sistema por rol: Administrador, Encargado de documentos, Revisor / Publicador y visitantes del portal. |
| [Manual técnico](docs/manuales/MANUAL_TECNICO.md) | Arquitectura, modelo de datos, seguridad, API, pruebas y mantenimiento. |
| [Guía de instalación y despliegue](docs/manuales/GUIA_INSTALACION_DESPLIEGUE.md) | Instalación en desarrollo y en un servidor de producción, respaldos y actualización. |
| [Mapa del código](docs/MAPA_DEL_CODIGO.md) | Dónde se modifica cada función del sistema. |
| [Plan de trabajo](docs/PLAN_DE_TRABAJO.md) | Fases del proyecto, decisiones y avance. |

Las versiones en Word (.docx) de los manuales están en `docs/manuales/word/`.
