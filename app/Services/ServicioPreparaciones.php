<?php

namespace App\Services;

use App\Models\{Producto, Receta, Usuario, Venta};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioPreparaciones
{
    public function receta(Receta $receta): array
    {
        $insumos = $receta->detalles->sortBy('insumo_id')->map(fn ($d) => [
            'insumo_id' => $d->insumo_id, 'nombre' => $d->insumo?->nombre ?? 'Insumo',
            'cantidad' => $d->cantidad, 'unidad_medida' => $d->unidad_medida,
        ])->values()->all();
        $datos = ['nombre' => $receta->nombre, 'descripcion' => $receta->descripcion, 'insumos' => $insumos];
        return $datos + ['version' => hash('sha256', json_encode([$receta->id, $receta->estado, $datos], JSON_THROW_ON_ERROR))];
    }

    public static function normalizarIndicaciones(array $indicaciones): array
    {
        return collect($indicaciones)->sortBy('unidad')->map(fn ($i) => [
            'unidad' => (int) $i['unidad'], 'version' => $i['version'], 'nota' => $i['nota'] ?? null,
            'insumos' => collect($i['insumos'])->sortBy('insumo_id')->map(fn ($d) => [
                'insumo_id' => (int) $d['insumo_id'], 'cantidad' => bcadd((string) $d['cantidad'], '0', 3),
            ])->values()->all(),
        ])->values()->all();
    }

    public function unidades(Producto $producto, Receta $receta, array $linea): array
    {
        $original = $this->receta($receta);
        $indicaciones = collect($linea['indicaciones'] ?? []);
        if ($indicaciones->pluck('unidad')->unique()->count() !== $indicaciones->count()
            || $indicaciones->contains(fn ($i) => $i['unidad'] < 1 || $i['unidad'] > $linea['cantidad'])) {
            throw ValidationException::withMessages(['detalles' => 'Las indicaciones deben corresponder a unidades distintas de la orden.']);
        }
        $porUnidad = $indicaciones->keyBy('unidad');
        $filas = [];
        for ($unidad = 1; $unidad <= $linea['cantidad']; $unidad++) {
            $indicacion = $porUnidad->get($unidad);
            if ($indicacion && $indicacion['version'] !== $original['version']) {
                throw ValidationException::withMessages(['detalles' => 'Cambió la receta de '.$producto->nombre.'. Recarga el POS y revisa las indicaciones antes de cobrar.']);
            }
            $personalizados = collect($indicacion['insumos'] ?? []);
            if ($indicacion && ($personalizados->pluck('insumo_id')->sort()->values()->all() !== collect($original['insumos'])->pluck('insumo_id')->sort()->values()->all())) {
                throw ValidationException::withMessages(['detalles' => 'Solo puedes indicar cantidades de los insumos originales de '.$producto->nombre.'.']);
            }
            $porInsumo = $personalizados->keyBy('insumo_id');
            $insumos = collect($original['insumos'])->map(function ($d) use ($porInsumo) {
                return ['insumo_id' => $d['insumo_id'], 'nombre' => $d['nombre'], 'unidad_medida' => $d['unidad_medida'],
                    'cantidad_original' => $d['cantidad'], 'cantidad' => $porInsumo->get($d['insumo_id'])['cantidad'] ?? $d['cantidad']];
            })->all();
            $nota = $indicacion['nota'] ?? null;
            $personalizado = filled($nota) || collect($insumos)->contains(fn ($d) => bccomp($d['cantidad'], $d['cantidad_original'], 3) !== 0);
            $filas[] = [
                'producto_id' => $producto->id, 'nombre_producto' => $producto->nombre,
                'unidad_numero' => $unidad, 'cantidad' => 1, 'precio_unitario' => $producto->precio_venta, 'subtotal' => $producto->precio_venta,
                'estado_preparacion' => 'pendiente',
                'preparacion' => json_encode(['receta' => $original['nombre'], 'descripcion' => $original['descripcion'],
                    'personalizado' => $personalizado, 'nota' => $nota, 'insumos' => $insumos], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        return $filas;
    }

    // Se llama dentro de la transacción de cobro: no cobra ni mueve inventario.
    public function registrar(Venta $venta, array $filas): void
    {
        if (! $filas) return;
        $pedidoId = $venta->pedido_id;
        if (! $pedidoId) {
            $pedidoId = DB::table('pedidos')->insertGetId([
                'cliente_id' => $venta->cliente_id, 'usuario_id' => $venta->usuario_id,
                'numero' => 'PREP-'.$venta->numero, 'fecha' => $venta->fecha,
                'estado' => 'pendiente', 'es_preparacion' => true, 'notas' => $venta->notas,
                'subtotal' => $venta->subtotal, 'descuento' => $venta->descuento, 'total' => $venta->total,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $venta->update(['pedido_id' => $pedidoId]);
        } else {
            // Conserva las líneas históricas del pedido previo; las nuevas llevan unidad_numero.
            DB::table('pedidos')->where('id', $pedidoId)->update(['es_preparacion' => true, 'estado' => 'pendiente', 'notas' => $venta->notas, 'updated_at' => now()]);
        }
        foreach ($filas as $fila) DB::table('detalle_pedidos')->insert($fila + ['pedido_id' => $pedidoId]);
    }

    public function consulta(Usuario $usuario)
    {
        $q = DB::table('detalle_pedidos as d')->join('pedidos as p', 'p.id', '=', 'd.pedido_id')
            ->join('ventas as v', 'v.pedido_id', '=', 'p.id')
            ->where('p.es_preparacion', true)->whereNotNull('d.preparacion')
            ->select('d.*', 'v.numero as numero_venta', 'v.id as venta_id', 'v.estado as estado_venta', 'v.fecha as fecha_venta', 'v.notas as nota_venta');
        if (! $usuario->tienePermiso('preparaciones.gestionar') && ! $usuario->tienePermiso('ventas.todas')) {
            $q->where('p.usuario_id', $usuario->id);
        }
        return $q;
    }

    public function cambiarEstado(int $pedidoId, int $detalleId, string $estado, Usuario $usuario): void
    {
        DB::transaction(function () use ($pedidoId, $detalleId, $estado, $usuario) {
            // Mismo primer bloqueo que una anulación; ambas operaciones no pueden cruzarse.
            $venta = Venta::where('pedido_id', $pedidoId)->lockForUpdate()->firstOrFail();
            $pedido = DB::table('pedidos')->where('id', $pedidoId)->lockForUpdate()->first();
            abort_unless($pedido?->es_preparacion, 404);
            abort_unless($usuario->tienePermiso('preparaciones.gestionar') || $usuario->tienePermiso('ventas.todas')
                || ($usuario->tienePermiso('pos.usar') && (int) $pedido->usuario_id === $usuario->id), 403);
            $detalle = DB::table('detalle_pedidos')->where('id', $detalleId)->where('pedido_id', $pedidoId)->whereNotNull('preparacion')->lockForUpdate()->first();
            abort_unless($detalle, 404);
            if ($venta->estado !== 'completada') throw ValidationException::withMessages(['estado' => 'La venta está anulada. No se puede continuar la preparación.']);
            if ($detalle->estado_preparacion === $estado) return;
            $siguiente = ['pendiente' => 'en_preparacion', 'en_preparacion' => 'listo'];
            if (($siguiente[$detalle->estado_preparacion] ?? null) !== $estado) {
                throw ValidationException::withMessages(['estado' => 'La preparación cambió. Actualiza la pantalla y respeta el orden Pendiente → En preparación → Listo.']);
            }
            DB::table('detalle_pedidos')->where('id', $detalleId)->update(['estado_preparacion' => $estado, 'actualizada_por' => $usuario->id, 'updated_at' => now()]);
            $pendientes = DB::table('detalle_pedidos')->where('pedido_id', $pedidoId)->whereNotNull('preparacion')->where('estado_preparacion', '!=', 'listo')->exists();
            DB::table('pedidos')->where('id', $pedidoId)->update(['estado' => $pendientes ? 'en_preparacion' : 'completado', 'updated_at' => now()]);
        }, 3);
    }
}
