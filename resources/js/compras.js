import { cantidadMostrar, monedaCentavos } from './formatos.js';
// Cantidades y dinero como enteros para que la vista previa coincida con el redondeo del servidor.
const escalado=(valor,decimales)=>{
    const texto=String(valor).trim();
    if(!/^\d+(\.\d*)?$/.test(texto)) return 0n;
    const [entero,fraccion='']=texto.split('.');
    return BigInt(entero+fraccion.padEnd(decimales,'0').slice(0,decimales));
};
const dinero=monedaCentavos;
// Etiquetas de presentación: no convertir cantidades ni modificar la unidad guardada.
const unidadVisible = unidad => ({
    kilogramo: 'kg', kilogramos: 'kg', kg: 'kg',
    gramo: 'g', gramos: 'g', g: 'g',
    litro: 'L', litros: 'L', l: 'L',
    mililitro: 'ml', mililitros: 'ml', ml: 'ml',
    unidad: 'unidades', unidades: 'unidades', cantidad: 'unidades',
}[String(unidad || '').trim().toLowerCase()] ?? (unidad || ''));
document.querySelectorAll('[data-compra]').forEach(formulario=>{
    const cuerpo=formulario.querySelector('[data-lineas-compra]');
    let siguiente=Math.max(-1,...Array.from(cuerpo.querySelectorAll('select')).map(select=>Number(select.name.match(/\[(\d+)\]/)?.[1] ?? 0)))+1;
    const recalcular=()=>{
        let total=0n;
        cuerpo.querySelectorAll('[data-linea-compra]').forEach(fila=>{
            const opcion = fila.querySelector('[data-producto-compra]').selectedOptions[0];
            const modo = fila.querySelector('[data-modo-compra]');
            modo.querySelector('[value="envases"]').disabled = !opcion?.dataset.contenido;
            const porEnvases = modo.value === 'envases';
            const unidad = unidadVisible(opcion?.dataset.unidad);
            const etiquetaCantidad = fila.querySelector('[data-unidad-cantidad]');
            etiquetaCantidad.textContent = porEnvases ? (opcion?.dataset.presentacion || 'envases') : unidad;
            etiquetaCantidad.hidden = !etiquetaCantidad.textContent;
            const esperado = fila.querySelector('[data-contenido-esperado]');
            if (!esperado.value && opcion?.dataset.contenido) esperado.value = opcion.dataset.contenido;
            esperado.disabled = !porEnvases;
            const cantidad=escalado(fila.querySelector('[data-cantidad-compra]').value,3);
            const factor = porEnvases ? escalado(esperado.value,3) : 1000n;
            const totalStock = cantidad * factor;
            const entero = totalStock / 1000000n;
            const fraccion = (totalStock % 1000000n).toString().padStart(6,'0').replace(/0+$/, '');
            fila.querySelector('[data-entrada-stock]').textContent = 'Entrada: ' + cantidadMostrar(entero.toString() + (fraccion ? '.' + fraccion : '')) + ' ' + unidad + (porEnvases ? ' · Costo por ' + (opcion?.dataset.presentacion || 'envase') : ' · Costo por unidad de medida');
            const costo=escalado(fila.querySelector('[data-costo-compra]').value,2);
            const subtotal=(cantidad*costo+500n)/1000n;
            total+=subtotal; fila.querySelector('[data-subtotal-compra]').textContent=dinero(subtotal);
        });
        formulario.querySelector('[data-total-compra]').textContent=dinero(total);
    };
    formulario.querySelector('[data-agregar-linea]').addEventListener('click',()=>{
        if(cuerpo.children.length>=100) return;
        const fragmento=formulario.querySelector('template').content.cloneNode(true);
        fragmento.querySelectorAll('[name]').forEach(campo=>campo.name=campo.name.replace('INDICE',siguiente));
        siguiente++; cuerpo.append(fragmento); recalcular();
    });
    cuerpo.addEventListener('click',evento=>{
        if(!evento.target.closest('[data-quitar-linea]')) return;
        if(cuerpo.children.length>1) evento.target.closest('tr').remove();
        recalcular();
    });
    cuerpo.addEventListener('change',evento=>{
        if(evento.target.matches('[data-producto-compra]')) {
            const fila = evento.target.closest('tr');
            fila.querySelector('[data-modo-compra]').value = 'base';
            fila.querySelector('[data-contenido-esperado]').value = evento.target.selectedOptions[0]?.dataset.contenido || '';
            fila.querySelector('[data-costo-compra]').value=evento.target.selectedOptions[0]?.dataset.costo || '0';
        }
        if (evento.target.matches('[data-modo-compra]')) {
            const fila = evento.target.closest('tr');
            const opcion = fila.querySelector('[data-producto-compra]').selectedOptions[0];
            fila.querySelector('[data-costo-compra]').value = evento.target.value === 'envases' ? (opcion?.dataset.costoEnvase || '') : (opcion?.dataset.costo || '0');
        }
        recalcular();
    });
    cuerpo.addEventListener('input',recalcular);
    recalcular();
});
