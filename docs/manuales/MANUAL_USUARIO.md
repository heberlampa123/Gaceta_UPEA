# Manual de usuario

**Sistema Web Integral de Gestión de la Gaceta Universitaria — Universidad Pública de El Alto**

Versión 1.0 · Noviembre de 2026

---

## Contenido

1. [Introducción](#1-introducción)
2. [Conceptos básicos](#2-conceptos-básicos)
3. [Acceso al sistema](#3-acceso-al-sistema)
4. [El panel administrativo](#4-el-panel-administrativo)
5. [Encargado de documentos](#5-encargado-de-documentos)
6. [Revisor / Publicador](#6-revisor--publicador)
7. [Administrador](#7-administrador)
8. [Portal público (visitantes)](#8-portal-público-visitantes)
9. [Preguntas frecuentes](#9-preguntas-frecuentes)

---

## 1. Introducción

El sistema permite **registrar, revisar, aprobar, publicar y consultar** los documentos oficiales de la Universidad
Pública de El Alto. En su primera versión gestiona las **Resoluciones del Honorable Consejo Universitario (HCU)**.

El sistema tiene dos espacios:

| Espacio | Para quién | Cómo se accede |
|---|---|---|
| **Panel administrativo** | Personal autorizado (Administrador, Encargado de documentos, Revisor / Publicador) | Botón **Panel administrativo** del portal, con usuario y contraseña |
| **Portal público** | Cualquier persona (visitantes) | Dirección principal del sistema, sin iniciar sesión |

### Roles

| Rol | Qué hace |
|---|---|
| **Administrador** | Gestiona cuentas, roles y permisos, tipos documentales y consulta la auditoría. |
| **Encargado de documentos** | Registra documentos, adjunta los PDF, corrige y atiende observaciones, y los envía a revisión. |
| **Revisor / Publicador** | Revisa los documentos, los devuelve con observaciones o los aprueba, y los publica en el portal. |
| **Visitante** | Busca, consulta y descarga los documentos publicados en el portal. |

> Cada persona ve en el menú solo las opciones que le permite su rol.

### Requisitos

Un navegador actualizado (Google Chrome, Microsoft Edge, Mozilla Firefox o Brave) y conexión a la red donde está el sistema.

---

## 2. Conceptos básicos

### 2.1 Estados de un documento

Cada documento pasa por los siguientes estados:

| Estado | Significado | Visible en el portal |
|---|---|---|
| **Borrador** | Registrado por el Encargado; todavía no se envió a revisión. Se puede modificar. | No |
| **Pendiente de revisión** | Enviado a revisión; espera al Revisor. No se puede modificar. | No |
| **Observado** | El Revisor lo devolvió con observaciones. El Encargado debe corregirlo. | No |
| **Aprobado para publicación** | El Revisor lo autorizó; falta publicarlo. | No |
| **Publicado** | Disponible para cualquier persona en el portal público. | **Sí** |

### 2.2 Recorrido de un documento

```
  Encargado              Revisor / Publicador
  ─────────              ────────────────────
  Registra ──► Borrador
                 │ Enviar a revisión
                 ▼
          Pendiente de revisión ──► Aprobar ──► Aprobado ──► Publicar ──► Publicado
                 ▲        │
     Atender y   │        │ Devolver con observaciones
     reenviar    │        ▼
                 └──── Observado
```

Solo se permiten estos pasos. Por ejemplo, no se puede publicar un documento que no fue aprobado.

### 2.3 Versiones del PDF

Cada documento tiene un archivo PDF. Si hay que corregirlo, se carga una **nueva versión**: la anterior **no se borra**,
solo deja de ser la vigente. La versión vigente es la que se revisa y la que se publica.

Cada versión guarda su **huella digital SHA-256**, un código único que cambia si el archivo se modifica, aunque sea
mínimamente. Sirve para comprobar que un PDF no fue alterado.

### 2.4 Seguimiento

Cada documento tiene un **historial** con todos sus pasos: quién lo registró, envió, observó, aprobó o publicó, y cuándo.
Este historial no se puede modificar ni borrar.

### 2.5 Notificaciones

El sistema avisa automáticamente:

| Aviso | Lo recibe |
|---|---|
| Nuevo documento por revisar / Documento corregido por revisar | Los Revisores |
| Su documento fue observado | Quien registró el documento |
| Su documento fue aprobado | Quien registró el documento |
| Su documento fue publicado | Quien registró el documento |

---

## 3. Acceso al sistema

### 3.1 Iniciar sesión

1. Abra la dirección del sistema y presione **Panel administrativo**.
2. Escriba su **nombre de usuario o correo** y su **contraseña**.
3. Presione **Ingresar**.

> El ícono del ojo junto a la contraseña permite mostrarla u ocultarla.

### 3.2 Primer ingreso: contraseña temporal

Las cuentas las crea el Administrador, que le entregará una **contraseña temporal**. En su primer ingreso, el sistema le
pedirá **cambiarla** antes de continuar:

1. Escriba la contraseña temporal en **Contraseña actual**.
2. Escriba su nueva contraseña dos veces. Debe tener **al menos 8 caracteres, con letras y números**.
3. Presione **Actualizar contraseña**.

### 3.3 Bloqueo por intentos fallidos

Si se equivoca de contraseña **5 veces seguidas**, la cuenta se **bloquea durante 15 minutos**. El sistema le indica la
hora a la que podrá intentar nuevamente. Si necesita ingresar antes, pida al Administrador que la desbloquee.

### 3.4 Olvidé mi contraseña

Comuníquese con el Administrador. Él le asignará una nueva contraseña temporal, que deberá cambiar al ingresar.

### 3.5 Mi perfil y cambio de contraseña

En el menú de su nombre (esquina superior derecha) encontrará:

- **Mi perfil**: sus datos, roles y permisos.
- **Cambiar contraseña**: en cualquier momento, indicando la contraseña actual.

Para modificar sus datos (nombre, correo, cargo) o sus roles, comuníquese con el Administrador.

### 3.6 Cerrar sesión

Menú de su nombre → **Cerrar sesión**. Hágalo siempre al terminar, especialmente en equipos compartidos.

---

## 4. El panel administrativo

### 4.1 Partes de la pantalla

| Parte | Ubicación | Función |
|---|---|---|
| **Menú lateral** | Izquierda (en el celular, botón ☰) | Acceso a las secciones según su rol. |
| **Campana** 🔔 | Barra superior | Muestra sus avisos; el número rojo indica los no leídos. Se actualiza sola cada minuto. |
| **Menú de su nombre** | Esquina superior derecha | Mi perfil, cambiar contraseña y cerrar sesión. |

### 4.2 Inicio (tablero)

Al ingresar verá:

- **Mis pendientes**: lo que tiene por hacer según su rol (documentos por revisar, por publicar, observados, borradores…).
  Al presionar una tarjeta, va directamente a esos documentos.
- **Documentos por estado**: cantidad de documentos en cada estado.
- **Notificaciones sin leer**, **documentos con movimiento reciente** y **su actividad reciente**.

### 4.3 Notificaciones

- En la **campana**, presione un aviso para abrir el documento; el aviso se marca como leído.
- **Marcar todas como leídas**: en la campana o en la página **Notificaciones** del menú.
- La página **Notificaciones** muestra todos sus avisos, con la opción **Sin leer**.

---

## 5. Encargado de documentos

### 5.1 Registrar un documento

1. Menú **Gestión documental** → **Registrar documento**.
2. Complete los datos:

| Campo | Descripción | Ejemplo |
|---|---|---|
| Tipo documental * | Tipo de documento | Resolución del HCU |
| Instancia emisora * | Quién emite el documento | Honorable Consejo Universitario |
| Número * | Número oficial (letras, números, `/`, `-` y `.`) | 025/2026 |
| Gestión * | Año al que corresponde | 2026 |
| Fecha de emisión * | No puede ser una fecha futura | 10/11/2026 |
| Título * | Título o denominación | Resolución que aprueba el Reglamento de Titulación |
| Descripción | Resumen del contenido (se usa en las búsquedas) | — |
| Archivo PDF * | Solo PDF, **hasta 20 MB** | resolucion_025.pdf |

   Si el tipo documental tiene **campos adicionales** (por ejemplo, «N.º de sesión»), aparecerán al elegirlo.

3. Presione **Registrar**. El documento queda en estado **Borrador**.

> No pueden existir dos documentos del mismo tipo con el mismo número en la misma gestión.
> El sistema verifica que el archivo sea realmente un PDF.

### 5.2 Consultar documentos

Menú **Gestión documental**: lista de documentos con búsqueda (número, título o palabras clave) y filtros por estado,
gestión y tipo. Presione **Ver** para abrir la ficha del documento, que muestra:

- El **PDF vigente** en pantalla, con los botones **Abrir** y **Descargar**.
- Los **datos** del documento.
- Las **versiones** del PDF, con su huella SHA-256 y el botón **Verificar integridad**.
- Las **observaciones** recibidas y sus respuestas.
- El **seguimiento** (historial completo).

### 5.3 Modificar datos

Solo en estado **Borrador** u **Observado**: en la ficha, presione **Editar datos**, corrija y presione
**Guardar cambios**. Para cambiar el PDF, cargue una nueva versión (5.4).

### 5.4 Cargar una nueva versión del PDF

Solo en estado **Borrador** u **Observado**:

1. En la ficha, sección **Cargar nueva versión**, seleccione el PDF corregido.
2. Escriba el **motivo del cambio** (por ejemplo, «Corrección del número en el encabezado»).
3. Presione **Cargar versión**.

La nueva versión pasa a ser la vigente; la anterior se conserva en la lista de versiones.
No se acepta un archivo idéntico al vigente.

### 5.5 Enviar a revisión

1. Revise los datos y el PDF.
2. En la ficha, sección **Enviar a revisión**, escriba un comentario para el revisor (opcional).
3. Presione **Enviar a revisión** y confirme.

El documento pasa a **Pendiente de revisión** y ya no puede modificarse. Los Revisores reciben un aviso.

### 5.6 Atender observaciones y reenviar

Cuando un documento es **observado**, recibirá el aviso «Su documento fue observado».

1. Abra el documento (desde el aviso o desde **Gestión documental**).
2. Lea las observaciones (sección **Observaciones**).
3. Si el PDF debe corregirse, **cargue primero la nueva versión** (5.4). Si hay que corregir datos, use **Editar datos**.
4. En **Atender observaciones**, escriba **cómo atendió cada observación**. Todas son obligatorias.
5. Presione **Reenviar a revisión**.

El documento vuelve a **Pendiente de revisión** y los Revisores reciben el aviso «Documento corregido por revisar».

---

## 6. Revisor / Publicador

### 6.1 Bandeja de revisión

Menú **Revisión y publicación**. Tiene tres pestañas, cada una con su contador:

| Pestaña | Contenido |
|---|---|
| **Pendientes de revisión** | Documentos enviados que esperan su revisión. |
| **Aprobados por publicar** | Documentos aprobados que falta publicar. |
| **Devueltos con observaciones** | Documentos que están siendo corregidos por el Encargado. |

Los documentos más antiguos aparecen primero.

> **Importante:** nadie puede revisar, aprobar ni publicar un documento que **registró él mismo**.
> Esos documentos no aparecen en su bandeja; debe revisarlos otro Revisor.

### 6.2 Revisar un documento

Presione **Revisar**. En la ficha verá el PDF vigente, los datos, las versiones y el historial.
Según lo que encuentre:

**Si está correcto → Aprobar**

1. En **Revisión**, escriba un comentario (opcional).
2. Presione **Aprobar para publicación** y confirme. El documento pasa a **Aprobado**.

**Si tiene errores → Devolver con observaciones**

1. En **Devolver con observaciones**, describa la observación.
2. Para registrar otra, presione **Agregar otra observación**.
3. Presione **Devolver para corrección**. El documento pasa a **Observado** y el Encargado recibe el aviso.

### 6.3 Publicar

1. Pestaña **Aprobados por publicar** → **Publicar**.
2. Presione **Publicar en el portal** y confirme.

El documento queda **disponible para cualquier persona** en el portal público, y el Encargado recibe el aviso.
En la ficha aparece el botón **Ver en el portal**.

> Publicar es definitivo en esta versión del sistema. Verifique el documento antes de publicarlo.

---

## 7. Administrador

### 7.1 Usuarios

Menú **Usuarios**: lista con búsqueda y filtros por estado (activos, inactivos, bloqueados) y por rol.

**Crear una cuenta**

1. **Nuevo usuario**.
2. Complete nombres, apellidos, correo institucional, **nombre de usuario** (minúsculas, sin espacios; por ejemplo `jperez`) y cargo.
3. **Contraseña temporal**: escríbala o **déjela vacía para que el sistema la genere**.
4. Marque uno o más **roles**.
5. **Crear usuario**.

El sistema muestra la **contraseña temporal una sola vez**: anótela y entréguela al usuario. Él deberá cambiarla al ingresar.

**Acciones sobre una cuenta** (botón **Editar**, panel **Estado de la cuenta**):

| Acción | Efecto |
|---|---|
| **Guardar cambios** | Actualiza los datos y los roles. |
| **Restablecer contraseña** | Genera una nueva contraseña temporal (se muestra una sola vez) y obliga a cambiarla. También desbloquea la cuenta. |
| **Desbloquear** | Aparece si la cuenta está bloqueada por intentos fallidos. |
| **Desactivar cuenta** | El usuario ya no puede ingresar; si estaba conectado, se cierra su sesión. Sus registros se conservan. |
| **Activar cuenta** | Vuelve a habilitar una cuenta desactivada. |

> Las cuentas **no se eliminan**: se desactivan, para conservar la trazabilidad de sus acciones.
> No puede desactivar su propia cuenta, y el sistema no permite quedarse sin ningún usuario que pueda gestionar cuentas.

### 7.2 Roles y permisos

Menú **Roles y permisos**. El sistema autoriza cada acción por el **código del permiso**; los roles solo agrupan permisos.
Por eso puede crear roles nuevos sin modificar el sistema.

| Permiso | Autoriza |
|---|---|
| `usuario.gestionar` | Crear, modificar, desactivar y desbloquear cuentas. |
| `rol.gestionar` | Administrar roles y asignar permisos. |
| `tipo_documental.gestionar` | Crear y administrar tipos documentales. |
| `auditoria.ver` | Consultar la auditoría. |
| `documento.ver` | Consultar los documentos y su seguimiento. |
| `documento.registrar` | Registrar documentos y adjuntar su PDF. |
| `documento.editar` | Modificar datos y cargar nuevas versiones. |
| `documento.enviar_revision` | Enviar documentos a revisión. |
| `observacion.atender` | Atender observaciones y reenviar. |
| `documento.revisar` | Revisar y devolver con observaciones. |
| `documento.aprobar` | Aprobar para publicación. |
| `documento.publicar` | Publicar en el portal. |

- **Nuevo rol**: código (mayúsculas, por ejemplo `SECRETARIA`; no se puede cambiar después), nombre, descripción y permisos.
- **Editar**: nombre, descripción, estado y permisos.
- El rol **Administrador** no puede desactivarse y conserva siempre los permisos de gestión de usuarios y roles.

### 7.3 Tipos documentales

Menú **Tipos documentales**. Permite agregar nuevos tipos (por ejemplo «Resolución Rectoral» o «Reglamento»)
**sin modificar el sistema**.

1. **Nuevo tipo**: código (por ejemplo `RES_RECTORAL`; no cambia después), nombre y descripción.
2. **Campos adicionales** (opcional): datos propios del tipo que se pedirán en cada documento.
   - **Agregar campo** → escriba la **etiqueta** (por ejemplo «N.º de sesión»). El **nombre interno** se genera solo.
   - Elija el **tipo de dato**: texto corto, texto largo, número, fecha, lista de opciones o sí/no.
   - Para **lista de opciones**, escriba una opción por línea (mínimo dos).
   - Marque **Oblig.** si el dato es obligatorio.
3. **Crear tipo**.

Un tipo **inactivo** deja de ofrecerse al registrar documentos; los ya registrados no cambian.
Si quita un campo de un tipo con documentos, sus valores se conservan pero dejan de mostrarse.

### 7.4 Auditoría

Menú **Auditoría**: todas las operaciones del sistema (inicios de sesión, intentos fallidos, bloqueos, creación y
modificación de registros, cambios de permisos, envíos, devoluciones, aprobaciones y publicaciones).

- **Filtros**: usuario, acción, entidad, ID del registro y rango de fechas.
- **Ver detalle**: valores anteriores y nuevos de los datos modificados. Las contraseñas nunca se registran.
- **Exportar a Excel (CSV)**: descarga los registros filtrados.

Los intentos fallidos y los bloqueos se resaltan en rojo. Los registros de auditoría no se pueden modificar ni eliminar.

---

## 8. Portal público (visitantes)

Dirección principal del sistema. No requiere iniciar sesión. Solo muestra documentos **publicados**.

### 8.1 Buscar

- **Buscar**: escriba el número, parte del título o palabras clave. Los resultados se actualizan mientras escribe.
- **Filtros**: tipo documental, gestión, número de resolución, fecha de emisión (desde / hasta), instancia emisora y orden.
- **Limpiar**: quita todos los filtros.

La dirección del navegador refleja la búsqueda: puede copiarla para compartir los mismos resultados.

### 8.2 Consultar y descargar

- **Ver documento**: abre la ficha con el PDF en pantalla y sus datos.
- **Descargar PDF**: guarda el archivo en su equipo.
- **Abrir en pestaña nueva**: muestra el PDF a pantalla completa.

### 8.3 Verificar un PDF descargado

La ficha muestra la **huella SHA-256** del PDF publicado. Para comprobar que una copia no fue alterada, calcule la huella
del archivo y compárela:

- **Windows (PowerShell):** `Get-FileHash .\archivo.pdf -Algorithm SHA256`
- **Linux / macOS:** `sha256sum archivo.pdf`

Si ambas huellas coinciden, el archivo es idéntico al publicado.

---

## 9. Preguntas frecuentes

| Situación | Qué hacer |
|---|---|
| «El usuario o la contraseña son incorrectos» | Verifique mayúsculas y minúsculas. Después de 5 intentos la cuenta se bloquea 15 minutos. |
| «La cuenta está bloqueada temporalmente» | Espere la hora indicada o pida al Administrador que la desbloquee. |
| «Su cuenta está desactivada» | Comuníquese con el Administrador. |
| No veo una opción del menú | Su rol no tiene ese permiso. Consulte al Administrador. |
| No puedo editar un documento | Solo se edita en Borrador u Observado. En otros estados está bloqueado. |
| No puedo aprobar un documento | Si usted lo registró, debe aprobarlo otro Revisor. |
| «El archivo supera el tamaño máximo» | El PDF debe pesar como máximo 20 MB. Reduzca su tamaño (por ejemplo, escaneando a menor resolución). |
| «El archivo no es un PDF válido» | El archivo no es un PDF real (por ejemplo, una imagen o un Word renombrado). Expórtelo nuevamente como PDF. |
| «La sesión expiró» (error 419) | La página estuvo abierta mucho tiempo. Vuelva atrás, recargue la página e intente de nuevo. |
| «Demasiadas solicitudes» (error 429) | Se hicieron muchas búsquedas o descargas seguidas. Espere un minuto. |
| La campana no se actualiza | Recargue la página. Si accede por una dirección distinta a la habitual, consulte al administrador técnico. |
