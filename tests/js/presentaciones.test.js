import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { cantidadEntrada, cantidadMostrar } from '../../resources/js/formatos.js';

const codigo = readFileSync(new URL('../../resources/js/presentaciones.js', import.meta.url), 'utf8').replace(/^import .*;\r?\n/, '');
function formulario(valores = {}) {
    const campos = {};
    for (const [id, valor] of Object.entries({tipo:'insumo',contenido_por_unidad:'946',unidades_stock:'24',stock_actual:'0',unidad_medida:'ml',...valores})) {
        campos[id] = {value:valor, readOnly:false, addEventListener(evento, funcion) { this.actualizar = funcion; }, setCustomValidity(error) { this.error = error; }};
    }
    const aviso = {textContent:''};
    const bloque = {querySelectorAll:()=>[campos.contenido_por_unidad,campos.unidades_stock],querySelector:()=>aviso};
    runInNewContext(codigo, {cantidadEntrada,cantidadMostrar,document:{querySelector:()=>bloque,getElementById:id=>campos[id]}});
    return {campos,bloque,aviso};
}

test('Otro exige texto y cambiar a una presentación normal descarta el texto anterior', () => {
    const campos = {};
    for (const [id, value] of Object.entries({tipo:'insumo',contenido_por_unidad:'946',unidades_stock:'',stock_actual:'9',unidad_medida:'ml',presentacion:'Otro',otra_presentacion:'   '})) {
        campos[id] = {value, options:[{value}], addEventListener(evento, fn) { this[evento] = fn; }, setCustomValidity(error) {this.error = error;}};
    }
    campos['bloque-otra-presentacion'] = {};
    campos.presentacion.options.push({value:'Bolsa'});
    const bloque = {querySelectorAll:()=>Object.values(campos), querySelector:()=>({})};
    runInNewContext(codigo, {cantidadEntrada,cantidadMostrar,document:{querySelector:selector=>selector === '[data-presentacion-opciones]' ? campos.presentacion : bloque,getElementById:id=>campos[id]}});
    assert.equal(campos.otra_presentacion.required,true);
    assert.ok(campos.otra_presentacion.error);
    campos.otra_presentacion.value = 'Envase retornable';
    campos.otra_presentacion.input();
    assert.equal(campos.otra_presentacion.error,'');
    campos.presentacion.value = 'Bolsa';
    campos.presentacion.change();
    assert.equal(campos.otra_presentacion.value,'');
    assert.equal(campos.otra_presentacion.disabled,true);
    assert.equal(campos['bloque-otra-presentacion'].hidden,true);
    campos.unidad_medida.value = 'galón';
    campos.unidad_medida.input();
    assert.ok(campos.unidad_medida.error);
    assert.equal(campos.stock_actual.value,'9');
});
test('la vista previa calcula 24 bolsas de 946 ml y mantiene exactos los decimales', () => {
    const {campos} = formulario();
    assert.equal(campos.stock_actual.value,'22704');
    assert.equal(campos.stock_actual.readOnly,true);
    campos.contenido_por_unidad.value='0.946';
    campos.unidad_medida.value='L';
    campos.contenido_por_unidad.actualizar();
    assert.equal(campos.stock_actual.value,'22.704');
});
test('editar sin conteo conserva el contenido parcial y los productos de venta no convierten', () => {
    const {campos} = formulario({unidades_stock:'',stock_actual:'22554.000'});
    assert.equal(campos.stock_actual.value,'22554.000');
    assert.equal(campos.stock_actual.readOnly,false);
    const venta = formulario({tipo:'venta',stock_actual:'24'});
    assert.equal(venta.campos.stock_actual.value,'24');
    assert.equal(venta.bloque.hidden,true);
    assert.equal(venta.campos.contenido_por_unidad.disabled,true);
});
test('rechaza pérdida de precisión y exceso de stock en la vista previa', () => {
    for (const valores of [
        {contenido_por_unidad:'0.001',unidades_stock:'0.001'},
        {contenido_por_unidad:'999999999',unidades_stock:'24'},
    ]) {
        const {campos} = formulario(valores);
        assert.ok(campos.unidades_stock.error);
        assert.equal(campos.stock_actual.value,'0');
    }
});
