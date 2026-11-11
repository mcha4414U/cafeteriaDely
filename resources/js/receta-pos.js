import { Modal } from 'bootstrap';
import { cantidadEntrada } from './formatos.js';

export function editorReceta(carrito, alGuardar, editable) {
    const elemento = document.getElementById('modal-receta');
    const modal = new Modal(elemento);
    const formulario = document.getElementById('form-receta');
    const selector = document.getElementById('unidad-receta');
    const campos = document.getElementById('insumos-receta');
    const nota = document.getElementById('nota-receta');
    const error = document.getElementById('error-receta');
    let productoId, unidad, borradores;
    function capturar() {
        if (!formulario.reportValidity()) return false;
        const borrador = borradores.get(unidad);
        borrador.nota = nota.value;
        campos.querySelectorAll('input').forEach((campo,n) => borrador.insumos[n].cantidad = campo.value);
        return true;
    }
    function mostrar() {
        const producto = carrito.productos.get(productoId);
        const borrador = borradores.get(unidad);
        campos.replaceChildren(); error.textContent = ''; nota.value = borrador.nota;
        producto.receta.insumos.forEach((insumo,n) => {
            const fila = document.createElement('div'); fila.className = 'mb-3';
            const etiqueta = document.createElement('label'); etiqueta.className = 'form-label';
            etiqueta.htmlFor = 'cantidad-receta-' + n; etiqueta.textContent = insumo.nombre + ' (' + insumo.unidad_medida + ')';
            const campo = document.createElement('input'); campo.id = etiqueta.htmlFor; campo.className = 'form-control';
            campo.type = 'number'; campo.min = '0'; campo.max = '999999999.999'; campo.step = '0.001'; campo.required = true;
            campo.value = cantidadEntrada(borrador.insumos[n].cantidad);
            fila.append(etiqueta,campo); campos.append(fila);
        });
    }
    selector.addEventListener('change', () => {
        if (!capturar()) { selector.value = String(unidad); return; }
        unidad = Number(selector.value); mostrar();
    });
    document.getElementById('restablecer-receta').addEventListener('click', () => {
        const receta = carrito.productos.get(productoId).receta;
        borradores.set(unidad,{unidad,version:receta.version,nota:'',insumos:receta.insumos.map(i=>({insumo_id:i.insumo_id,cantidad:String(i.cantidad)}))});
        mostrar();
    });
    formulario.addEventListener('submit', e => {
        e.preventDefault();
        if (!editable() || !capturar()) return;
        try {
            carrito.guardarIndicaciones(productoId,borradores.values());
            modal.hide(); alGuardar();
        } catch (e) { error.textContent = e.message; }
    });
    return id => {
        if (!editable() || !carrito.tieneReceta(id)) return;
        productoId = String(id); unidad = 1; borradores = new Map(); selector.replaceChildren();
        for(let n=1;n<=carrito.lineas.get(productoId);n++) {
            borradores.set(n,carrito.recetaUnidad(productoId,n));
            const opcion = document.createElement('option'); opcion.value = n; opcion.textContent = 'Unidad #' + n; selector.append(opcion);
        }
        document.getElementById('titulo-receta').textContent = 'Preparación de ' + carrito.productos.get(productoId).nombre;
        mostrar(); modal.show();
    };
}
