<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Producto;
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
            $ruta = $datos['imagen']->store('productos', 'local');
            $datos['imagen'] = $ruta;
        } else {
            unset($datos['imagen']);
        }
        try {
            $resultado = DB::transaction(function () use ($datos, $usuarioId, $producto) {
                // La categoría se bloquea también al desactivarla o archivarla.
                $categoria = Categoria::lockForUpdate()->findOrFail($datos['categoria_id']);
                if (! $categoria->estado) {
                    throw ValidationException::withMessages(['categoria_id' => 'Selecciona una categoría activa.']);
                }
                $nuevo = ! $producto;
                $producto = $producto ? Producto::lockForUpdate()->findOrFail($producto->id) : new Producto;
                if (! $nuevo && $producto->stock_actual > 0 && $producto->unidad_medida !== $datos['unidad_medida']) {
                    throw ValidationException::withMessages(['unidad_medida' => 'No se puede cambiar la unidad de medida mientras exista stock.']);
                }
                $producto->fill($datos);
                if ($nuevo) {
                    $producto->stock_actual = $datos['stock_inicial'];
                }
                $producto->save();
                if ($nuevo && $producto->stock_actual > 0) {
                    DB::table('movimientos_inventario')->insert(['producto_id' => $producto->id, 'usuario_id' => $usuarioId, 'tipo' => 'ajuste', 'cantidad' => $producto->stock_actual, 'stock_anterior' => 0, 'stock_nuevo' => $producto->stock_actual, 'motivo' => 'Carga inicial del producto', 'fecha' => now(), 'created_at' => now(), 'updated_at' => now()]);
                }

                return $producto;
            });
        } catch (\Throwable $error) {
            if ($ruta) {
                Storage::disk('local')->delete($ruta);
            } throw $error;
        }
        if ($ruta && $anterior) {
            Storage::disk('local')->delete($anterior);
        }

        return $resultado;
    }
}
