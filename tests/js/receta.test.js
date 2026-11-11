import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Carrito } from '../../resources/js/carrito.js';
const productos = () => [{id:1,precio_venta:'15.00',receta:{version:'a'.repeat(64),insumos:[{insumo_id:5,nombre:'Leche',cantidad:'150.000',unidad_medida:'ml'}]}},{id:2,precio_venta:'5.00',receta:null}];

test('Receta solo para productos que la tienen; carga original y cancelar borrador no modifica carrito',()=>{
    const c = new Carrito(productos()); c.agregar(1); c.agregar(2);
    assert.equal(c.tieneReceta(1),true); assert.equal(c.tieneReceta(2),false);
    const borrador = c.recetaUnidad(1,1); assert.equal(borrador.insumos[0].cantidad,'150.000');
    borrador.insumos[0].cantidad='170'; assert.equal(c.recetaUnidad(1,1).insumos[0].cantidad,'150.000');
    assert.throws(()=>c.recetaUnidad(2,1));
});
test('personalización por unidad, receta original intacta y serialización de cobro exacta',()=>{
    const p = productos(), original=structuredClone(p), c=new Carrito(p); c.agregar(1); c.agregar(1);
    const b=c.recetaUnidad(1,1); b.insumos[0].cantidad='170'; b.nota='Más leche'; c.guardarIndicacion(1,b);
    assert.equal(c.recetaUnidad(1,1).insumos[0].cantidad,'170');
    assert.equal(c.recetaUnidad(1,2).insumos[0].cantidad,'150.000');
    assert.deepEqual(p,original); assert.equal(c.total(),3000n);
    const payload=JSON.parse(JSON.stringify(c.detalles())); assert.equal(payload[0].indicaciones.length,1); assert.equal(payload[0].indicaciones[0].unidad,1);
    c.limpiar(); c.agregar(1); assert.equal(c.recetaUnidad(1,1).insumos[0].cantidad,'150.000');
});
test('restablecer y reducir unidades no conserva instrucciones de una unidad eliminada',()=>{
    const c=new Carrito(productos()); c.agregar(1); c.agregar(1);
    const original=c.recetaUnidad(1,2), b=structuredClone(original); b.insumos[0].cantidad='0'; c.guardarIndicacion(1,b);
    assert.equal(c.indicaciones.get('1').size,1); c.guardarIndicacion(1,original);
    assert.equal(c.indicaciones.get('1').size,0);
    c.guardarIndicacion(1,b); c.disminuir(1); c.agregar(1);
    assert.equal(c.recetaUnidad(1,2).insumos[0].cantidad,'150.000');
    c.quitar(1); c.agregar(1); assert.equal(c.detalles()[0].indicaciones,undefined);
});
test('rechaza cantidades inválidas, insumos ajenos y unidad que no existe',()=>{
    const c=new Carrito(productos()); c.agregar(1);
    for(const cantidad of ['-1','0.0001','abc','1000000000']) {
        const b=c.recetaUnidad(1,1); b.insumos[0].cantidad=cantidad;
        assert.throws(()=>c.guardarIndicacion(1,b));
    }
    const b=c.recetaUnidad(1,1); b.insumos[0].insumo_id=999; assert.throws(()=>c.guardarIndicacion(1,b));
    assert.throws(()=>c.recetaUnidad(1,2));
});


test('guardar varios borradores es atómico si una unidad tiene datos inválidos',()=>{
    const c=new Carrito(productos()); c.agregar(1); c.agregar(1);
    const a=c.recetaUnidad(1,1), b=c.recetaUnidad(1,2);
    a.insumos[0].cantidad='170'; b.insumos[0].cantidad='-5';
    assert.throws(()=>c.guardarIndicaciones(1,[a,b]));
    assert.equal(c.recetaUnidad(1,1).insumos[0].cantidad,'150.000');
    assert.equal(c.detalles()[0].indicaciones,undefined);
});
