# Manual técnico

**Sistema Web Integral de Gestión de la Gaceta Universitaria — Universidad Pública de El Alto**

Versión 1.0 · Noviembre de 2026

---

## Contenido

1. [Arquitectura](#1-arquitectura)
2. [Tecnologías](#2-tecnologías)
3. [Estructura del proyecto](#3-estructura-del-proyecto)
4. [Modelo de datos](#4-modelo-de-datos)
5. [Seguridad](#5-seguridad)
6. [Flujo documental](#6-flujo-documental)
7. [Archivos PDF y versiones](#7-archivos-pdf-y-versiones)
8. [Notificaciones y auditoría](#8-notificaciones-y-auditoría)
9. [API REST](#9-api-rest)
10. [Interfaz (frontend)](#10-interfaz-frontend)
11. [Configuración](#11-configuración)
12. [Pruebas automáticas](#12-pruebas-automáticas)
13. [Mantenimiento y extensión](#13-mantenimiento-y-extensión)

> Para ubicar el archivo exacto de cada función, consulte el [Mapa del código](../MAPA_DEL_CODIGO.md).

---

## 1. Arquitectura

Aplicación web cliente-servidor en tres capas, desarrollada como **un único proyecto Laravel** que genera las vistas
(Blade) y expone una API REST (JSON).

| Capa | Componentes |
|---|---|
| **Presentación** | Vistas Blade + Bootstrap 5 (panel administrativo y portal público); JavaScript con Axios para las partes que se actualizan sin recargar (búsqueda del portal y notificaciones). |
| **Lógica de aplicación** | Controladores, solicitudes de validación (Form Requests), políticas de autorización, servicios de dominio (`GestionDocumental`, `FlujoDocumental`, `ConsultaPublica`), eventos y oyentes, middleware. |
| **Datos** | PostgreSQL 17 mediante Eloquent. Los PDF se guardan en el almacenamiento privado de Laravel; sus rutas y huellas, en la tabla `version_documento`. |

Principios aplicados:

- **Lógica en servicios**: los controladores solo validan, autorizan y delegan.
- **Reglas en dos niveles**: la aplicación valida y la base de datos impone las restricciones críticas (índices únicos, `CHECK`, triggers).
- **Autorización por código de permiso**, nunca por nombre de rol.
- **Trazabilidad**: seguimiento por documento y auditoría de todas las operaciones, ambos de solo inserción.

---

## 2. Tecnologías

| Componente | Tecnología | Versión |
|---|---|---|
| Framework | Laravel | 13.x |
| Lenguaje | PHP | 8.3 o superior (desarrollado con 8.4) |
| Base de datos | PostgreSQL | 17 (extensión `pg_trgm`) |
| ORM | Eloquent | incluido en Laravel |
| API y autenticación API | Laravel Sanctum | 4.x |
| Interfaz | Blade, Bootstrap 5.3, Bootstrap Icons | — |
| Peticiones sin recargar | Axios (no `fetch`) | 1.x |
| Compilación de estilos y scripts | Vite | 7.x |
| Traducciones | laravel-lang/common (español) | 6.x |
| Desarrollo | Docker (PostgreSQL y pgAdmin), Composer, Node.js, Git | — |
| Pruebas | PHPUnit | incluido en Laravel |

---

## 3. Estructura del proyecto

```
app/
  Console/Commands/      Comando de respaldo (gaceta:respaldo)
  Events/                DocumentoCambioDeEstado
  Exceptions/            TransicionNoPermitida
  Http/
    Controllers/
      Api/               API JSON: búsqueda pública y notificaciones
      Auth/              Inicio y cierre de sesión
      Panel/             Panel administrativo
      Portal/            Portal público
    Middleware/          VerificarCuenta, CabecerasSeguridad
    Requests/            Validaciones de formularios
    Resources/           Formato JSON de la API
  Listeners/             NotificarCambioDeEstado
  Models/                13 modelos Eloquent (uno por entidad)
  Policies/              DocumentoPolicy (permiso + estado + revisor distinto)
  Services/              GestionDocumental, FlujoDocumental, ConsultaPublica
  Support/               Auditor, LimitesArchivo, PdfSimple
config/gaceta.php        Configuración propia del sistema
database/
  migrations/            Esquema de la base de datos
  seeders/               Datos iniciales y de demostración
  factories/             Datos de prueba
docker/                  pgAdmin y script de la base de pruebas
docs/                    Plan de trabajo, mapa del código y manuales
resources/
  scss/app.scss          Tema visual (Bootstrap personalizado con Sass)
  js/                    app.js y módulos (portal, notificaciones, campos adicionales, flujo)
  views/                 Vistas Blade (layouts, auth, panel, portal, errors)
routes/
  web.php                Portal, autenticación y panel
  api.php                API REST
  console.php            Tareas programadas
tests/                   Pruebas automáticas
```

---

## 4. Modelo de datos

### 4.1 Tablas

Modelo normalizado en tercera forma normal (13 entidades):

| Grupo | Tabla | Propósito |
|---|---|---|
| Administración | `usuario` | Cuentas del panel. No se eliminan: se desactivan. |
| | `rol` | Perfiles de acceso (ADMIN, ENCARGADO, REVISOR). |
| | `permiso` | Acciones autorizables, identificadas por código. |
| | `usuario_rol` | Usuarios ↔ roles (clave primaria compuesta). |
| | `rol_permiso` | Roles ↔ permisos (clave primaria compuesta). |
| Gestión documental | `tipo_documental` | Tipos de documento y definición de sus campos adicionales (`campos_adicionales`, JSONB). |
| | `documento` | Datos comunes de cada documento; datos propios del tipo en `metadatos` (JSONB). |
| | `version_documento` | Versiones del PDF: ruta, tamaño, huella SHA-256, motivo y vigencia. |
| Seguimiento | `estado_documento` | Estados del flujo, identificados por código. |
| | `seguimiento_documento` | Historial de cada documento (solo inserción). |
| | `observacion` | Observaciones del revisor sobre una versión, con su respuesta. |
| Comunicación | `notificacion` | Avisos internos a los usuarios. |
| Control | `auditoria` | Operaciones de los usuarios (solo inserción). |

Además existen las tablas propias de Laravel: `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`,
`failed_jobs`, `personal_access_tokens` y `migrations`.

**Campos agregados al modelo del análisis** (decisiones del 12/11/2026):

- `usuario.bloqueado_hasta`: bloqueo temporal tras intentos fallidos.
- `usuario.debe_cambiar_contrasena`: obliga a cambiar la contraseña asignada por el administrador.

### 4.2 Restricciones de integridad

| Regla | Implementación |
|---|---|
| Documento único por tipo, número y gestión (entre documentos activos) | Índice único parcial `documento_tipo_numero_gestion_activo_unique … WHERE activo` (un borrador anulado libera su número) |
| Una sola versión vigente por documento | Índice único parcial `version_documento_una_vigente ON (id_documento) WHERE es_vigente` |
| Número de versión único por documento | `UNIQUE (id_documento, numero_version)` |
| Observación atendida completa | `CHECK`: `respuesta`, `id_usuario_atiende` y `fecha_atencion` todos nulos o todos con valor |
| Seguimiento y auditoría inalterables | Triggers que rechazan `UPDATE` y `DELETE` (función `impedir_modificacion`) |
| Estado con documentos no desactivable | Trigger `trg_estado_documento_desactivacion` |
| Sin eliminación física | Claves foráneas `RESTRICT`; borrado lógico con `activo` |

### 4.3 Índices de búsqueda

| Índice | Uso |
|---|---|
| `documento_busqueda_idx` (GIN, `to_tsvector('spanish', …)`) | Búsqueda por palabras clave en número, título y descripción, con raíces en español («convocatorias» encuentra «convocatoria»). |
| `documento_numero_trgm_idx`, `documento_titulo_trgm_idx` (GIN, `pg_trgm`) | Búsqueda por fragmentos (`ILIKE '%texto%'`). |
| Índices B-tree en `gestion`, `fecha_emision`, `id_estado_actual` | Filtros y orden. |

**Rendimiento medido** con 20.000 documentos: búsqueda frecuente en menos de 1 ms; peor caso (término inexistente,
con conteo total) en unos 8 ms (antes de los índices de trigramas: unos 90 ms).

---

## 5. Seguridad

| Aspecto | Implementación |
|---|---|
| Contraseñas | Hash bcrypt (columna `contrasena_hash`); nunca se guardan ni se auditan en texto plano. Política: mínimo 8 caracteres con letras y números (`AppServiceProvider`). |
| Inicio de sesión | Por nombre de usuario o correo, sin distinguir mayúsculas. Los nombres inexistentes reciben el mismo tratamiento de bloqueo que las cuentas reales, para no revelar qué cuentas existen. Bloqueo de 15 minutos tras 5 intentos fallidos (`Usuario::MAX_INTENTOS_FALLIDOS`, `MINUTOS_BLOQUEO`). Límite de 10 intentos por minuto por IP. |
| Contraseña temporal | Middleware `VerificarCuenta`: obliga a cambiarla antes de usar el panel. |
| Cuentas desactivadas | `VerificarCuenta` cierra la sesión en la siguiente petición; la API responde 403. |
| Cambio de contraseña | Middleware `auth.session` (AuthenticateSession): al cambiar o restablecer la contraseña se cierran las demás sesiones abiertas de la cuenta; el restablecimiento también revoca los tokens de API. |
| Autorización | `Gate::before` resuelve cualquier habilidad con forma `modulo.accion` mediante `Usuario::tienePermiso()`. `DocumentoPolicy` añade el estado del documento y la regla de revisor distinto. |
| CSRF | Activo en formularios y en la API con sesión (Sanctum `statefulApi`). |
| Archivos | PDF en disco privado (`storage/app/private`); solo se entregan mediante controladores que verifican permiso o publicación. Se valida extensión, tipo MIME y cabecera `%PDF-`. |
| Cabeceras | `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`, `Content-Security-Policy` (solo scripts propios, sin scripts en línea; en páginas HTML) y `Strict-Transport-Security` en producción con HTTPS. |
| Doble envío | Los botones se desactivan al enviar; si una acción del flujo ya no corresponde al estado, se muestra un aviso en lugar de un 403 (`Controller::autorizarEnFlujo`). |
| Límites de uso | Portal: 60 búsquedas y 30 descargas por minuto por IP. |
| Datos públicos | El portal y la API pública solo exponen documentos publicados y nunca datos internos (rutas, usuarios, estados). |
| Contenido dinámico | El JavaScript inserta datos con `textContent`, nunca como HTML. |

---

## 6. Flujo documental

El servicio `App\Services\FlujoDocumental` centraliza las transiciones (constante `TRANSICIONES`):

| Acción | Desde | Hacia | Permiso | Revisor distinto |
|---|---|---|---|---|
| `enviarARevision` | Borrador | Pendiente de revisión | `documento.enviar_revision` | — |
| `devolver` | Pendiente de revisión | Observado | `documento.revisar` | Sí |
| `reenviar` | Observado | Pendiente de revisión | `observacion.atender` | — |
| `aprobar` | Pendiente de revisión | Aprobado | `documento.aprobar` | Sí |
| `publicar` | Aprobado | Publicado | `documento.publicar` | Sí |

Cada transición, dentro de una transacción:

1. Bloquea la fila del documento (`SELECT … FOR UPDATE`).
2. Verifica que el estado actual sea el de origen; si no, lanza `TransicionNoPermitida`.
3. Verifica que el documento esté activo y, al enviar, reenviar o aprobar, que tenga completos los campos obligatorios de su tipo.
4. Aplica las reglas propias (observaciones obligatorias, respuestas completas, sin observaciones pendientes al aprobar…).
5. Cambia el estado, registra el seguimiento y la auditoría.

Después de confirmar la transacción emite el evento `DocumentoCambioDeEstado`.

---

## 7. Archivos PDF y versiones

Servicio `App\Services\GestionDocumental`:

- **Ruta**: `storage/app/private/documentos/{gestión}/{uuid}.pdf` (disco y carpeta en `config/gaceta.php`).
- **Tamaño máximo**: `GACETA_PDF_MAX_MB` (20 MB). PHP debe permitirlo: `upload_max_filesize` ≥ 20M y `post_max_size` ≥ 25M.
- **Nueva versión**: en una transacción, retira la vigencia de la anterior y crea la nueva con número correlativo; rechaza un archivo idéntico al vigente.
- **Integridad**: `verificarIntegridad()` recalcula la huella SHA-256 y la compara con la registrada.
- Si la base de datos falla al registrar, el archivo guardado se elimina.
- **Anulación**: `anular()` desactiva un borrador registrado por error (`activo = false`), con motivo, seguimiento (`ANULACION`) y auditoría; su número queda disponible.
- **Validaciones de coherencia** (`GuardarDocumentoRequest::after`): el año de la fecha de emisión debe ser el de la gestión, y el año del número (`…/AAAA`), si lo tiene, también.

---

## 8. Notificaciones y auditoría

**Notificaciones** — `App\Listeners\NotificarCambioDeEstado` (registrado automáticamente por Laravel):

| Nuevo estado | Tipo | Destinatarios |
|---|---|---|
| Pendiente de revisión | `NUEVO_PENDIENTE` | Usuarios activos con `documento.revisar`, excepto quien registró |
| Observado | `OBSERVADO` | Quien registró |
| Aprobado | `APROBADO` | Quien registró |
| Publicado | `PUBLICADO` | Quien registró |

Nunca se notifica a quien realizó la acción.

**Auditoría** — `App\Support\Auditor::registrar()`, `creacion()` y `modificacion()`. Guarda el usuario, la acción,
la entidad, el ID, el detalle (valores anteriores y nuevos, sin contraseñas) y la IP.

---

## 9. API REST

| Método y ruta | Autenticación | Descripción |
|---|---|---|
| `GET /api/documentos` | Pública (límite 60/min por IP) | Búsqueda de documentos publicados. Parámetros: `q`, `tipo`, `gestion`, `numero`, `desde`, `hasta` (AAAA-MM-DD), `instancia`, `orden` (`recientes`, `antiguos`, `numero`), `page`. |
| `GET /api/notificaciones/resumen` | Sanctum (sesión o token) | `{ no_leidas, recientes: [...] }` del usuario autenticado. |
| `PATCH /api/notificaciones/{id}/leer` | Sanctum | Marca una notificación propia como leída. |
| `POST /api/notificaciones/leer-todas` | Sanctum | Marca todas como leídas. |
| `GET /api/user` | Sanctum | Datos del usuario autenticado. |

Ejemplo de respuesta de `GET /api/documentos?q=reglamento`:

```json
{
  "data": [{
    "id": 24, "tipo": "Resolución del HCU", "numero": "025/2026", "gestion": 2026,
    "titulo": "Resolución que aprueba el Reglamento de Titulación",
    "resumen": "…", "instancia_emisora": "Honorable Consejo Universitario",
    "fecha_emision": "2026-11-10", "fecha_emision_texto": "10/11/2026",
    "url": "…/documentos/24", "url_pdf": "…/documentos/24/pdf", "url_descarga": "…/documentos/24/pdf?descargar=1"
  }],
  "links": { "…": "…" },
  "meta": { "current_page": 1, "last_page": 1, "total": 1, "…": "…" },
  "filtros": { "q": "reglamento", "orden": "recientes" }
}
```

**Sesión en la API**: Sanctum reconoce la sesión del panel solo en los dominios de `config/sanctum.php`
(`SANCTUM_STATEFUL_DOMAINS` o `APP_URL`). Si el sistema se publica en otro dominio o IP, agréguelo con su puerto.

---

## 10. Interfaz (frontend)

- **Plantillas**: `layouts/base` (común), `layouts/panel` (menú lateral, campana, usuario), `layouts/portal` (encabezado institucional).
- **Estilos**: Bootstrap 5.3 personalizado con Sass en `resources/scss/app.scss` (paleta del escudo de la UPEA, fuente Inter instalada en el proyecto, franja con los colores de la wiphala). Solo modo claro en la primera versión (`$enable-dark-mode: false`). Logo: `public/img/logo-upea.png`.
- **JavaScript** (`resources/js/`), todos los módulos se inician desde `app.js`:

| Módulo | Función |
|---|---|
| `portal.js` | Búsqueda del portal con Axios, paginación y URL compartible. |
| `notificaciones.js` | Campana: consulta cada 60 s y al volver a la pestaña; marcar todas como leídas. |
| `campos-adicionales.js` | Editor de campos de los tipos documentales y campos según el tipo en el formulario de documentos. |
| `flujo-documental.js` | «Agregar otra observación». |
| `bootstrap.js` | Configuración de Axios (`window.axios`). |

Después de modificar CSS o JS: `npm run build`.

---

## 11. Configuración

Variables propias del sistema en `.env` (ver también `.env.example`):

| Variable | Uso | Valor de desarrollo |
|---|---|---|
| `APP_URL` | Dirección del sistema (también la usa Sanctum) | `http://localhost:8000` |
| `DB_*` | Conexión a PostgreSQL | `127.0.0.1`, `5434`, `gaceta_upea`, `gaceta` |
| `PGADMIN_EMAIL`, `PGADMIN_PASSWORD` | Acceso a pgAdmin (solo desarrollo) | — |
| `ADMIN_USUARIO`, `ADMIN_CORREO`, `ADMIN_CONTRASENA` | Administrador inicial (`AdministradorSeeder`) | `admin` |
| `GACETA_PDF_MAX_MB` | Tamaño máximo del PDF | `20` |
| `GACETA_RESPALDO_DIAS` | Días que se conservan los respaldos | `30` |
| `GACETA_RESPALDO_CONTENEDOR` | Contenedor Docker de PostgreSQL para el respaldo; vacío en producción | `gaceta_postgres` |
| `GACETA_PG_DUMP` | Ruta de `pg_dump` en producción | `pg_dump` |
| `SANCTUM_STATEFUL_DOMAINS` | Dominios en los que la API reconoce la sesión | (por defecto, a partir de `APP_URL`) |

Zona horaria: `America/La_Paz` (`config/app.php`). Idioma: español (`APP_LOCALE=es`).

---

## 12. Pruebas automáticas

```bash
php artisan test                         # todas
php artisan test --filter=FlujoDocumentalTest
```

Usan la base `gaceta_upea_test` (configurada en `phpunit.xml`; se crea con `docker/postgres/init/01-crear-bd-pruebas.sh`)
y nunca la base de desarrollo. **96 pruebas** cubren:

| Archivo | Cubre |
|---|---|
| `Database/ModeloDatosTest` | Catálogos, permisos, restricciones, triggers y búsqueda. |
| `Auth/InicioSesionTest` | Inicio de sesión, bloqueo, cuentas desactivadas, contraseña temporal. |
| `Panel/UsuarioAdministracionTest`, `RolAdministracionTest`, `PerfilTest` | Administración y perfil. |
| `Panel/GestionDocumentalTest` | Registro, validación de PDF, versiones, edición por estado, integridad. |
| `Panel/TipoDocumentalTest` | Tipos y campos adicionales. |
| `Panel/FlujoDocumentalTest` | Transiciones, roles, revisor distinto, concurrencia. |
| `Portal/PortalPublicoTest` | Visibilidad, filtros, API, límites. |
| `Panel/NotificacionesAuditoriaTest` | Destinatarios, API de la campana, auditoría y tablero. |
| `SeguridadYRespaldoTest` | Cabeceras, páginas de error y respaldo. |
| `CorreccionesRevisionTest` | Regresión de las 10 brechas corregidas en la revisión del 13/11/2026. |

---

## 13. Mantenimiento y extensión

### 13.1 Respaldos

```bash
php artisan gaceta:respaldo                # base de datos + PDF
php artisan gaceta:respaldo --sin-archivos # solo base de datos
```

Crea `storage/app/respaldos/AAAA-MM-DD_HHMMSS/` con `base_datos.dump`, `documentos.zip` y `respaldo.json`
(tamaños y huellas SHA-256). Elimina los respaldos con más de `GACETA_RESPALDO_DIAS` días. Se programa a diario
a las 02:00 (`routes/console.php`); requiere `php artisan schedule:run` cada minuto en el servidor.

**Copie los respaldos fuera del servidor** (otro equipo o almacenamiento externo).

**Restaurar** (ver la Guía de instalación y despliegue, sección «Restaurar un respaldo»):

```bash
pg_restore --clean --if-exists --no-owner -h <host> -U <usuario> -d <base> base_datos.dump
# y descomprimir documentos.zip dentro de storage/app/private/
```

### 13.2 Registros (logs)

`storage/logs/laravel.log`. En producción use `LOG_LEVEL=warning`.

### 13.3 Agregar un tipo documental

Desde el panel (**Tipos documentales**), sin programar. Ver el Manual de usuario, sección 7.3.

### 13.4 Agregar un permiso

1. Agregarlo en `database/seeders/RolesYPermisosSeeder.php` (`PERMISOS` y, si corresponde, en `ROLES`).
2. Ejecutar `php artisan db:seed --class=RolesYPermisosSeeder` (no duplica datos).
3. Usarlo en rutas (`can:modulo.accion`), vistas (`@can`) o controladores (`$this->authorize`).

### 13.5 Agregar un estado o una transición

Requiere programación: agregar el estado en `EstadoDocumentoSeeder`, la transición en `FlujoDocumental::TRANSICIONES`
con su método, la regla en `DocumentoPolicy`, el destinatario en `NotificarCambioDeEstado` y las pruebas correspondientes.

### 13.6 Cambios de esquema

Siempre mediante una **nueva migración** (`php artisan make:migration …`); nunca modificando migraciones ya aplicadas en producción.
