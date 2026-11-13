/**
 * Campana de notificaciones del panel (Fase 7, RF-08). Usa Axios contra la API protegida
 * con Sanctum (sesión del panel): GET /api/notificaciones/resumen y POST /api/notificaciones/leer-todas.
 *
 * - Se actualiza cada minuto y cuando el usuario vuelve a la pestaña.
 * - Cada aviso se dibuja clonando <template data-plantilla> (mismo diseño que el servidor),
 *   con textContent, por lo que el contenido nunca se interpreta como HTML.
 * - Sin JavaScript, la campana muestra los valores que envió el servidor al cargar la página.
 */

const INTERVALO_MS = 60_000;

export function iniciarNotificaciones() {
    const campana = document.querySelector('[data-notificaciones]');
    if (!campana) return;

    const contador = campana.querySelector('[data-contador]');
    const contadorMenu = document.querySelector('[data-contador-menu]');
    const lista = campana.querySelector('[data-lista]');
    const vacio = campana.querySelector('[data-vacio]');
    const plantilla = campana.querySelector('[data-plantilla]');
    const botonLeerTodas = campana.querySelector('[data-leer-todas-boton]');
    const botonCampana = campana.querySelector('[data-campana]');

    let temporizador = null;

    const pintarContador = (cantidad) => {
        for (const elemento of [contador, contadorMenu]) {
            if (!elemento) continue;
            elemento.textContent = cantidad > 99 ? '99+' : String(cantidad);
            elemento.hidden = cantidad === 0;
        }
        botonCampana.setAttribute('aria-label', cantidad ? `Notificaciones: ${cantidad} sin leer` : 'Notificaciones');
    };

    const dibujarAviso = (aviso) => {
        const nodo = plantilla.content.firstElementChild.cloneNode(true);
        nodo.href = aviso.url;
        nodo.classList.toggle('bg-primary-subtle', !aviso.leida);
        nodo.querySelector('[data-icono]').className = `bi bi-${aviso.icono} text-gaceta mt-1`;
        nodo.querySelectorAll('[data-campo]').forEach((elemento) => {
            elemento.textContent = aviso[elemento.dataset.campo] ?? '';
        });
        return nodo;
    };

    const actualizar = async () => {
        try {
            const { data } = await window.axios.get(campana.dataset.api, { headers: { Accept: 'application/json' } });
            pintarContador(data.no_leidas);
            lista.replaceChildren(...data.recientes.map(dibujarAviso));
            vacio.hidden = data.recientes.length > 0;
        } catch (error) {
            // Sesión cerrada o cuenta desactivada: se deja de consultar.
            if ([401, 403, 419].includes(error.response?.status)) detener();
        }
    };

    const iniciar = () => {
        detener();
        temporizador = setInterval(actualizar, INTERVALO_MS);
    };

    const detener = () => {
        clearInterval(temporizador);
        temporizador = null;
    };

    botonLeerTodas?.addEventListener('click', async () => {
        botonLeerTodas.disabled = true;
        try {
            await window.axios.post(campana.dataset.leerTodas, {}, { headers: { Accept: 'application/json' } });
            await actualizar();
        } finally {
            botonLeerTodas.disabled = false;
        }
    });

    // Al abrir la campana se muestran los avisos más recientes.
    campana.addEventListener('show.bs.dropdown', actualizar);

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            actualizar();
            iniciar();
        } else {
            detener();
        }
    });

    iniciar();
}
