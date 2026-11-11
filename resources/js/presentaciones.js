import { cantidadEntrada, cantidadMostrar } from './formatos.js';
const bloque = document.querySelector('[data-presentacion-insumo]');
if (bloque) {
    const tipo = document.getElementById('tipo');
    const contenido = document.getElementById('contenido_por_unidad');
    const unidades = document.getElementById('unidades_stock');
    const stock = document.getElementById('stock_actual');
    const medida = document.getElementById('unidad_medida');
    const presentacion = document.querySelector('[data-presentacion-opciones]');
    const otra = document.getElementById('otra_presentacion');
    const otraBloque = document.getElementById('bloque-otra-presentacion');
    const validarPresentacion = () => {
        if (!otra) return;
        const activa = tipo.value === 'insumo' && presentacion.value === 'Otro';
        otraBloque.hidden = !activa;
        otra.disabled = !activa;
        otra.required = activa;
        otra.setCustomValidity(activa && !otra.value.trim() ? 'Especifica otra presentación.' : '');
        presentacion.setCustomValidity(Array.from(presentacion.options).some(opcion => opcion.value === presentacion.value) ? '' : 'Selecciona una presentación válida.');
        medida.setCustomValidity(Array.from(medida.options).some(opcion => opcion.value === medida.value) ? '' : 'Selecciona una unidad válida.');
    };
    const readonlyOriginal = stock.readOnly;
    const mil = valor => {
        if (!/^\d+(\.\d{0,3})?$/.test(valor)) return null;
        const [entero, decimal = ''] = valor.split('.');
        return BigInt(entero + decimal.padEnd(3, '0'));
    };
    const actualizar = () => {
        const insumo = tipo.value === 'insumo';
        bloque.hidden = !insumo;
        bloque.querySelectorAll('input, select').forEach(campo => campo.disabled = !insumo);
        validarPresentacion();
        const calcular = insumo && unidades.value !== '';
        contenido.required = calcular;
        stock.readOnly = readonlyOriginal || calcular;
        unidades.setCustomValidity('');
        const aviso = bloque.querySelector('[data-total-presentacion]');
        aviso.textContent = '';
        if (!calcular) return;
        const c = mil(contenido.value), u = mil(unidades.value);
        if (c === null || u === null || c <= 0n) return;
        const producto = c * u;
        if (producto % 1000n !== 0n || producto > 999999999999000n) {
            unidades.setCustomValidity('El total excede el límite o requiere más de tres decimales.');
            aviso.textContent = 'Revisa contenido y unidades; el total debe ser exacto hasta tres decimales.';
            return;
        }
        const total = producto / 1000n;
        stock.value = cantidadEntrada((total / 1000n).toString() + '.' + (total % 1000n).toString().padStart(3, '0'));
        aviso.textContent = 'Stock disponible: ' + cantidadMostrar(stock.value) + ' ' + medida.value;
    };
    [tipo, contenido, unidades, medida].forEach(campo => campo.addEventListener('input', actualizar));
    if (otra) {
        presentacion.addEventListener('change', () => {
            if (presentacion.value !== 'Otro') otra.value = '';
            validarPresentacion();
        });
        otra.addEventListener('input', validarPresentacion);
    }
    actualizar();
}
