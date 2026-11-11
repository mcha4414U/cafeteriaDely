import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Carrito, moneda } from '../../resources/js/carrito.js';

test('orden: dos clics, aumentar, disminuir, quitar y limpiar sin cambiar productos', () => {
    const productos = [{ id: 1, precio_venta: '16.00', stock_actual: '0.000' }, { id: 2, precio_venta: '0.10', stock_actual: '10.000' }];
    const original = structuredClone(productos);
    const carrito = new Carrito(productos);
    carrito.agregar(1); carrito.agregar(1);
    assert.equal(carrito.lineas.get('1'), 2);
    assert.equal(moneda(carrito.total()), 'Bs. 32,00');
    carrito.agregar(2); carrito.agregar(2); carrito.agregar(2);
    assert.equal(moneda(carrito.total()), 'Bs. 32,30');
    carrito.disminuir(1);
    assert.equal(moneda(carrito.total()), 'Bs. 16,30');
    carrito.quitar(2); carrito.disminuir(1); carrito.disminuir(1);
    assert.equal(carrito.total(), 0n);
    carrito.agregar(999);
    assert.equal(carrito.lineas.size, 0);
    carrito.agregar(1); carrito.limpiar();
    assert.equal(carrito.lineas.size, 0);
    assert.deepEqual(productos, original);
});

test('el total conserva centavos exactos incluso con precios grandes', () => {
    const carrito = new Carrito([{id: 1, precio_venta: '9999999999.99'}]);
    carrito.agregar(1); carrito.agregar(1);
    assert.equal(moneda(carrito.total()), 'Bs. 19.999.999.999,98');
});
