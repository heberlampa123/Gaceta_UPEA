/**
 * Portal público (Fase 6): búsqueda con filtros sin recargar la página, usando Axios
 * contra la API pública GET /api/documentos (decisión técnica: Axios, no fetch).
 *
 * - Sin JavaScript, el formulario y la paginación funcionan como enlaces normales.
 * - Cada resultado se dibuja clonando <template id="plantillaResultado"> (el mismo diseño
 *   que usa el servidor) y llenando sus atributos data-campo y data-enlace con textContent/href,
 *   por lo que el contenido nunca se interpreta como HTML.
 * - La dirección del navegador se actualiza para que la búsqueda pueda compartirse o recargarse.
 */

const ESPERA_ESCRITURA_MS = 400;

export function iniciarPortal() {
    const formulario = document.querySelector('[data-portal-busqueda]');
    if (!formulario) return;

    const lista = document.querySelector('[data-portal-resultados]');
    const plantilla = document.getElementById('plantillaResultado');
    const total = document.querySelector('[data-total]');
    const cargando = document.querySelector('[data-cargando]');
    const error = document.querySelector('[data-error]');
    const sinResultados = document.querySelector('[data-sin-resultados]');
    const paginacion = document.querySelector('[data-paginacion]');
    const limpiar = document.querySelector('[data-limpiar-filtros]');

    let peticionEnCurso = null;
    let temporizador = null;

    /** Filtros con valor, para no enviar parámetros vacíos. */
    const parametros = (pagina) => {
        const datos = Object.fromEntries(
            [...new FormData(formulario).entries()].filter(([, valor]) => String(valor).trim() !== ''),
        );
        if (pagina > 1) datos.page = pagina;
        return datos;
    };

    const dibujarResultado = (documento) => {
        const nodo = plantilla.content.firstElementChild.cloneNode(true);

        nodo.querySelectorAll('[data-campo]').forEach((elemento) => {
            const valor = documento[elemento.dataset.campo] ?? '';
            elemento.textContent = valor;
            if (elemento.dataset.campo === 'resumen') elemento.hidden = valor === '';
        });
        nodo.querySelectorAll('[data-enlace]').forEach((enlace) => {
            enlace.href = documento[enlace.dataset.enlace] ?? '#';
        });

        return nodo;
    };

    const actualizarPaginacion = ({ current_page: actual, last_page: ultima }, filtros) => {
        paginacion.hidden = ultima <= 1;

        const enlace = (elemento, pagina, deshabilitado) => {
            elemento.dataset.pagina = String(pagina);
            elemento.href = `?${new URLSearchParams({ ...filtros, page: pagina })}`;
            elemento.classList.toggle('disabled', deshabilitado);
            elemento.setAttribute('aria-disabled', deshabilitado ? 'true' : 'false');
        };

        enlace(paginacion.querySelector('[data-anterior]'), Math.max(1, actual - 1), actual <= 1);
        enlace(paginacion.querySelector('[data-siguiente]'), Math.min(ultima, actual + 1), actual >= ultima);
        paginacion.querySelector('[data-pagina-texto]').textContent = `Página ${actual} de ${ultima}`;
    };

    const buscar = async (pagina = 1) => {
        const filtros = parametros(pagina);

        // Si el usuario sigue escribiendo, se cancela la búsqueda anterior.
        peticionEnCurso?.abort();
        peticionEnCurso = new AbortController();

        cargando.hidden = false;
        error.hidden = true;
        formulario.setAttribute('aria-busy', 'true');

        try {
            const { data: respuesta } = await window.axios.get(formulario.dataset.api, {
                params: filtros,
                signal: peticionEnCurso.signal,
                headers: { Accept: 'application/json' },
            });

            lista.replaceChildren(...respuesta.data.map(dibujarResultado));
            sinResultados.hidden = respuesta.meta.total > 0;
            total.textContent = `${respuesta.meta.total} documento(s) encontrado(s)`;
            actualizarPaginacion(respuesta.meta, parametros(1));

            const consulta = new URLSearchParams(filtros).toString();
            window.history.replaceState(null, '', consulta ? `?${consulta}` : window.location.pathname);

            if (pagina > 1) lista.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (excepcion) {
            if (window.axios.isCancel(excepcion)) return;

            error.textContent = excepcion.response?.status === 429
                ? 'Se realizaron demasiadas búsquedas en poco tiempo. Espere un momento e intente nuevamente.'
                : 'No se pudo realizar la búsqueda. Verifique su conexión e intente nuevamente.';
            error.hidden = false;
        } finally {
            cargando.hidden = true;
            formulario.removeAttribute('aria-busy');
        }
    };

    // Enviar el formulario (botón o Enter): buscar sin recargar.
    formulario.addEventListener('submit', (evento) => {
        evento.preventDefault();
        clearTimeout(temporizador);
        buscar(1);
    });

    // Listas y fechas: buscar al cambiar. Textos: buscar al dejar de escribir.
    formulario.addEventListener('change', (evento) => {
        if (evento.target.matches('select, input[type="date"]')) buscar(1);
    });
    formulario.addEventListener('input', (evento) => {
        if (!evento.target.matches('input[type="search"], input[type="text"]')) return;
        clearTimeout(temporizador);
        temporizador = setTimeout(() => buscar(1), ESPERA_ESCRITURA_MS);
    });

    // Paginación sin recargar.
    paginacion.addEventListener('click', (evento) => {
        const enlace = evento.target.closest('[data-pagina]');
        if (!enlace) return;
        evento.preventDefault();
        if (!enlace.classList.contains('disabled')) buscar(Number(enlace.dataset.pagina));
    });

    // Limpiar filtros sin recargar.
    limpiar?.addEventListener('click', (evento) => {
        evento.preventDefault();
        formulario.reset();
        formulario.querySelectorAll('input:not([type="hidden"])').forEach((campo) => { campo.value = ''; });
        formulario.querySelectorAll('select').forEach((lista) => { lista.selectedIndex = 0; });
        buscar(1);
    });
}
