import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

test('tipo insumo oculta campos de venta sin borrar valores; producto los recupera',()=>{
    const precio={name:'precio_venta',value:'15.00',required:true,disabled:false};
    const requiere={name:'requiere_receta',value:'1',disabled:false,addEventListener(){}};
    const bloques=[{querySelectorAll:()=>[precio]},{querySelectorAll:()=>[requiere]}];
    const aviso={hidden:true};
    const tipo={value:'venta',addEventListener(evento,fn){this.actualizar=fn;}};
    const codigo=readFileSync(new URL('../../resources/js/producto-formulario.js',import.meta.url),'utf8').replaceAll('export function','function');
    runInNewContext(codigo,{document:{getElementById:id=>id==='tipo'?tipo:(id==='requiere_receta'?requiere:null),querySelectorAll:()=>bloques,querySelector:()=>aviso}});
    assert.equal(precio.required,true); assert.equal(aviso.hidden,false);
    tipo.value='insumo'; tipo.actualizar();
    assert.equal(precio.disabled,true); assert.equal(precio.required,false);
    assert.equal(requiere.disabled,true); assert.equal(aviso.hidden,true);
    assert.equal(precio.value,'15.00'); assert.equal(requiere.value,'1');
    tipo.value='venta'; tipo.actualizar();
    assert.equal(precio.disabled,false); assert.equal(requiere.disabled,false);
    assert.equal(precio.required,true); assert.equal(aviso.hidden,false);
});

test('costo automático exacto, validación y retorno al precio manual',()=>{
    const codigo=readFileSync(new URL('../../resources/js/producto-formulario.js',import.meta.url),'utf8').replaceAll('export function','function');
    const contexto={document:{getElementById:()=>null}};
    runInNewContext(codigo,contexto);
    assert.equal(contexto.costoPorCantidad('120','24'),'5.00');
    assert.equal(contexto.costoPorCantidad('10','3'),'3.33');
    assert.equal(contexto.costoPorCantidad('1.01','2'),'0.51');
    for(const [total,cantidad] of [['120','0'],['','24'],['-1','2'],['1','-2'],['1',''],['1.001','2'],['9999999999.99','0.001']]) assert.equal(contexto.costoPorCantidad(total,cantidad),null);
    const campo=value=>({value,classList:{toggle(){}},setCustomValidity(error){this.error=error;},addEventListener(e,f){this[e]=f;}});
    const modo=campo('unidad'), precio=campo('8.50'), total=campo('120'), cantidad=campo('24');
    contexto.iniciarCostoProducto(modo,precio,total,cantidad,[]);
    assert.equal(precio.value,'8.50');
    modo.value='cantidad'; modo.change();
    assert.equal(precio.readOnly,true); assert.equal(precio.value,'5.00');
    total.value='240'; total.input(); assert.equal(precio.value,'10.00');
    cantidad.value='12'; cantidad.input(); assert.equal(precio.value,'20.00');
    precio.value='0.01'; precio.input(); assert.equal(precio.value,'20.00');
    modo.value='unidad'; modo.change(); assert.equal(precio.readOnly,false); assert.equal(precio.value,'8.50');
});
