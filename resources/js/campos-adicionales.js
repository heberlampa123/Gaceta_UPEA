/**
 * Campos adicionales de los tipos documentales (Fase 4).
 *
 * 1) Editor de campos en el formulario de tipos documentales: agregar y quitar filas,
 *    generar el nombre interno a partir de la etiqueta y mostrar las opciones de las listas.
 * 2) Formulario de documentos: mostrar solo los campos del tipo documental elegido.
 *
 * Todo ocurre en el navegador (no hay peticiones al servidor).
 */

/** "N.º de sesión" → "no_de_sesion" */
const nombreInterno = (texto) =>
    texto
        .normalize('NFKD') // «º» → «o», «á» → «a» (igual que Str::ascii en PHP)
        .replace(/[\u0300-\u036f]/g, '') // quita las tildes
        .toLowerCase()
        .replace(/[^a-z0-9\s_-]/g, '') // quita puntos y símbolos (igual que Str::slug)
        .replace(/[\s_-]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .replace(/^(\d)/, 'campo_$1')
        .slice(0, 40);

function iniciarEditorDeCampos() {
    const contenedor = document.querySelector('[data-campos]');
    if (!contenedor) return;

    const plantilla = document.getElementById('plantillaCampo');
    const botonAgregar = document.querySelector('[data-agregar-campo]');
    const mensajeVacio = document.querySelector('[data-campos-vacio]');
    let siguiente = Number(contenedor.dataset.siguiente || 0);

    const actualizarVacio = () => {
        if (mensajeVacio) mensajeVacio.hidden = contenedor.querySelector('[data-campo]') !== null;
    };

    const prepararFila = (fila) => {
        const tipo = fila.querySelector('[data-tipo-campo]');
        const opciones = fila.querySelector('[data-opciones]');
        const etiqueta = fila.querySelector('[data-etiqueta]');
        const nombre = fila.querySelector('[data-nombre]');
        const quitar = fila.querySelector('[data-quitar-campo]');

        tipo.addEventListener('change', () => {
            opciones.hidden = tipo.value !== 'seleccion';
        });

        // En los campos nuevos, el nombre interno se genera solo mientras el usuario no lo edite.
        if (!nombre.readOnly) {
            if (nombre.value) nombre.dataset.editado = '1';
            nombre.addEventListener('input', () => {
                nombre.dataset.editado = '1';
            });
            etiqueta.addEventListener('input', () => {
                if (!nombre.dataset.editado) nombre.value = nombreInterno(etiqueta.value);
            });
        }

        quitar.addEventListener('click', () => {
            const existente = quitar.dataset.existente === '1';
            if (existente && !window.confirm('Los valores ya registrados de este campo dejarán de mostrarse. ¿Quitar el campo?')) {
                return;
            }
            fila.remove();
            actualizarVacio();
        });
    };

    contenedor.querySelectorAll('[data-campo]').forEach(prepararFila);

    botonAgregar?.addEventListener('click', () => {
        contenedor.insertAdjacentHTML('beforeend', plantilla.innerHTML.replaceAll('__INDICE__', String(siguiente++)));
        const fila = contenedor.lastElementChild;
        prepararFila(fila);
        actualizarVacio();
        fila.querySelector('[data-etiqueta]').focus();
    });
}

function iniciarCamposPorTipo() {
    const selector = document.querySelector('[data-selector-tipo]');
    if (!selector) return;

    const alternar = () => {
        document.querySelectorAll('[data-campos-tipo]').forEach((grupo) => {
            const visible = grupo.dataset.camposTipo === selector.value;
            grupo.hidden = !visible;
            grupo.disabled = !visible; // los campos deshabilitados no se envían
        });
    };

    selector.addEventListener('change', alternar);
    alternar();
}

export function iniciarCamposAdicionales() {
    iniciarEditorDeCampos();
    iniciarCamposPorTipo();
}
