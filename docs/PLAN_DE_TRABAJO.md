# Plan de Trabajo — Sistema Web Integral de Gestión de la Gaceta Universitaria (UPEA)

> Basado en: *Sistema Web Integral de Gestión de la Gaceta Universitaria de la UPEA* (análisis, 35 págs.) y *Normalización de la Base de Datos* (10 págs.).
> Alcance: **Versión 1 — Resoluciones del HCU**.
> Stack: Laravel 13 · PHP 8.3+ · PostgreSQL (Docker) · Eloquent · Blade + Bootstrap 5 · API REST con Sanctum · Git.

---

## 1. Trazabilidad: objetivos → requisitos → fases

| Objetivo específico | Requisitos | Fase(s) |
|---|---|---|
| OE1. Analizar necesidades y procesos | — | 0 (hecho) + validación con la Gaceta |
| OE2. Estructura modular para varios tipos documentales | RF-05, RNF-05 | 1, 4 |
| OE3. Registro, revisión, aprobación y publicación | RF-02, RF-03, RF-07 | 3, 5 |
| OE4. Búsqueda y consulta pública | RF-06 | 6 |
| OE5. Controles de acceso, usuarios e historial | RF-01, RF-09, RNF-01 | 2, 7 |
| OE6. Almacenamiento y control de versiones de PDF | RF-04 | 3 |
| (transversal) Notificaciones internas | RF-08 | 7 |

---

## 2. Fases

### Fase 0 — Preparación del entorno ✅ COMPLETADA (12/11/2026)
- [x] Laravel 13 instalado en `C:\SigGacetaUniv`.
- [x] Repositorio Git (`main`).
- [x] `docker-compose.yml`: PostgreSQL 17 (puerto **5434** en Windows; 5432 y 5433 ya estaban ocupados) y pgAdmin 4 (http://localhost:5050) con el servidor registrado previamente.
- [x] `.env` / `.env.example`: conexión `pgsql`, `APP_LOCALE=es`, `APP_FAKER_LOCALE=es_ES`; zona horaria `America/La_Paz` en `config/app.php`.
- [x] Sanctum instalado (`routes/api.php`, tabla `personal_access_tokens`).
- [x] Tailwind reemplazado por Bootstrap 5.3 (Vite).
- [x] Traducciones al español (`laravel-lang/common`, carpeta `lang/es`).
- [x] Verificado: página en :8000 (200), API protegida (401 sin token), pgAdmin (200), migraciones en PostgreSQL y `php artisan test` aprobado.

### Fase 1 — Base de datos (diseño físico)
Implementar las 13 tablas del modelo 3FN como migraciones.

- [ ] Migraciones en orden de dependencias: `rol`, `permiso`, `usuario`, `usuario_rol`, `rol_permiso`, `tipo_documental`, `estado_documento`, `documento`, `version_documento`, `seguimiento_documento`, `observacion`, `notificacion`, `auditoria`.
- [ ] Columnas `JSONB`: `tipo_documental.campos_adicionales`, `documento.metadatos`, `auditoria.detalle`.
- [ ] Restricciones de integridad (sección 4.4.15):
  - `UNIQUE (id_tipo_documental, numero, gestion)` en documento.
  - `UNIQUE (id_documento, numero_version)` en version_documento.
  - Índice único parcial: `CREATE UNIQUE INDEX ... ON version_documento (id_documento) WHERE es_vigente`.
  - `CHECK` de observación atendida: `respuesta`, `id_usuario_atiende`, `fecha_atencion` todos NULL o todos NOT NULL.
  - FK opcionales solo donde lo indica el documento.
- [ ] Protección de tablas solo-inserción (`auditoria`, `seguimiento_documento`): trigger que impida `UPDATE`/`DELETE`.
- [ ] Índices de búsqueda: `gestion`, `fecha_emision`, `id_estado_actual`, y búsqueda de texto completo (`tsvector` en español sobre `titulo`, `descripcion`, `numero`).
- [ ] Modelos Eloquent con relaciones; adaptar el modelo `User` de Laravel a la tabla `usuario` (`contrasena_hash`, `nombre_usuario`).
- [ ] Seeders: 3 roles, catálogo de permisos, 5 estados, tipo `RES_HCU`, usuario administrador inicial; factories para datos de prueba.

**Entregable:** `php artisan migrate --seed` crea la BD completa y coincide con la Figura 9 del análisis.

### Fase 2 — Seguridad y administración (RF-01, RNF-01)
- [ ] Login con `nombre_usuario` o correo; sin registro público (cuentas solo por el administrador).
- [ ] Bloqueo temporal por `intentos_fallidos`; actualización de `ultimo_acceso`.
- [ ] Middleware/Gates que verifican **códigos de permiso** (`documento.registrar`, `documento.revisar`, `documento.publicar`, `usuario.gestionar`, ...), nunca nombres de rol.
- [ ] CRUD de usuarios con desactivación lógica (sin borrado físico).
- [ ] Gestión de roles y asignación de permisos.
- [ ] Mi perfil: datos y cambio de contraseña.
- [ ] Layout del panel (Bootstrap 5) con menú según permisos (Figura 2).

**Entregable:** el administrador crea cuentas y cada rol ve solo lo que le corresponde.

### Fase 3 — Gestión documental y versiones (RF-02, RF-04)
- [ ] Registro de resolución: tipo, número, gestión, fecha de emisión, título, descripción, instancia emisora, metadatos dinámicos.
- [ ] Carga de PDF: validación MIME/tamaño, nombre único, disco **privado** (`storage/app/private`), cálculo de `hash_sha256` y `tamano_bytes`.
- [ ] Nueva versión en una transacción: la anterior deja de ser vigente, `numero_version` correlativo, `motivo_cambio` obligatorio.
- [ ] Listado interno con filtros por estado/gestión; ficha con historial de versiones.
- [ ] Edición permitida solo en estados `BORRADOR` y `OBSERVADO`.

**Entregable:** el Encargado registra una resolución y gestiona sus versiones de PDF.

### Fase 4 — Tipos documentales (RF-05)
- [ ] CRUD de tipos documentales con activación/desactivación.
- [ ] Editor de `campos_adicionales` (nombre, tipo de dato, obligatorio).
- [ ] Formulario de documento que genera y valida los campos dinámicos según el tipo.

**Entregable:** se puede crear un tipo nuevo (p. ej. «Resolución Rectoral») sin tocar el código.

### Fase 5 — Flujo de revisión y publicación (RF-03, RF-07)
- [ ] Servicio de **máquina de estados** con las 5 transiciones permitidas (sección 4.4.10); cualquier otra se rechaza.
- [ ] Cada transición, en una transacción: actualiza `id_estado_actual`, inserta en `seguimiento_documento`, dispara notificación y auditoría.
- [ ] Bandeja del Revisor: pendientes de revisión, visor PDF, registrar observaciones sobre la versión.
- [ ] Atención de observaciones por el Encargado (respuesta + nueva versión) y reenvío.
- [ ] Aprobar y publicar (registra `fecha_publicacion`).
- [ ] Línea de tiempo del documento (como el ejemplo de la sección 2.4).

**Entregable:** flujo completo Borrador → Pendiente → (Observado ↔ Pendiente) → Aprobado → Publicado.

### Fase 6 — Portal público (RF-06)
- [ ] Página pública (Figura 3): encabezado institucional, buscador, filtros (tipo, gestión, número, fecha de emisión, instancia emisora), listado paginado.
- [ ] Solo muestra documentos en estados con `es_publico = true` y `activo = true`.
- [ ] Ficha del documento con visor PDF embebido y descarga, servidos por controlador (nunca la ruta directa del archivo) y solo la versión vigente.
- [ ] Endpoints API JSON públicos de búsqueda (con *rate limiting*), consumidos por JavaScript para filtrar sin recargar.

**Entregable:** cualquier visitante busca, ve y descarga resoluciones publicadas.

### Fase 7 — Notificaciones, auditoría y tablero (RF-08, RF-09)
- [ ] Notificaciones: `NUEVO_PENDIENTE` → usuarios con permiso de revisar; `OBSERVADO` y `APROBADO` → encargado que registró; `PUBLICADO` → encargado (y revisores).
- [ ] Campana con contador de no leídas (API + Sanctum, consulta periódica), marcar como leída.
- [ ] Auditoría automática (observers/eventos): inicio de sesión (exitoso y fallido), crear, modificar, desactivar, cambios de permisos, publicar; con valores anteriores y nuevos en `detalle` y la IP.
- [ ] Consulta de auditoría con filtros (usuario, acción, entidad, fechas).
- [ ] Tablero de inicio: conteos por estado, pendientes del usuario, actividad reciente.

**Entregable:** cada acción relevante queda registrada y los usuarios reciben sus avisos.

### Fase 8 — Pruebas, ajustes y documentación
- [ ] Pruebas automatizadas (Pest/PHPUnit): transiciones válidas e inválidas, permisos por rol, versión vigente única, visibilidad pública, unicidad del documento.
- [ ] Pruebas con usuarios de la Gaceta (aceptación) y ajustes de usabilidad (RNF-04).
- [ ] Revisión de rendimiento de búsquedas con datos de volumen realista (RNF-02).
- [ ] Manual de usuario por rol, manual técnico y guía de despliegue.
- [ ] Definir con la unidad responsable el servidor de producción y las copias de seguridad (BD + archivos PDF).

---

## 3. Cronograma estimado (referencial)

| Semana | Fase |
|---|---|
| 1 | 0 — Entorno + 1 — Base de datos |
| 2 | 1 — Base de datos (cierre) + 2 — Seguridad |
| 3 | 2 — Seguridad (cierre) + 3 — Gestión documental |
| 4 | 3 — Versiones + 4 — Tipos documentales |
| 5–6 | 5 — Flujo de revisión y publicación |
| 7 | 6 — Portal público |
| 8 | 7 — Notificaciones y auditoría |
| 9–10 | 8 — Pruebas, ajustes y documentación |

---

## 4. Puntos a validar con la Gaceta / vacíos detectados en el análisis

1. **Sustitución de un PDF ya publicado:** la sección 4.4.9 exige «autorización correspondiente», pero no hay transición desde `PUBLICADO`. Proponer: nueva versión → vuelve a `PENDIENTE_REVISION` (manteniendo visible la versión publicada anterior) o dejarlo fuera de la V1.
2. **Retiro de documentos publicados:** está diferido a versiones posteriores (sección 2.5). Confirmar.
3. **Bloqueo temporal de cuenta:** existe `intentos_fallidos`, pero falta un campo como `bloqueado_hasta` (o definir que el desbloqueo lo hace el administrador). Se recomienda agregarlo.
4. **Recuperación de contraseña:** no está definida. Opción simple: el administrador restablece la contraseña y se obliga a cambiarla en el siguiente inicio de sesión (campo `debe_cambiar_contrasena`).
5. **Tamaño máximo de PDF** y si los documentos escaneados necesitan OCR para la búsqueda por contenido (en la V1 la búsqueda es solo por metadatos).
6. **Filtro por instancia emisora:** el atributo existe para el filtrado, pero no figura en los filtros del portal (Figura 3). Se incluyó en la Fase 6.
7. **Carga de resoluciones históricas:** ¿habrá una migración masiva de resoluciones antiguas? Podría requerir una carga por lotes.
8. **Laravel y `usuario`:** el modelo usa `contrasena_hash` y `nombre_usuario`. Es compatible, pero exige configurar el modelo de autenticación (no se usa la tabla `users` por defecto).
