<?php

namespace App\Services;

use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioInventario
{
    public function ajustar(Producto $producto, array $datos, int $usuarioId): void
    {
        DB::transaction(function () use ($producto, $datos, $usuarioId) {
            $producto = Producto::lockForUpdate()->findOrFail($producto->id);
            if (bccomp((string) $producto->stock_actual, (string) $datos['stock_esperado'], 3) !== 0) {
                throw ValidationException::withMessages(['stock_nuevo' => 'El stock cambió desde que abriste la página. Recarga y revisa la cantidad antes de ajustar.']);
            }
            $diferencia = bcsub((string) $datos['stock_nuevo'], (string) $producto->stock_actual, 3);
            if (bccomp($diferencia, '0', 3) === 0) {
                throw ValidationException::withMessages(['stock_nuevo' => 'La cantidad nueva es igual al stock actual.']);
            }
            DB::table('movimientos_inventario')->insert(['producto_id' => $producto->id, 'usuario_id' => $usuarioId, 'tipo' => 'ajuste', 'cantidad' => $diferencia, 'stock_anterior' => $producto->stock_actual, 'stock_nuevo' => $datos['stock_nuevo'], 'motivo' => $datos['motivo'], 'fecha' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $producto->stock_actual = $datos['stock_nuevo'];
            $producto->save();
            app(ServicioAlertas::class)->sincronizar($producto);
        });
    }
}
