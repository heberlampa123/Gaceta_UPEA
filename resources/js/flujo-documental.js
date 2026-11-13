/**
 * Flujo de revisión (Fase 5): el revisor puede escribir varias observaciones antes de devolver
 * el documento. Cada clic en «Agregar otra observación» crea un cuadro de texto nuevo.
 * Todo ocurre en el navegador (no hay peticiones al servidor).
 */
export function iniciarFlujoDocumental() {
    const contenedor = document.querySelector('[data-observaciones]');
    const boton = document.querySelector('[data-agregar-observacion]');
    if (!contenedor || !boton) return;

    boton.addEventListener('click', () => {
        const modelo = contenedor.querySelector('textarea');
        const nuevo = modelo.cloneNode(false);
        nuevo.value = '';
        contenedor.appendChild(nuevo);
        nuevo.focus();
    });
}
