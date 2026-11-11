export function adaptarTipo(tipo, bloques, aviso, requiere) {
    const esProducto = tipo !== 'insumo';
    bloques.forEach(bloque => {
        bloque.hidden = !esProducto;
        bloque.querySelectorAll('input,select').forEach(campo => {
            campo.disabled = !esProducto;
            if (campo.name === 'precio_venta') campo.required = esProducto;
        });
    });
    if (aviso) aviso.hidden = !esProducto || requiere?.value !== '1';
}

export function costoPorCantidad(total, cantidad) {
    if (!/^\d+(\.\d{1,2})?$/.test(total) || !/^\d+(\.\d{1,3})?$/.test(cantidad)) return null;
    const entero = (valor, decimales) => {
        const [a, b = ''] = valor.split('.');
        return BigInt(a + b.padEnd(decimales, '0'));
    };
    const centavos = entero(total, 2), milesimas = entero(cantidad, 3);
    if (milesimas <= 0n || milesimas > 999999999999n || centavos > 999999999999n) return null;
    const resultado = (centavos * 1000n * 2n + milesimas) / (milesimas * 2n);
    if (resultado > 999999999999n) return null;
    return `${resultado / 100n}.${(resultado % 100n).toString().padStart(2, '0')}`;
}

export function iniciarCostoProducto(modo, precio, total, cantidad, bloques) {
    const manual = { valor: precio.value };
    const actualizar = () => {
        const automatico = modo.value === 'cantidad';
        modo.setCustomValidity(['unidad', 'cantidad'].includes(modo.value) ? '' : 'Selecciona Unidad o Cantidad.');
        precio.readOnly = automatico;
        precio.classList.toggle('bg-body-secondary', automatico);
        bloques.forEach(bloque => {
            bloque.hidden = !automatico;
            bloque.querySelectorAll('input').forEach(input => { input.disabled = !automatico; input.required = automatico; });
        });
        total.setCustomValidity('');
        cantidad.setCustomValidity('');
        if (!automatico) return;
        const resultado = costoPorCantidad(total.value, cantidad.value);
        precio.value = resultado ?? '';
        if (resultado === null) total.setCustomValidity('Introduce un total no negativo y una cantidad mayor que cero, dentro de los límites permitidos.');
    };
    modo.addEventListener('change', () => {
        if (modo.value === 'cantidad') manual.valor = precio.value;
        else precio.value = manual.valor;
        actualizar();
    });
    total.addEventListener('input', actualizar);
    cantidad.addEventListener('input', actualizar);
    precio.addEventListener('input', () => { if (modo.value === 'cantidad') actualizar(); });
    actualizar();
}

const modoCosto = document.getElementById('modo_costo');
if (modoCosto) {
    iniciarCostoProducto(modoCosto, document.getElementById('precio_compra'), document.getElementById('precio_total_compra'),
        document.getElementById(modoCosto.dataset.edicion === '1' ? 'cantidad_compra' : 'stock_actual'), document.querySelectorAll('[data-costo-cantidad]'));
    const etiquetaPrecio = document.querySelector('label[for="precio_compra"]');
    const etiquetaStock = document.querySelector('label[for="stock_actual"]');
    const actualizarEtiquetas = () => {
        etiquetaPrecio.textContent = modoCosto.value === 'cantidad' ? 'Precio de compra por unidad (Bs.)' : 'Precio de compra por unidad de medida (Bs.)';
        etiquetaStock.textContent = modoCosto.value === 'cantidad' && modoCosto.dataset.edicion === '0' ? 'Stock disponible (cantidad de unidades)' : 'Stock disponible (unidad de medida)';
    };
    modoCosto.addEventListener('change', actualizarEtiquetas);
    actualizarEtiquetas();
}
const tipo = document.getElementById('tipo');
if (tipo && document.getElementById('requiere_receta')) {
    const requiere = document.getElementById('requiere_receta');
    const actualizar = () => adaptarTipo(tipo.value, document.querySelectorAll('[data-solo-producto]'), document.querySelector('[data-aviso-receta]'), requiere);
    tipo.addEventListener('change', actualizar);
    requiere.addEventListener('change', actualizar);
    actualizar();
}
