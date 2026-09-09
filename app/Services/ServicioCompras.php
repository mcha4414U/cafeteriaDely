<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioCompras
{
    public function registrar(array $datos, int $usuarioId): Compra
    {
        $lineas = collect($datos['detalles'])->sortBy('producto_id')->map(fn ($linea) => [
            'producto_id' => (int) $linea['producto_id'], 'cantidad' => bcadd((string) $linea['cantidad'], '0', 3),
            'costo_unitario' => bcadd((string) $linea['costo_unitario'], '0', 2),
        ])->values()->all();
        $huella = hash('sha256', json_encode([(int) $datos['proveedor_id'], $datos['fecha'], $datos['observaciones'] ?? null, $lineas], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($datos, $usuarioId, $lineas, $huella) {
            // Serializar envíos del mismo usuario, incluidos dobles clics y reintentos.
            Usuario::whereKey($usuarioId)->lockForUpdate()->firstOrFail();
            $existente = Compra::where('clave_idempotencia', $datos['clave_idempotencia'])->first();
            if ($existente) {
                if ($existente->usuario_id !== $usuarioId || $existente->huella_solicitud !== $huella) {
                    throw ValidationException::withMessages(['clave_idempotencia' => 'Este formulario ya se utilizó para otra compra. Abre una compra nueva.']);
                }

                return $existente;
            }
            $proveedor = Proveedor::lockForUpdate()->findOrFail($datos['proveedor_id']);
            if (! $proveedor->estado) {
                throw ValidationException::withMessages(['proveedor_id' => 'El proveedor ya no está activo.']);
            }
            // Bloquear los productos en orden de ID para reducir interbloqueos.
            $productos = Producto::whereIn('id', array_column($lineas, 'producto_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $total = '0.00';
            $preparadas = [];
            foreach ($lineas as $linea) {
                $producto = $productos->get($linea['producto_id']);
                if (! $producto || ! $producto->estado) {
                    throw ValidationException::withMessages(['detalles' => 'Uno de los productos ya no está disponible.']);
                }
                $nuevoStock = bcadd($producto->stock_actual, $linea['cantidad'], 3);
                if (bccomp($nuevoStock, '999999999.999', 3) > 0) {
                    throw ValidationException::withMessages(['detalles' => 'La compra supera el stock máximo permitido para '.$producto->nombre.'.']);
                }
                // Redondeo comercial por línea; nunca confiar en los totales del navegador.
                $subtotal = bcadd(bcadd(bcmul($linea['cantidad'], $linea['costo_unitario'], 5), '0.005', 5), '0', 2);
                $total = bcadd($total, $subtotal, 2);
                if (bccomp($total, '9999999999.99', 2) > 0) {
                    throw ValidationException::withMessages(['detalles' => 'El total supera el importe máximo permitido.']);
                }
                $preparadas[] = compact('producto', 'linea', 'subtotal', 'nuevoStock');
            }
            $compra = Compra::create([
                'proveedor_id' => $proveedor->id, 'usuario_id' => $usuarioId, 'numero' => 'TEMP-'.$datos['clave_idempotencia'],
                'fecha' => $datos['fecha'], 'subtotal' => $total, 'total' => $total, 'estado' => 'registrada',
                'observaciones' => $datos['observaciones'] ?? null, 'clave_idempotencia' => $datos['clave_idempotencia'],
                'huella_solicitud' => $huella, 'nombre_proveedor' => $proveedor->nombre,
            ]);
            $compra->update(['numero' => 'C-'.str_pad((string) $compra->id, 6, '0', STR_PAD_LEFT)]);
            foreach ($preparadas as $preparada) {
                ['producto' => $producto,'linea' => $linea,'subtotal' => $subtotal,'nuevoStock' => $nuevoStock] = $preparada;
                $compra->detalles()->create($linea + ['subtotal' => $subtotal, 'nombre_producto' => $producto->nombre, 'unidad_medida' => $producto->unidad_medida]);
                MovimientoInventario::create([
                    'producto_id' => $producto->id, 'usuario_id' => $usuarioId, 'compra_id' => $compra->id, 'tipo' => 'entrada_compra',
                    'cantidad' => $linea['cantidad'], 'stock_anterior' => $producto->stock_actual, 'stock_nuevo' => $nuevoStock,
                    'motivo' => 'Recepción de compra '.$compra->numero, 'fecha' => now(),
                ]);
                $producto->stock_actual = $nuevoStock;
                $producto->precio_compra = $linea['costo_unitario'];
                $producto->save();
            }

            return $compra;
        }, 3);
    }
}
