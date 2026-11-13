# Plan de Trabajo — Sistema Web Integral de Gestión de la Gaceta Universitaria (UPEA)

> Basado en: *Sistema Web Integral de Gestión de la Gaceta Universitaria de la UPEA* (análisis, 35 págs.) y *Normalización de la Base de Datos* (10 págs.).
> Alcance: **Versión 1 — Resoluciones del HCU**.
> Stack: Laravel 13 · PHP 8.3+ · PostgreSQL (Docker) · Eloquent · Blade + Bootstrap 5 · API REST con Sanctum · **Axios** · Git.

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

## 2. Decisiones técnicas

| Tema | Decisión | Motivo |
|---|---|---|
| Peticiones sin recargar la página | **Axios** (no `fetch`) en todo el sistema. | Ya viene configurado en Laravel (`resources/js/bootstrap.js`, `window.axios`), envía el token CSRF automáticamente, convierte JSON y maneja los errores de validación (422). |
| Formularios del panel | Formularios Blade tradicionales (envío y recarga). | Más simples de validar y auditar; Axios solo donde el análisis pide actualizar sin recargar. |
| Diseño visual | El diseño actual es **provisional y funcional**. El diseño definitivo se realiza en la **Fase 9**. | Primero se prioriza el funcionamiento; los estilos están centralizados para cambiarlos al final sin rehacer pantallas. |

---

## 3. Fases

### Fase 0 — Preparación del entorno ✅ COMPLETADA (12/11/2026)
- [x] Laravel 13 instalado en `C:\SigGacetaUniv`.
- [x] Repositorio Git (`main`) publicado en GitHub.
- [x] `docker-compose.yml`: PostgreSQL 17 (puerto **5434** en Windows; 5432 y 5433 ya estaban ocupados) y pgAdmin 4 (http://localhost:5050) con el servidor registrado previamente.
- [x] `.env` / `.env.example`: conexión `pgsql`, `APP_LOCALE=es`, `APP_FAKER_LOCALE=es_ES`; zona horaria `America/La_Paz` en `config/app.php`.
- [x] Sanctum instalado (`routes/api.php`, tabla `personal_access_tokens`).
- [x] Tailwind reemplazado por Bootstrap 5.3 (Vite); Axios disponible como `window.axios`.
- [x] Traducciones al español (`laravel-lang/common`, carpeta `lang/es`).

### Fase 1 — Base de datos (diseño físico) ✅ COMPLETADA (12/11/2026)
- [x] 13 migraciones (una por entidad) en orden de dependencias; la tabla `users` de Laravel se reemplazó por `usuario`.
- [x] Columnas `JSONB`: `tipo_documental.campos_adicionales`, `documento.metadatos`, `auditoria.detalle`.
- [x] Restricciones (4.4.15): unicidad tipo-número-gestión, `(id_documento, numero_version)` único, índice único parcial de versión vigente, `CHECK` de observación atendida, `CHECK` de número de versión y tamaño.
- [x] Triggers: `seguimiento_documento` y `auditoria` solo admiten inserciones; un estado con documentos no puede desactivarse.
- [x] Índice GIN de búsqueda de texto completo en español (número, título, descripción).
- [x] 11 modelos Eloquent con relaciones; `Usuario` como modelo de autenticación (`contrasena_hash`, `nombre_usuario`) y `tienePermiso()` por código.
- [x] Seeders: 3 roles, 12 permisos, 5 estados, tipo `RES_HCU`, administrador inicial (datos en `.env` → `config/gaceta.php`). `DemoSeeder` con usuarios y 20 resoluciones de prueba.
- [x] Pruebas sobre PostgreSQL (`gaceta_upea_test`): 12 pruebas del modelo de datos.
- [x] **Decisiones agregadas al modelo:** `usuario.bloqueado_hasta` (bloqueo automático de 15 min tras 5 intentos, el administrador puede desbloquear antes) y `usuario.debe_cambiar_contrasena` (el administrador restablece la contraseña; el usuario debe cambiarla al entrar).

### Fase 2 — Seguridad y administración (RF-01, RNF-01) ✅ COMPLETADA (12/11/2026)
- [x] Inicio de sesión con nombre de usuario o correo; sin registro público. Límite de 10 intentos por minuto por IP.
- [x] Bloqueo automático de 15 minutos tras 5 intentos fallidos (`bloqueado_hasta`); el administrador puede desbloquear antes. Se actualiza `ultimo_acceso`.
- [x] Cambio obligatorio de la contraseña temporal (`debe_cambiar_contrasena`) y expulsión de cuentas desactivadas (middleware `cuenta`).
- [x] Autorización por **código de permiso** con `Gate::before` (`@can('usuario.gestionar')`, `can:` en rutas).
- [x] Usuarios: listado con filtros, alta con contraseña temporal (generada o asignada), edición, roles, desactivar/activar, desbloquear y restablecer contraseña. Protecciones: no desactivarse a sí mismo y no dejar el sistema sin administradores.
- [x] Roles: alta, edición y asignación de permisos agrupados por módulo; el código del rol no cambia; el rol ADMIN no se desactiva y conserva sus permisos críticos.
- [x] Mi perfil: datos, permisos y cambio de contraseña (mín. 8 caracteres con letras y números).
- [x] Auditoría de inicio/cierre de sesión, intentos fallidos, bloqueos, altas, modificaciones (valores anteriores y nuevos), cambios de roles y permisos.
- [x] Layout del panel con Bootstrap 5 y menú según permisos (Figura 2); portal público provisional.
- [x] 24 pruebas nuevas (38 en total).

### Fase 3 — Gestión documental y versiones (RF-02, RF-04) ✅ COMPLETADA (12/11/2026)
- [x] Registro de documentos con su PDF (versión 1) en estado Borrador: tipo, número, gestión, fecha de emisión, título, descripción e instancia emisora.
- [x] PDF de **hasta 20 MB** (decisión 12/11/2026): validación de extensión, tipo MIME y contenido real (`%PDF-`); guardado en disco privado con nombre único (`documentos/{gestión}/{uuid}.pdf`), tamaño y huella SHA-256.
- [x] Nuevas versiones en una transacción: la anterior deja de ser vigente y se conserva; motivo obligatorio; se rechaza un archivo idéntico al vigente.
- [x] Edición de datos y carga de versiones solo en Borrador u Observado (`DocumentoPolicy`).
- [x] Listado con búsqueda por palabras clave y filtros (estado, gestión, tipo); ficha con visor PDF, historial de versiones, seguimiento y **verificación de integridad**.
- [x] PDF servido solo a usuarios autenticados con permiso (nunca por URL pública).
- [x] Registro en seguimiento (REGISTRO, NUEVA_VERSION) y en auditoría.
- [x] `DemoSeeder` con 20 resoluciones y sus PDF.
- [x] **Sin carga masiva de resoluciones históricas**: el sistema empieza desde ahora (decisión 12/11/2026).
- [x] 11 pruebas nuevas (49 en total).

### Fase 4 — Tipos documentales (RF-05) ✅ COMPLETADA (12/11/2026)
- [x] Administración de tipos documentales (permiso `tipo_documental.gestionar`): alta, edición y activación/desactivación. El código no cambia; los tipos no se eliminan.
- [x] Editor de **campos adicionales** por tipo (máx. 20): etiqueta, nombre interno (generado automáticamente), tipo de dato (texto, texto largo, número, fecha, lista de opciones, sí/no) y obligatorio. Se guardan en `campos_adicionales` (JSONB).
- [x] El formulario de documentos muestra los campos del tipo elegido, los valida (obligatorios, lista de opciones, número, fecha) y guarda solo los definidos en `documento.metadatos`, convertidos a su tipo de dato.
- [x] La ficha del documento muestra los campos adicionales con su etiqueta.
- [x] Los tipos inactivos no se ofrecen al registrar documentos.
- [x] Auditoría del alta y la modificación de tipos.
- [x] 7 pruebas nuevas (56 en total).

### Fase 5 — Flujo de revisión y publicación (RF-03, RF-07) ✅ COMPLETADA (12/11/2026)
- [x] Servicio `FlujoDocumental` con las **5 transiciones** de la sección 4.4.10; cualquier otra se rechaza. Cada transición bloquea el documento, verifica su estado, registra seguimiento y auditoría en una transacción.
- [x] Encargado: enviar a revisión; atender **todas** las observaciones (respuesta obligatoria) y reenviar.
- [x] Revisor / Publicador: bandeja con pestañas (pendientes, aprobados por publicar, devueltos); devolver con una o varias observaciones sobre la versión vigente; aprobar; publicar (registra `fecha_publicacion`).
- [x] **El mismo Revisor / Publicador aprueba y publica** (decisión 12/11/2026).
- [x] **Nadie revisa, aprueba ni publica un documento que registró** (decisión 12/11/2026), aunque el administrador le asigne ambos roles.
- [x] Ficha del documento con panel de acciones según el rol y el estado, historial de observaciones con sus respuestas y seguimiento completo (sección 2.4).
- [x] Evento `DocumentoCambioDeEstado` emitido tras cada transición (lo usarán las notificaciones de la Fase 7).
- [x] `DemoSeeder` recorre el flujo real (con devoluciones, correcciones, aprobaciones y publicaciones).
- [x] 9 pruebas nuevas (65 en total).

### Fase 6 — Portal público (RF-06) ✅ COMPLETADA (12/11/2026)
- [x] Portal (Figura 3): encabezado institucional, buscador (número, título o palabras clave), filtros (tipo, gestión, número, rango de fecha de emisión, instancia emisora, orden), listado paginado y pie de página.
- [x] Solo documentos **publicados y activos**; los demás devuelven 404 (ficha y PDF).
- [x] Ficha pública con visor PDF, descarga, datos (incluye campos adicionales del tipo) y huella SHA-256 para verificar copias.
- [x] Se entrega siempre la **versión vigente** del PDF, servida por controlador (nunca por URL directa).
- [x] API pública `GET /api/documentos` (JSON, paginada), consumida con **Axios** para filtrar sin recargar; actualiza la URL para compartir la búsqueda. Sin datos internos.
- [x] Funciona sin JavaScript (formulario y paginación normales).
- [x] Límites por IP: 60 búsquedas/min (API) y 30 descargas/min.
- [x] Botón «Ver en el portal» en la ficha del panel para documentos publicados.
- [x] 7 pruebas nuevas (72 en total).

### Fase 7 — Notificaciones, auditoría y tablero (RF-08, RF-09) ✅ COMPLETADA (12/11/2026)
- [x] Notificaciones automáticas en cada paso del flujo (oyente del evento `DocumentoCambioDeEstado`): `NUEVO_PENDIENTE` → revisores activos (excepto el registrador); `OBSERVADO`, `APROBADO` y `PUBLICADO` → quien registró el documento. Nunca se notifica a quien realizó la acción.
- [x] Campana en la barra superior con contador y avisos recientes, actualizada con **Axios** cada minuto y al volver a la pestaña; «Marcar todas como leídas»; al abrir un aviso se marca como leído y lleva al documento.
- [x] API de notificaciones protegida con **Sanctum** (sesión del panel, `statefulApi`): `GET /api/notificaciones/resumen`, `PATCH /api/notificaciones/{id}/leer`, `POST /api/notificaciones/leer-todas`. Cada usuario solo ve las suyas.
- [x] Página «Notificaciones» (todas / sin leer) con contador en el menú.
- [x] Consulta de auditoría (permiso `auditoria.ver`) con filtros por usuario, acción, entidad, ID y fechas; detalle con valores anteriores y nuevos; exportación a CSV (Excel).
- [x] Tablero de inicio: pendientes según el rol (por revisar, por publicar, observados, borradores, cuentas bloqueadas), documentos por estado, notificaciones sin leer, documentos recientes y actividad propia.
- [x] `APP_URL=http://localhost:8000` (necesario para que Sanctum reconozca la sesión en `localhost:8000`).
- [x] 8 pruebas nuevas (80 en total).

### Fase 8 — Pruebas, ajustes y documentación ✅ COMPLETADA (13/11/2026)
- [x] **Seguridad:** cabeceras en todas las respuestas (`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`; HSTS en producción con HTTPS).
- [x] **Páginas de error en español** (403, 404, 419, 429, 500, 503); el mensaje genérico de Laravel se traduce.
- [x] **Rendimiento (RNF-02):** prueba con 20.000 documentos. Índices de trigramas (`pg_trgm`) para la búsqueda por fragmentos: peor caso de ~90 ms a ~8 ms.
- [x] **Respaldos:** comando `php artisan gaceta:respaldo` (base de datos con `pg_dump` + ZIP de los PDF + resumen con huellas SHA-256), retención de 30 días y ejecución diaria a las 02:00. Probado y verificado con `pg_restore`.
- [x] **Producción:** configuración recomendada en `.env.example` y guía completa (Nginx, PHP-FPM, PostgreSQL, HTTPS, tareas programadas, restauración, actualización).
- [x] **Manuales** en Markdown (`docs/manuales/`) y en Word (`docs/manuales/word/`): manual de usuario por rol, manual técnico y guía de instalación y despliegue. Herramientas para regenerar los Word en `docs/manuales/herramientas/`.
- [x] `README.md` propio del sistema.
- [x] 4 pruebas nuevas (84 en total).
- [x] Base de desarrollo recreada con datos de demostración (13/11/2026; respaldo previo en `storage/app/respaldos/`).
- [x] **Revisión de brechas** (13/11/2026): barrido de 1.740 peticiones sin errores y 18 pruebas exploratorias. **10 brechas corregidas**, con 12 pruebas de regresión (96 en total):
  1. No se envía ni aprueba un documento sin los campos obligatorios de su tipo.
  2. Anulación de borradores registrados por error (el número queda disponible).
  3. Un borrador cuyo tipo se desactivó se puede seguir editando.
  4. El nombre de usuario no distingue mayúsculas.
  5. El bloqueo por intentos no revela si una cuenta existe.
  6. Cambiar o restablecer la contraseña cierra las demás sesiones (`auth.session`).
  7. Política de seguridad de contenido (CSP).
  8. El año de la fecha de emisión debe ser el de la gestión.
  9. El año del número (`…/AAAA`) debe coincidir con la gestión.
  10. Doble clic: botones desactivados al enviar y aviso amable si el estado ya cambió.
- [ ] Decisiones pendientes con la Gaceta: (11) ¿solo quien registró edita sus borradores?, (12) ¿el Revisor ve los borradores?, (13) retiro o sustitución de documentos publicados, (14) ¿el Revisor puede corregir una observación antes de que se atienda?
- [ ] Pruebas con usuarios de la Gaceta (aceptación).
- [ ] Definir con la unidad responsable el servidor de producción.

### Fase 9 — Diseño visual definitivo (RNF-04)
Se realiza al final, cuando todas las funciones estén terminadas.
- [ ] Obtener de la Gaceta / UPEA: logo oficial (SVG o PNG transparente), colores institucionales y, si existe, manual de identidad visual.
- [ ] Definir la paleta y tipografía definitivas en `resources/css/app.css` (variables `--gaceta-*`).
- [ ] Rediseñar las plantillas comunes: `resources/views/layouts/base.blade.php` y `panel.blade.php` (menú, barra superior, pie).
- [ ] Diseño definitivo del portal público y de la pantalla de inicio de sesión.
- [ ] Revisión de accesibilidad (contraste, navegación con teclado) y de pantallas pequeñas (celular).
- [ ] Validación del diseño con el personal de la Gaceta.

**Entregable:** interfaz con la identidad institucional de la UPEA, aprobada por la Gaceta.

---

## 4. Cronograma estimado (referencial)

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
| 11 | 9 — Diseño visual definitivo |

---

## 5. Puntos a validar con la Gaceta / vacíos detectados en el análisis

1. **Sustitución de un PDF ya publicado:** la sección 4.4.9 exige «autorización correspondiente», pero no hay transición desde `PUBLICADO`. Proponer: nueva versión → vuelve a `PENDIENTE_REVISION` (manteniendo visible la versión publicada anterior) o dejarlo fuera de la V1.
2. **Retiro de documentos publicados:** está diferido a versiones posteriores (sección 2.5). Confirmar.
3. ~~**Bloqueo temporal de cuenta**~~ ✅ Resuelto: campo `bloqueado_hasta` (automático + desbloqueo por el administrador).
4. ~~**Recuperación de contraseña**~~ ✅ Resuelto: la restablece el administrador y el usuario debe cambiarla (`debe_cambiar_contrasena`).
5. ~~**Tamaño máximo de PDF**~~ ✅ Resuelto: 20 MB. Pendiente: si los documentos escaneados necesitan OCR (en la V1 la búsqueda es solo por metadatos).
6. **Filtro por instancia emisora:** el atributo existe para el filtrado, pero no figura en los filtros del portal (Figura 3). Se incluyó en la Fase 6.
7. ~~**Carga de resoluciones históricas**~~ ✅ Resuelto: no se requiere; el sistema empieza desde ahora.
8. ~~**Laravel y `usuario`**~~ ✅ Resuelto en la Fase 1 (`config/auth.php` usa `App\Models\Usuario`).
