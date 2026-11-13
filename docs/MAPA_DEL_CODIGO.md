# Mapa del código — Sistema de la Gaceta Universitaria (UPEA)

Guía para ubicar rápidamente dónde se modifica cada función del sistema.
Las rutas parten de la carpeta del proyecto (`C:\SigGacetaUniv`). El número después de `:` indica la línea del archivo.

> Se actualiza al cerrar cada fase del [Plan de trabajo](PLAN_DE_TRABAJO.md).

---

## Fase 0 — Entorno

| Función | Detalle | Archivo(s) |
|---|---|---|
| **Base de datos en Docker** | PostgreSQL 17 (puerto 5434 en Windows) y pgAdmin 4 (http://localhost:5050). | `docker-compose.yml`<br>Servidor registrado en pgAdmin: `docker/pgadmin/servers.json`<br>Creación de la base de pruebas: `docker/postgres/init/01-crear-bd-pruebas.sh` |
| **Configuración general** | Conexión, idioma, contraseñas locales (no se sube a Git). | `.env` (plantilla para otros equipos: `.env.example`) |
| **Zona horaria** | `America/La_Paz`. | `config/app.php` (clave `timezone`) |
| **Configuración propia del sistema** | Datos del administrador inicial. | `config/gaceta.php` |
| **Estilos y scripts** | Bootstrap 5, Bootstrap Icons y estilos propios. | Entrada: `resources/js/app.js`<br>Estilos propios: `resources/css/app.css`<br>Compilación: `vite.config.js` |
| **Textos en español** | Mensajes de validación y del sistema. | `lang/es/` y `lang/es.json` |
| **Peticiones sin recargar (Axios)** | Se usa **Axios, no `fetch`**. Está disponible como `window.axios` y envía el token CSRF automáticamente. Se usará en la búsqueda del portal (Fase 6) y en las notificaciones (Fase 7). | Configuración: `resources/js/bootstrap.js` |

---

## Fase 1 — Base de datos

| Función | Detalle | Archivo(s) |
|---|---|---|
| **Tablas (13 entidades)** | Una migración por tabla, en orden de dependencias. | `database/migrations/2026_11_12_2000NN_create_<tabla>_table.php` (NN = 01 a 13) |
| **Reglas de integridad (4.4.15)** | Documento único, una sola versión vigente, observación atendida completa. | `..._200008_create_documento_table.php`, `..._200009_create_version_documento_table.php`, `..._200011_create_observacion_table.php` |
| **Tablas de solo inserción** | Seguimiento y auditoría no se modifican ni eliminan (triggers). | `..._200010_create_seguimiento_documento_table.php` (función `impedir_modificacion`) y `..._200013_create_auditoria_table.php` |
| **Estado no desactivable con documentos** | Trigger en la tabla de estados. | `..._200007_create_estado_documento_table.php` |
| **Búsqueda por palabras clave** | Índice de texto completo en español (número, título, descripción). | Índice: `..._200008_create_documento_table.php`<br>Consulta: `app/Models/Documento.php` (`scopeBuscar`) |
| **Modelos (Eloquent)** | Un modelo por entidad, con sus relaciones. | `app/Models/` (`Usuario`, `Rol`, `Permiso`, `TipoDocumental`, `EstadoDocumento`, `Documento`, `VersionDocumento`, `SeguimientoDocumento`, `Observacion`, `Notificacion`, `Auditoria`) |
| **Códigos de estados, roles y tipos** | Constantes usadas por el programa (`BORRADOR`, `ADMIN`, `RES_HCU`, …). | `app/Models/EstadoDocumento.php`, `app/Models/Rol.php`, `app/Models/TipoDocumental.php` |
| **Datos iniciales** | Roles, permisos, estados, tipo «Resolución del HCU» y administrador. | `database/seeders/DatabaseSeeder.php` (llama a los demás)<br>`RolesYPermisosSeeder.php`, `EstadoDocumentoSeeder.php`, `TipoDocumentalSeeder.php`, `AdministradorSeeder.php` |
| **Datos de demostración** | Usuarios `encargado` y `revisor` + 20 resoluciones (solo desarrollo). | `database/seeders/DemoSeeder.php`<br>Generadores: `database/factories/UsuarioFactory.php`, `DocumentoFactory.php` |
| **Pruebas del modelo de datos** | 12 pruebas de las reglas de la base de datos. | `tests/Feature/Database/ModeloDatosTest.php`<br>Base de pruebas: `phpunit.xml` (`gaceta_upea_test`) |

---

## Fase 2 — Seguridad y administración

| Función | Detalle | Archivo(s) |
|---|---|---|
| **Inicio de sesión** | Con nombre de usuario o correo. No hay registro público. | **Lógica:** `app/Http/Controllers/Auth/LoginController.php` (método `store`, línea 34)<br>**Pantalla:** `resources/views/auth/login.blade.php`<br>**Rutas:** `routes/web.php` |
| **Bloqueo** | Tras 5 intentos fallidos la cuenta se bloquea 15 minutos y se libera sola; el administrador puede desbloquearla antes. Cada IP tiene un límite de 10 intentos por minuto. | **Cantidad de intentos y minutos:** `app/Models/Usuario.php:23` (`MAX_INTENTOS_FALLIDOS`) y `:25` (`MINUTOS_BLOQUEO`)<br>**Límite por IP:** `app/Providers/AppServiceProvider.php:29`<br>**Lógica del bloqueo:** `LoginController.php`, método `sumarIntentoFallido` |
| **Contraseña temporal** | Mientras el usuario no la cambie, no puede usar el panel. Si un usuario es desactivado con la sesión abierta, el sistema lo saca. | **Control:** `app/Http/Middleware/VerificarCuenta.php` (las páginas permitidas están en la línea 18)<br>**Registro del middleware:** `bootstrap/app.php`<br>**Pantalla:** `resources/views/panel/perfil/contrasena.blade.php` |
| **Permisos** | Se verifican por código (`usuario.gestionar`, `rol.gestionar`, …). Si se crea un rol nuevo, funciona sin tocar el programa. | **Verificación:** `app/Providers/AppServiceProvider.php:37` (`Gate::before`) y `app/Models/Usuario.php` (`tienePermiso`)<br>**Catálogo de permisos y roles iniciales:** `database/seeders/RolesYPermisosSeeder.php:17` y `:33`<br>**Qué permiso exige cada página:** `routes/web.php` (`can:...`) |
| **Usuarios** | Listado con filtros, crear, editar, asignar roles, desactivar/activar, desbloquear y restablecer contraseña. La contraseña temporal se muestra una sola vez. | **Lógica:** `app/Http/Controllers/Panel/UsuarioController.php`<br>**Validaciones:** `app/Http/Requests/GuardarUsuarioRequest.php`<br>**Pantallas:** `resources/views/panel/usuarios/` (`index`, `create`, `edit`, `_form`)<br>**Aviso de contraseña temporal:** `resources/views/partials/alertas.blade.php` |
| **Protecciones** | El administrador no puede desactivarse a sí mismo, y el sistema no permite quedarse sin administradores. El rol ADMIN no se puede desactivar ni perder sus permisos de gestión. | **Usuarios:** `UsuarioController.php:134` (`desactivar`) y `:202` (`hayOtroAdministrador`)<br>**Rol ADMIN:** `app/Http/Controllers/Panel/RolController.php:24` (`PERMISOS_PROTEGIDOS_ADMIN`) y método `update` |
| **Roles** | Crear y editar roles, con los permisos agrupados por módulo. El código del rol no cambia una vez creado. | **Lógica:** `app/Http/Controllers/Panel/RolController.php`<br>**Validaciones:** `app/Http/Requests/GuardarRolRequest.php`<br>**Pantallas:** `resources/views/panel/roles/` |
| **Mi perfil** | Datos de la cuenta, permisos y cambio de contraseña (mínimo 8 caracteres, con letras y números). | **Lógica:** `app/Http/Controllers/Panel/PerfilController.php`<br>**Reglas de la contraseña:** `app/Providers/AppServiceProvider.php:26`<br>**Pantallas:** `resources/views/panel/perfil/` (`show`, `contrasena`) |
| **Auditoría** | Se registran inicios y cierres de sesión, intentos fallidos, bloqueos, altas, modificaciones (valores anteriores y nuevos) y cambios de roles y permisos. Las contraseñas nunca se guardan. | **Cómo se registra:** `app/Support/Auditor.php` (los campos que nunca se guardan están en la línea 16)<br>**Nombres de las acciones en pantalla:** `app/Models/Auditoria.php:66` (`ETIQUETAS`) |
| **Diseño** | Panel con menú lateral según la Figura 2, desplegable en el celular. Las secciones de fases futuras aparecen como «Próximamente». | **Opciones del menú:** `resources/views/layouts/panel.blade.php:9`<br>**Colores:** `resources/css/app.css:5` (`--gaceta-primario`, `--gaceta-acento`)<br>**Estructura común:** `resources/views/layouts/base.blade.php`<br>**Inicio del panel:** `resources/views/panel/inicio.blade.php` + `app/Http/Controllers/Panel/InicioController.php` |
| **Portal público (provisional)** | Portada hasta la Fase 6. | `resources/views/portal/inicio.blade.php` |
| **Pruebas** | Inicio de sesión, usuarios, roles y perfil. | `tests/Feature/Auth/InicioSesionTest.php`, `tests/Feature/Panel/UsuarioAdministracionTest.php`, `RolAdministracionTest.php`, `PerfilTest.php` |

---

## Fase 3 — Gestión documental y versiones

| Función | Detalle | Archivo(s) |
|---|---|---|
| **Lógica central** | Registrar, modificar, agregar versiones y verificar integridad. Todo pasa por este servicio. | `app/Services/GestionDocumental.php` |
| **Registro de documentos** | Con su PDF (versión 1), en estado Borrador. | **Lógica:** `GestionDocumental::registrar`<br>**Controlador:** `app/Http/Controllers/Panel/DocumentoController.php` (`create`, `store`)<br>**Pantallas:** `resources/views/panel/documentos/create.blade.php` y `_form.blade.php` |
| **Validaciones del documento** | Campos obligatorios, formato del número, número único por gestión, tipo de archivo. | `app/Http/Requests/GuardarDocumentoRequest.php` |
| **Tamaño máximo del PDF** | 20 MB (también debe permitirlo PHP). | `.env` (`GACETA_PDF_MAX_MB`) → `config/gaceta.php` (`documentos`)<br>Límite real considerando PHP: `app/Support/LimitesArchivo.php` |
| **Dónde se guardan los PDF** | `storage/app/private/documentos/{gestión}/{uuid}.pdf` (no accesible desde la web). | Carpeta y disco: `config/gaceta.php`<br>Guardado y control del contenido `%PDF-`: `GestionDocumental::guardarArchivo` |
| **Nuevas versiones** | La anterior deja de ser vigente y se conserva; motivo obligatorio; no se acepta un archivo idéntico. | **Lógica:** `GestionDocumental::agregarVersion`<br>**Validación:** `app/Http/Requests/NuevaVersionRequest.php`<br>**Controlador:** `app/Http/Controllers/Panel/VersionDocumentoController.php` (`store`) |
| **Quién puede editar y cuándo** | Permiso `documento.editar` y solo en Borrador u Observado. | `app/Policies/DocumentoPolicy.php`<br>Estados editables: `app/Models/Documento.php` (`ESTADOS_EDITABLES`) |
| **Ver y descargar el PDF** | Solo usuarios con sesión y permiso `documento.ver`. | `VersionDocumentoController.php` (`archivo`; nombre del archivo descargado) |
| **Verificar integridad** | Compara la huella SHA-256 actual con la registrada. | `GestionDocumental::verificarIntegridad` y `VersionDocumentoController.php` (`verificar`) |
| **Listado y filtros** | Búsqueda por palabras clave, estado, gestión y tipo. | `DocumentoController.php` (`index`)<br>Pantalla: `resources/views/panel/documentos/index.blade.php` |
| **Ficha del documento** | Visor PDF, datos, versiones y seguimiento. | `resources/views/panel/documentos/show.blade.php` |
| **Colores de los estados** | Insignia de color por estado. | `resources/views/panel/documentos/_estado.blade.php` |
| **Nombres de las acciones del seguimiento** | «Registro del documento», «Nueva versión del PDF», … | `app/Models/SeguimientoDocumento.php` (`ETIQUETAS`) |
| **Mensaje cuando el archivo supera el límite de PHP** | Error amigable en lugar de la página de error. | `bootstrap/app.php` (`PostTooLargeException`) |
| **Rutas** | `/panel/documentos/...` | `routes/web.php` (sección «Gestión documental») |
| **Datos de demostración** | 20 resoluciones con PDF generados. | `database/seeders/DemoSeeder.php`<br>Generador de PDF simple: `app/Support/PdfSimple.php` |
| **Pruebas** | Registro, validaciones, versiones, edición por estado, archivos, integridad y búsqueda. | `tests/Feature/Panel/GestionDocumentalTest.php` |

---

## Fase 4 — Tipos documentales

| Función | Detalle | Archivo(s) |
|---|---|---|
| **Administración de tipos** | Crear, editar y activar/desactivar tipos documentales. El código no cambia y los tipos no se eliminan. | **Controlador:** `app/Http/Controllers/Panel/TipoDocumentalController.php` (`index` :20, `store` :32, `update` :52)<br>**Pantallas:** `resources/views/panel/tipos/` (`index`, `create`, `edit`, `_form`)<br>**Rutas:** `routes/web.php` (sección «Tipos documentales», `/panel/tipos-documentales`) |
| **Validación del tipo y sus campos** | Código único en mayúsculas, nombre único, campos con nombre interno válido y sin repetir, listas con al menos dos opciones, máximo 20 campos. | `app/Http/Requests/GuardarTipoDocumentalRequest.php` (máximo de campos :16, normalización :23, regla de las listas :82, datos a guardar :116) |
| **Tipos de dato disponibles** | Texto corto, texto largo, número, fecha, lista de opciones y sí/no. | `app/Models/TipoDocumental.php:23` (`TIPOS_CAMPO`) |
| **Editor de campos (agregar/quitar filas)** | Nombre interno generado desde la etiqueta («N.º de sesión» → `no_de_sesion`); las opciones se muestran solo para listas. | **Fila de un campo:** `resources/views/panel/tipos/_campo.blade.php`<br>**JavaScript:** `resources/js/campos-adicionales.js` (`nombreInterno` :12, editor :23) |
| **Campos en el formulario de documentos** | Se muestran solo los campos del tipo elegido. | **Vista:** `resources/views/panel/documentos/_metadatos.blade.php`<br>**JavaScript que alterna los campos:** `resources/js/campos-adicionales.js:79` |
| **Validación y guardado de los valores** | Obligatorios, opciones válidas, número y fecha; se guardan solo los campos definidos, convertidos a su tipo. | **Reglas:** `TipoDocumental.php:67` (`reglasMetadatos`)<br>**Conversión:** `TipoDocumental.php:97` (`normalizarMetadatos`)<br>**Uso en el formulario:** `app/Http/Requests/GuardarDocumentoRequest.php:89` (`datosDocumento`) |
| **Visualización en la ficha** | Etiqueta y valor con formato (Sí/No, fecha dd/mm/aaaa). | **Formato:** `TipoDocumental.php:121` (`formatearValor`)<br>**Vista:** `resources/views/panel/documentos/show.blade.php` (sección «Datos del documento») |
| **Pruebas** | Permisos, alta con campos, validaciones, edición, uso en documentos, tipos inactivos. | `tests/Feature/Panel/TipoDocumentalTest.php` |

---

## Fase 5 — Flujo de revisión y publicación

| Función | Detalle | Archivo(s) |
|---|---|---|
| **Transiciones permitidas** | Borrador → Pendiente → (Observado ↔ Pendiente) → Aprobado → Publicado. Cualquier otra se rechaza. | `app/Services/FlujoDocumental.php:35` (`TRANSICIONES`) |
| **Lógica común de cada transición** | Bloquea el documento, verifica el estado, cambia el estado, registra seguimiento y auditoría, emite el evento. | `FlujoDocumental.php:148` (`ejecutar`) |
| **Enviar a revisión** (Encargado) | Borrador → Pendiente de revisión, con comentario opcional. | **Lógica:** `FlujoDocumental.php:43`<br>**Controlador:** `app/Http/Controllers/Panel/FlujoDocumentalController.php:23`<br>**Permiso y estado:** `app/Policies/DocumentoPolicy.php:31` |
| **Devolver con observaciones** (Revisor) | Pendiente → Observado. Una o varias observaciones sobre la versión vigente. | **Lógica:** `FlujoDocumental.php:53`<br>**Controlador:** `FlujoDocumentalController.php:49`<br>**Permiso:** `DocumentoPolicy.php:41`<br>**Botón «Agregar otra observación»:** `resources/js/flujo-documental.js` |
| **Atender observaciones y reenviar** (Encargado) | Observado → Pendiente. Respuesta obligatoria para cada observación pendiente. | **Lógica:** `FlujoDocumental.php:86`<br>**Controlador:** `FlujoDocumentalController.php:34`<br>**Permiso:** `DocumentoPolicy.php:36` |
| **Aprobar** (Revisor) | Pendiente → Aprobado. No se permite con observaciones pendientes. | **Lógica:** `FlujoDocumental.php:118`<br>**Controlador:** `FlujoDocumentalController.php:64`<br>**Permiso:** `DocumentoPolicy.php:46` |
| **Publicar** (Revisor / Publicador) | Aprobado → Publicado. Registra la fecha de publicación; el documento queda visible en el portal. | **Lógica:** `FlujoDocumental.php:133`<br>**Controlador:** `FlujoDocumentalController.php:76`<br>**Permiso:** `DocumentoPolicy.php:51` |
| **Revisor distinto del registrador** | Quien registró el documento no puede revisarlo, aprobarlo ni publicarlo. | **Pantallas y rutas:** `DocumentoPolicy.php:79`<br>**Servicio (doble control):** `FlujoDocumental.php:170` |
| **Bandeja del revisor** | Pestañas: pendientes, aprobados por publicar, devueltos. Excluye los documentos propios. | **Controlador:** `app/Http/Controllers/Panel/RevisionController.php` (pestañas :17, consulta :23)<br>**Pantalla:** `resources/views/panel/revision/index.blade.php` |
| **Panel de acciones en la ficha** | Muestra solo las acciones permitidas al usuario según su rol y el estado. | `resources/views/panel/documentos/_flujo.blade.php` |
| **Historial de observaciones** | Observaciones por versión, con su respuesta y quién la atendió. | `resources/views/panel/documentos/_observaciones.blade.php` |
| **Mensaje de acción no permitida** | Si el estado cambió mientras tanto (dos usuarios a la vez). | `app/Exceptions/TransicionNoPermitida.php` |
| **Evento para notificaciones** | Se emite tras cada transición; lo usará la Fase 7. | `app/Events/DocumentoCambioDeEstado.php` |
| **Rutas** | `/panel/documentos/{id}/enviar-revision`, `/reenviar`, `/devolver`, `/aprobar`, `/publicar` y `/panel/revision`. | `routes/web.php` (secciones «Flujo de revisión» y «Bandeja») |
| **Pruebas** | Recorrido completo, transiciones no permitidas, roles, revisor distinto, observaciones, bandeja, concurrencia y datos de demostración. | `tests/Feature/Panel/FlujoDocumentalTest.php` |

---

## Fase 6 — Portal público

| Función | Detalle | Archivo(s) |
|---|---|---|
| **Búsqueda (lógica compartida)** | La misma consulta para la página y la API; solo publicados y activos. | `app/Services/ConsultaPublica.php` (limpieza de filtros :34, búsqueda :60, opciones de filtros :83) |
| **Resultados por página y órdenes** | 10 por página; más recientes, más antiguos, número. | `ConsultaPublica.php:20` (`POR_PAGINA`) y `:22` (`ORDENES`) |
| **Página del portal** | Buscador, filtros, resultados y paginación (Figura 3). | **Controlador:** `app/Http/Controllers/Portal/PortalController.php:24` (`index`)<br>**Vista:** `resources/views/portal/inicio.blade.php`<br>**Encabezado y pie:** `resources/views/layouts/portal.blade.php` |
| **Diseño de cada resultado** | Una sola plantilla para el servidor y para Axios. | `resources/views/portal/_resultado.blade.php` |
| **Búsqueda sin recargar (Axios)** | Al cambiar filtros o escribir (espera 400 ms), consulta la API y redibuja; actualiza la URL. | `resources/js/portal.js` (espera :12, dibujo :39, búsqueda :69, llamada Axios :81) |
| **API pública (JSON)** | `GET /api/documentos?q=&tipo=&gestion=&numero=&desde=&hasta=&instancia=&orden=&page=` | **Controlador:** `app/Http/Controllers/Api/DocumentoPublicoController.php:21`<br>**Ruta:** `routes/api.php` |
| **Datos públicos de un documento** | Qué campos se muestran/exponen (sin datos internos). | `app/Http/Resources/DocumentoPublicoResource.php` |
| **Ficha pública** | PDF, datos, campos adicionales y huella SHA-256. | **Controlador:** `PortalController.php:39` (`show`)<br>**Vista:** `resources/views/portal/documento.blade.php` |
| **PDF público** | Versión vigente, en el navegador o descarga. | `PortalController.php:48` (`pdf`); regla «solo publicados»: `:67` |
| **Límites de uso** | 60 búsquedas por minuto (API) y 30 descargas por minuto, por IP. | `app/Providers/AppServiceProvider.php:32` y `:33` |
| **Rutas** | `/`, `/documentos/{id}`, `/documentos/{id}/pdf`, `/api/documentos`. | `routes/web.php` (sección «Portal público») y `routes/api.php` |
| **Pruebas** | Visibilidad, ficha y PDF, versión vigente, filtros, API, límite y campos adicionales. | `tests/Feature/Portal/PortalPublicoTest.php` |

---

## Fase 7 — Notificaciones, auditoría y tablero

| Función | Detalle | Archivo(s) |
|---|---|---|
| **Quién recibe cada aviso** | Pendiente → revisores activos (excepto el registrador); Observado / Aprobado / Publicado → quien registró. Nunca a quien actuó. | `app/Listeners/NotificarCambioDeEstado.php` (`handle` :25; Pendiente :32, Observado :38, Aprobado :44, Publicado :50) |
| **Textos de los avisos** | Título y mensaje de cada tipo de notificación. | `NotificarCambioDeEstado.php` (dentro de cada caso del `match`) |
| **Usuarios con un permiso** | Consulta usada para elegir destinatarios. | `app/Models/Usuario.php:134` (`scopeConPermiso`) |
| **Íconos por tipo** | Ícono de cada aviso en la campana. | `app/Models/Notificacion.php:61` (`icono`) |
| **Campana (Axios)** | Contador y avisos recientes; se actualiza cada minuto y al volver a la pestaña. | **Vista:** `resources/views/layouts/panel.blade.php` (bloque «Campana de notificaciones»)<br>**Cada aviso:** `resources/views/panel/notificaciones/_item.blade.php`<br>**JavaScript:** `resources/js/notificaciones.js` (intervalo :11, actualización :47) |
| **API de notificaciones (Sanctum)** | Resumen, marcar una y marcar todas como leídas. | **Controlador:** `app/Http/Controllers/Api/NotificacionController.php` (cantidad de recientes :18, resumen :21, leer :34, leer todas :44)<br>**Rutas:** `routes/api.php`<br>**Sesión del panel en la API:** `bootstrap/app.php:25` (`statefulApi`) y `APP_URL` en `.env` |
| **Página de notificaciones** | Todas / sin leer; abrir marca como leída y lleva al documento. | **Controlador:** `app/Http/Controllers/Panel/NotificacionController.php` (lista :14, abrir :34, leer todas :45)<br>**Vista:** `resources/views/panel/notificaciones/index.blade.php` |
| **Consulta de auditoría** | Filtros por usuario, acción, entidad, ID y fechas; detalle desplegable. | **Controlador:** `app/Http/Controllers/Panel/AuditoriaController.php:19`<br>**Vista:** `resources/views/panel/auditoria/index.blade.php` |
| **Exportar auditoría** | CSV con separador «;» y tildes compatibles con Excel. | `AuditoriaController.php:34` (`exportar`) |
| **Tablero de inicio** | Pendientes según el rol, documentos por estado, notificaciones, recientes y actividad. | **Controlador:** `app/Http/Controllers/Panel/InicioController.php:19`<br>**Vista:** `resources/views/panel/inicio.blade.php` |
| **Pruebas** | Destinatarios, API de la campana, marcar como leídas, auditoría, CSV y tablero. | `tests/Feature/Panel/NotificacionesAuditoriaTest.php` |

---

## Cambios frecuentes

| Si se solicita… | Cambiar esto |
|---|---|
| Otro número de intentos o de minutos de bloqueo | `app/Models/Usuario.php`, líneas 23 y 25 |
| Contraseñas más exigentes (por ejemplo, con símbolos) | `app/Providers/AppServiceProvider.php:26` → agregar `->symbols()` |
| Otro color institucional | `resources/css/app.css`, líneas 5 a 7 |
| Agregar o renombrar una opción del menú | `resources/views/layouts/panel.blade.php`, arreglo `$menu` |
| Agregar un permiso nuevo | `database/seeders/RolesYPermisosSeeder.php` y luego `php artisan db:seed --class=RolesYPermisosSeeder` |
| Cambiar un texto de error del inicio de sesión | `app/Http/Controllers/Auth/LoginController.php` (constante `MENSAJE_CREDENCIALES` y método `mensajeBloqueo`) |
| Cambiar el nombre visible de un estado | `database/seeders/EstadoDocumentoSeeder.php` (el código no se cambia) |
| Otro tamaño máximo de PDF | `.env` → `GACETA_PDF_MAX_MB` (y ajustar `upload_max_filesize` / `post_max_size` en `php.ini`) |
| Otro valor por defecto de «Instancia emisora» | `config/gaceta.php` → `instancia_emisora_predeterminada` |
| Permitir editar en otros estados | `app/Models/Documento.php` → `ESTADOS_EDITABLES` |
| Agregar un nuevo tipo de dato para los campos (ej.: correo) | `TipoDocumental.php` → `TIPOS_CAMPO`, `reglasMetadatos`, `normalizarMetadatos`, `formatearValor` + `_metadatos.blade.php` (campo del formulario) |
| Cambiar el máximo de campos por tipo | `GuardarTipoDocumentalRequest.php` → `MAX_CAMPOS` |
| Permitir que quien registra también apruebe | Quitar `revisorDistinto: true` en `FlujoDocumental.php` y en `DocumentoPolicy.php` |
| Cambiar los textos automáticos del seguimiento | `FlujoDocumental.php` (segundo argumento de `unir(...)` en cada acción) |
| Agregar una pestaña a la bandeja | `RevisionController.php:17` (`BANDEJAS`) |
| Mostrar más resultados por página | `ConsultaPublica.php:20` (`POR_PAGINA`) |
| Cambiar los límites de búsquedas o descargas | `AppServiceProvider.php:32` y `:33` |
| Mostrar u ocultar un dato en los resultados del portal | `DocumentoPublicoResource.php` + `portal/_resultado.blade.php` (atributo `data-campo`) |
| Cambiar el tiempo de espera al escribir en el buscador | `resources/js/portal.js:12` |
| Notificar a otras personas en un paso del flujo | `NotificarCambioDeEstado.php` (lista de destinatarios del caso correspondiente) |
| Cambiar cada cuánto se actualiza la campana | `resources/js/notificaciones.js:11` (`INTERVALO_MS`) |
| Mostrar más avisos en la campana | `Api/NotificacionController.php:18` (`RECIENTES`) |
| Agregar un pendiente al tablero | `InicioController.php` (agregar un `$pendientes->push([...])`) |
| Acceder por otra dirección (IP de la red) y que funcione la campana | `.env` → `SANCTUM_STATEFUL_DOMAINS` (agregar la dirección con su puerto) |
| Cambiar los datos del administrador inicial | `.env` (`ADMIN_USUARIO`, `ADMIN_CORREO`, `ADMIN_CONTRASENA`) |

---

## Después de modificar

| Tipo de archivo | Qué hacer |
|---|---|
| `.php` y vistas `.blade.php` | Recargar la página. |
| `.css` o `.js` | `npm run build` y recargar con **Ctrl + F5**. |
| `.env` o `config/*.php` | `php artisan config:clear`. |
| Migraciones o seeders | `php artisan migrate:fresh --seed` (**borra los datos** de desarrollo) y, si se desea, `php artisan db:seed --class=DemoSeeder`. |
| Cualquier cambio | `php artisan test` para confirmar que todo sigue funcionando. |
