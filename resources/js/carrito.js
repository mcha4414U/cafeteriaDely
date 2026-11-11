import { monedaCentavos } from './formatos.js';
// Importes enteros en centavos; no modifica el inventario.
export function centavos(precio) {
    if (!/^\d+(\.\d{1,2})?$/.test(String(precio))) throw new Error('Precio inválido');
    const [entero, fraccion = ''] = String(precio).split('.');
    return BigInt(entero) * 100n + BigInt(fraccion.padEnd(2, '0'));
}
export function moneda(importe) {
    return monedaCentavos(importe);
}
export class Carrito {
    constructor(productos) { this.productos = new Map(productos.map(producto => [String(producto.id), producto])); this.lineas = new Map(); this.indicaciones = new Map(); }
    agregar(id) {
        id = String(id);
        if (!this.productos.has(id)) return;
        const cantidad = this.lineas.get(id) ?? 0;
        if (cantidad < (this.tieneReceta(id) ? 200 : 9999)) this.lineas.set(id, cantidad + 1);
    }
    disminuir(id) { id = String(id); const cantidad = this.lineas.get(id) ?? 0; this.indicaciones.get(id)?.delete(cantidad); if (cantidad <= 1) this.quitar(id); else this.lineas.set(id, cantidad - 1); }
    quitar(id) { this.lineas.delete(String(id)); this.indicaciones.delete(String(id)); }
    limpiar() { this.lineas.clear(); this.indicaciones.clear(); }
    tieneReceta(id) { return !!this.productos.get(String(id))?.receta; }
    recetaUnidad(id, unidad) {
        id = String(id);
        const receta = this.productos.get(id)?.receta;
        if (!receta || !Number.isInteger(unidad) || unidad < 1 || unidad > (this.lineas.get(id) ?? 0)) throw new Error('Unidad de preparación inválida.');
        return structuredClone(this.indicaciones.get(id)?.get(unidad) ?? {
            unidad, version: receta.version, nota: '',
            insumos: receta.insumos.map(i => ({insumo_id:i.insumo_id, cantidad:String(i.cantidad)})),
        });
    }
    guardarIndicacion(id, indicacion) {
        id = String(id);
        this.recetaUnidad(id, indicacion.unidad);
        const receta = this.productos.get(id).receta;
        if (indicacion.version !== receta.version || indicacion.insumos.length !== receta.insumos.length ||
            indicacion.insumos.some((i,n) => i.insumo_id !== receta.insumos[n].insumo_id ||
            !/^\d+(\.\d{1,3})?$/.test(String(i.cantidad)) || Number(i.cantidad) > 999999999.999) ||
            typeof indicacion.nota !== 'string' || indicacion.nota.length > 500) throw new Error('Revisa las cantidades y la indicación.');
        if (!this.indicaciones.has(id)) this.indicaciones.set(id, new Map());
        const original = !indicacion.nota.trim() && indicacion.insumos.every((i,n) => Number(i.cantidad) === Number(receta.insumos[n].cantidad));
        if (original) this.indicaciones.get(id).delete(indicacion.unidad);
        else this.indicaciones.get(id).set(indicacion.unidad, structuredClone(indicacion));
    }
    guardarIndicaciones(id, indicaciones) {
        id = String(id);
        const anterior = this.indicaciones.has(id) ? structuredClone(this.indicaciones.get(id)) : null;
        try { for (const indicacion of indicaciones) this.guardarIndicacion(id, indicacion); }
        catch (error) {
            if (anterior) this.indicaciones.set(id, anterior); else this.indicaciones.delete(id);
            throw error;
        }
    }
    detalles() {
        return [...this.lineas].map(([id,cantidad]) => {
            const linea = {producto_id:Number(id),cantidad,precio_esperado:this.productos.get(id).precio_venta};
            if (this.indicaciones.get(id)?.size) linea.indicaciones = structuredClone([...this.indicaciones.get(id).values()]);
            return linea;
        });
    }
    total() { return [...this.lineas].reduce((total, [id, cantidad]) => total + centavos(this.productos.get(id).precio_venta) * BigInt(cantidad), 0n); }
}

