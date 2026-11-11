import test from 'node:test';
import assert from 'node:assert/strict';
import { cantidadEntrada, cantidadMostrar, monedaCentavos } from '../../resources/js/formatos.js';

test('cantidades exactas, miles y decimales; inputs separados de presentación',()=>{
    for(const [valor,visible,entrada] of [
        ['2.000','2','2'],['2000.000','2.000','2000'],['1.500','1,5','1.5'],
        ['0.250','0,25','0.25'],['150.125','150,125','150.125'],
        ['999999999.999','999.999.999,999','999999999.999'],['23.841437','23,841437','23.841437'],
        ['-0.000','0','0'],['-1000.50','-1.000,5','-1000.5'],
    ]) {
        assert.equal(cantidadMostrar(valor),visible);
        assert.equal(cantidadEntrada(valor),entrada);
    }
});
test('dinero mantiene dos decimales e importes grandes exactos',()=>{
    assert.equal(monedaCentavos(1500n),'Bs. 15,00');
    assert.equal(monedaCentavos(200025n),'Bs. 2.000,25');
    assert.equal(monedaCentavos(1999999999998n),'Bs. 19.999.999.999,98');
});
