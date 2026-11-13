/**
 * Relaciones entre normas (13/11/2026): en la ficha del documento, busca con Axios las normas
 * publicadas y vigentes que se pueden abrogar, derogar o modificar, y guarda la elegida en un
 * campo oculto. Los resultados se dibujan con textContent (nunca como HTML).
 */
const ESPERA_ESCRITURA_MS = 350;

export function iniciarRelacionesDocumentales() {
    const formulario = document.querySelector('[data-relaciones]');
    if (!formulario) return;

    const buscador = formulario.querySelector('#buscar_norma');
    const destino = formulario.querySelector('[data-destino]');
    const lista = formulario.querySelector('[data-candidatos]');
    const seleccionada = formulario.querySelector('[data-seleccionada]');
    const tipo = formulario.querySelector('#id_tipo_relacion');
    const descripcionTipo = formulario.querySelector('[data-descripcion-tipo]');
    const detalleOpcional = formulario.querySelector('[data-detalle-opcional]');
    const textoAyuda = seleccionada.textContent;

    let temporizador = null;
    let peticion = null;

    // Descripción del tipo elegido y si la «parte afectada» es obligatoria.
    const actualizarTipo = () => {
        const opcion = tipo.selectedOptions[0];
        descripcionTipo.textContent = opcion?.dataset.descripcion ?? '';
        const requiere = opcion?.dataset.requiereDetalle === '1';
        detalleOpcional.textContent = requiere ? '(obligatoria)' : '(opcional)';
        formulario.querySelector('#detalle').required = requiere;
    };
    tipo.addEventListener('change', actualizarTipo);
    actualizarTipo();

    const limpiarLista = () => lista.replaceChildren();

    const elegir = (norma) => {
        destino.value = norma.id;
        buscador.value = norma.nombre;
        seleccionada.textContent = `Seleccionada: ${norma.nombre} — ${norma.titulo}`;
        limpiarLista();
    };

    const mostrar = (normas) => {
        limpiarLista();
        if (normas.length === 0) {
            const vacio = document.createElement('div');
            vacio.className = 'list-group-item text-body-secondary';
            vacio.textContent = 'No se encontraron normas publicadas y vigentes con ese criterio.';
            lista.appendChild(vacio);
            return;
        }
        normas.forEach((norma) => {
            const boton = document.createElement('button');
            boton.type = 'button';
            boton.className = 'list-group-item list-group-item-action';
            const nombre = document.createElement('div');
            nombre.className = 'fw-semibold';
            nombre.textContent = `${norma.nombre} · ${norma.fecha_emision}`;
            const titulo = document.createElement('div');
            titulo.className = 'text-body-secondary text-truncate';
            titulo.textContent = norma.titulo;
            boton.append(nombre, titulo);
            boton.addEventListener('click', () => elegir(norma));
            lista.appendChild(boton);
        });
    };

    buscador.addEventListener('input', () => {
        // Al escribir de nuevo, se descarta la norma elegida antes.
        destino.value = '';
        seleccionada.textContent = textoAyuda;
        clearTimeout(temporizador);

        const texto = buscador.value.trim();
        if (texto.length < 2) {
            limpiarLista();
            return;
        }

        temporizador = setTimeout(async () => {
            peticion?.abort();
            peticion = new AbortController();
            try {
                const { data } = await window.axios.get(formulario.dataset.urlCandidatos, {
                    params: { q: texto },
                    signal: peticion.signal,
                });
                mostrar(data.data);
            } catch (error) {
                if (window.axios.isCancel(error)) return;
                limpiarLista();
                seleccionada.textContent = 'No se pudo realizar la búsqueda. Intente nuevamente.';
            }
        }, ESPERA_ESCRITURA_MS);
    });

    // Enter en el buscador no debe enviar el formulario sin una norma elegida.
    buscador.addEventListener('keydown', (evento) => {
        if (evento.key === 'Enter') evento.preventDefault();
    });

    formulario.addEventListener('submit', (evento) => {
        if (!destino.value) {
            evento.preventDefault();
            seleccionada.textContent = 'Busque y seleccione la norma afectada de la lista.';
            buscador.focus();
        }
    });
}
