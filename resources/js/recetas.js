import { cantidadEntrada } from './formatos.js';
const datos = document.getElementById('datos-receta');
if (datos) {
    const { insumos, detalles } = JSON.parse(datos.textContent);
    const cuerpo = document.getElementById('filas-receta');
    const aviso = document.getElementById('aviso-receta');
    let siguiente = 0;
    function actualizarOpciones() {
        const elegidos = [...cuerpo.querySelectorAll('select')].map(campo => campo.value).filter(Boolean);
        cuerpo.querySelectorAll('select').forEach(campo => [...campo.options].forEach(opcion => {
            opcion.disabled = !!opcion.value && opcion.value !== campo.value && elegidos.includes(opcion.value);
        }));
    }
    function agregar(detalle = {}) {
        if (cuerpo.children.length >= 100) { aviso.textContent = 'Máximo 100 ingredientes por receta.'; return; }
        const indice = siguiente++;
        const fila = document.createElement('tr');
        const selector = document.createElement('select');
        selector.className = 'form-select'; selector.required = true;
        selector.name = 'detalles[' + indice + '][insumo_id]';
        selector.setAttribute('aria-label', 'Insumo');
        selector.add(new Option('Selecciona un insumo', ''));
        insumos.forEach(insumo => selector.add(new Option(insumo.nombre, String(insumo.id))));
        if (detalle.insumo_id && !insumos.some(insumo => String(insumo.id) === String(detalle.insumo_id))) {
            selector.add(new Option('Insumo no disponible; selecciona otro', String(detalle.insumo_id)));
        }
        selector.value = String(detalle.insumo_id ?? '');
        const cantidad = document.createElement('input');
        cantidad.className = 'form-control'; cantidad.type = 'number'; cantidad.min = '0.001'; cantidad.max = '999999999.999'; cantidad.step = '0.001'; cantidad.required = true;
        cantidad.name = 'detalles[' + indice + '][cantidad]'; cantidad.value = detalle.cantidad == null ? '' : cantidadEntrada(detalle.cantidad);
        cantidad.setAttribute('aria-label', 'Cantidad del insumo');
        const unidad = document.createElement('input');
        unidad.className = 'form-control'; unidad.readOnly = true; unidad.required = true;
        unidad.name = 'detalles[' + indice + '][unidad_medida]'; unidad.setAttribute('aria-label', 'Unidad del stock');
        unidad.value = insumos.find(insumo => String(insumo.id) === selector.value)?.unidad_medida ?? detalle.unidad_medida ?? '';
        selector.addEventListener('change', () => {
            unidad.value = insumos.find(insumo => String(insumo.id) === selector.value)?.unidad_medida ?? '';
            aviso.textContent = unidad.value ? '' : 'Define la unidad de medida del insumo en Productos antes de usarlo.';
            actualizarOpciones();
        });
        const quitar = document.createElement('button');
        quitar.type = 'button'; quitar.className = 'btn btn-outline-danger btn-sm'; quitar.textContent = 'Quitar';
        quitar.addEventListener('click', () => { fila.remove(); actualizarOpciones(); });
        [selector, cantidad, unidad, quitar].forEach(elemento => { const celda = document.createElement('td'); celda.append(elemento); fila.append(celda); });
        cuerpo.append(fila); actualizarOpciones();
    }
    (Array.isArray(detalles) ? detalles : Object.values(detalles)).forEach(agregar);
    document.getElementById('agregar-insumo').addEventListener('click', () => agregar());
    if (!cuerpo.children.length) agregar();
    document.getElementById('formulario-receta').addEventListener('submit', evento => {
        if (!cuerpo.children.length || [...cuerpo.querySelectorAll('input[readonly]')].some(campo => !campo.value)) {
            evento.preventDefault(); aviso.textContent = 'Añade al menos un insumo con una unidad de medida.';
        }
    });
}
