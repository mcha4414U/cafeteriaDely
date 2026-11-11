<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ServicioProductos
{
    public function guardar(array $datos, int $usuarioId, ?Producto $producto = null): Producto
    {
        $ruta = null;
        $anterior = $producto?->imagen;
        if (($datos['imagen'] ?? null) instanceof UploadedFile) {
            $ruta = $datos['imagen']->store('productos', 'public');
            $datos['imagen'] = $ruta;
        } else {
            unset($datos['imagen']);
        }
        try {
            $resultado = DB::transaction(function () use ($datos, $usuarioId, $producto, &$anterior) {
                // La categoría se bloquea también al desactivarla o archivarla.
                $categoria = ! empty($datos['categoria_id']) ? Categoria::lockForUpdate()->findOrFail($datos['categoria_id']) : null;
                if ($categoria && ! $categoria->estado) {
                    throw ValidationException::withMessages(['categoria_id' => 'Selecciona una categoría activa.']);
                }
                $nuevo = ! $producto;
                $producto = $producto ? Producto::lockForUpdate()->findOrFail($producto->id) : new Producto;
                $anterior = $producto->imagen;
                if (! $nuevo && $producto->tienePresentacion() && $datos['tipo'] !== 'insumo') {
                    throw ValidationException::withMessages(['tipo' => 'Conserva el tipo insumo: este artículo tiene una presentación configurada. Cambiarlo eliminaría la información de sus envases.']);
                }
                if (! $nuevo && $datos['tipo'] === 'insumo' && $producto->receta()->exists()) {
                    throw ValidationException::withMessages(['tipo' => 'Este producto tiene una receta. Conserva el tipo producto o ambos.']);
                }
                if ($datos['tipo'] === 'insumo') {
                    // Campo oculto: conservar su valor previo, sin convertir el insumo en vendible.
                    $datos['precio_venta'] = $producto->precio_venta ?? 0;
                    $datos['requiere_receta'] = false;
                }
                if (array_key_exists('requiere_receta', $datos)) {
                    $requiere = (bool) $datos['requiere_receta'];
                    if (! $requiere && ! $nuevo && ($producto->receta()->exists()
                        || DB::table('detalle_pedidos')->where('producto_id', $producto->id)->whereNotNull('preparacion')->exists())) {
                        throw ValidationException::withMessages(['requiere_receta' => 'No puedes desactivar la preparación: este producto tiene una receta o preparaciones históricas. La receta y el historial se conservan.']);
                    }
                    if (! \Illuminate\Support\Facades\Schema::hasColumn('productos', 'requiere_receta')) {
                        if ($requiere !== $producto->requiereReceta()) {
                            throw ValidationException::withMessages(['requiere_receta' => 'La opción requiere activar la migración pendiente con autorización del administrador.']);
                        }
                        unset($datos['requiere_receta']);
                    }
                }
                $datos['unidad_medida'] = $datos['unidad_medida'] ?? null;
                if (! $nuevo && ($producto->stock_actual > 0 || $producto->usadoComoInsumo()->exists() || $producto->movimientos()->exists()) && $producto->unidad_medida !== $datos['unidad_medida']) {
                    throw ValidationException::withMessages(['unidad_medida' => 'No se puede cambiar la unidad de medida mientras exista stock, historial de movimientos o se utilice en una receta.']);
                }
                if (! $nuevo && $datos['tipo'] === 'venta' && $producto->usadoComoInsumo()->exists()) {
                    throw ValidationException::withMessages(['tipo' => 'Primero retira este insumo de las recetas que lo utilizan.']);
                }
                if ($datos['tipo'] === 'insumo' && isset($datos['unidades_stock'])) {
                    $datos['stock_actual'] = \App\Support\PresentacionInsumo::total((string) $datos['contenido_por_unidad'], (string) $datos['unidades_stock']);
                }
                if ($datos['tipo'] !== 'insumo') {
                    $datos['contenido_por_unidad'] = null;
                    $datos['presentacion'] = null;
                }
                $stock = $datos['stock_actual'] ?? ($nuevo ? $datos['stock_inicial'] : $producto->stock_actual);
                if (! $nuevo && bccomp((string) $stock, (string) $producto->stock_actual, 3) !== 0) {
                    if (! Usuario::findOrFail($usuarioId)->tienePermiso('inventario.gestionar') || ! isset($datos['stock_esperado']) || empty(trim($datos['motivo_stock'] ?? ''))) {
                        throw ValidationException::withMessages(['stock_actual' => 'Para ajustar stock necesitas permiso de inventario, la cantidad original y un motivo.']);
                    }
                    app(ServicioInventario::class)->ajustar($producto, ['stock_esperado' => $datos['stock_esperado'], 'stock_nuevo' => $stock, 'motivo' => $datos['motivo_stock']], $usuarioId);
                    $producto->refresh();
                }
                unset($datos['stock_actual'], $datos['unidades_stock']);
                $datos['precio_compra'] = $datos['precio_compra'] ?? 0;
                if (array_key_exists('contenido_por_unidad', $datos) && $datos['contenido_por_unidad'] != $producto->contenido_por_unidad) {
                    $producto->precio_compra_presentacion = null;
                }
                $producto->fill($datos);
                if ($nuevo) {
                    $producto->stock_actual = $stock;
                }
                $producto->save();
                app(ServicioAlertas::class)->sincronizar($producto);
                if ($nuevo && $producto->stock_actual > 0) {
                    DB::table('movimientos_inventario')->insert(['producto_id' => $producto->id, 'usuario_id' => $usuarioId, 'tipo' => 'ajuste', 'cantidad' => $producto->stock_actual, 'stock_anterior' => 0, 'stock_nuevo' => $producto->stock_actual, 'motivo' => 'Carga inicial del producto', 'fecha' => now(), 'created_at' => now(), 'updated_at' => now()]);
                }

                return $producto;
            });
        } catch (\Throwable $error) {
            if ($ruta) {
                Storage::disk('public')->delete($ruta);
            } throw $error;
        }
        if ($ruta && $anterior) {
            Storage::disk('public')->delete($anterior);
            Storage::disk('local')->delete($anterior);
        }

        return $resultado;
    }
}
