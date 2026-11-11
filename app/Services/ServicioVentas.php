<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Usuario;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioVentas
{
    private function error(string $mensaje): never
    {
        throw ValidationException::withMessages(['venta' => $mensaje]);
    }

    public function registrar(array $datos, int $usuarioId): Venta
    {
        $lineas = collect($datos['detalles'])->sortBy('producto_id')->map(fn ($l) => ['producto_id' => (int) $l['producto_id'], 'cantidad' => (int) $l['cantidad'], 'precio_esperado' => bcadd((string) $l['precio_esperado'], '0', 2), ...(! empty($l['indicaciones']) ? ['indicaciones' => ServicioPreparaciones::normalizarIndicaciones($l['indicaciones'])] : [])])->values()->all();
        $huella = hash('sha256', json_encode([(int) $datos['cliente_id'], (int) $datos['metodo_pago_id'], $datos['pedido_id'] ?? null, bcadd((string) $datos['descuento'], '0', 2), isset($datos['recibido']) ? bcadd((string) $datos['recibido'], '0', 2) : null, $datos['notas'] ?? null, $lineas], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($datos, $usuarioId, $lineas, $huella) {
            Usuario::whereKey($usuarioId)->lockForUpdate()->firstOrFail();
            $existente = Venta::where('clave_idempotencia', $datos['clave_idempotencia'])->first();
            if ($existente) {
                if ($existente->usuario_id !== $usuarioId || $existente->huella_solicitud !== $huella) {
                    $this->error('Este cobro ya se utilizó para una operación diferente. Abre una orden nueva.');
                }

                return $existente;
            }
            $cliente = Cliente::whereKey($datos['cliente_id'])->where('estado', true)->lockForUpdate()->first();
            $pago = DB::table('metodos_pago')->where('id', $datos['metodo_pago_id'])->where('estado', true)->lockForUpdate()->first();
            if (! $cliente || ! $pago) {
                $this->error('Selecciona un cliente y método de pago activos.');
            }
            $pedido = null;
            if (! empty($datos['pedido_id'])) {
                $pedido = DB::table('pedidos')->where('id', $datos['pedido_id'])->lockForUpdate()->first();
                if (! $pedido || $pedido->es_preparacion || ! in_array($pedido->estado, ['pendiente', 'en_preparacion']) || Venta::where('pedido_id', $pedido->id)->exists()) {
                    $this->error('El pedido ya fue cobrado o cancelado.');
                }
                if (! Usuario::findOrFail($usuarioId)->tienePermiso('ventas.todas') && (int) $pedido->usuario_id !== $usuarioId) {
                    $this->error('No puedes cobrar pedidos de otro usuario.');
                }
                if ((int) $pedido->cliente_id !== $cliente->id) {
                    $this->error('El cliente no coincide con el pedido.');
                }
                $original = DB::table('detalle_pedidos')->where('pedido_id', $pedido->id)->orderBy('producto_id')->get()->map(fn ($l) => [(int) $l->producto_id, bcadd((string) $l->cantidad, '0', 3)])->all();
                if ($original !== array_map(fn ($l) => [$l['producto_id'], bcadd((string) $l['cantidad'], '0', 3)], $lineas)) {
                    $this->error('Los productos no coinciden con el pedido.');
                }
            }
            // Mismo orden de bloqueo para ventas, recetas y anulaciones; también protege insumos compartidos.
            $productos = Producto::withTrashed()->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $total = '0.00';
            $consumos = [];
            $preparadas = [];
            $paraBarista = [];
            foreach ($lineas as $l) {
                $p = $productos->get($l['producto_id']);
                if (! $p || $p->trashed() || ! $p->estado || ! in_array($p->tipo, ['venta', 'ambos'])) {
                    $this->error('Un producto ya no está disponible.');
                }
                if (bccomp($p->precio_venta, $l['precio_esperado'], 2) !== 0) {
                    $this->error('Cambió el precio de '.$p->nombre.'. Recarga el catálogo antes de cobrar.');
                }
                $receta = $p->receta()->with('detalles.insumo')->first();
                if ($p->requiereReceta() && ! $receta) {
                    $this->error('Falta registrar la receta de '.$p->nombre.'. Configúrala antes de vender este producto.');
                }
                if ($receta) {
                    if (! $receta->estado || $receta->detalles->isEmpty()) {
                        $this->error('La receta de '.$p->nombre.' está inactiva o vacía.');
                    }
                    if (count($paraBarista) + $l['cantidad'] > 200) {
                        $this->error('Una venta admite hasta 200 unidades que requieren preparación.');
                    }
                    $paraBarista = array_merge($paraBarista, app(ServicioPreparaciones::class)->unidades($p, $receta, $l));
                    foreach ($receta->detalles as $d) {
                        $insumo = $productos->get($d->insumo_id);
                        if (! $insumo || $insumo->trashed() || ! $insumo->estado || ! in_array($insumo->tipo, ['insumo', 'ambos']) || $insumo->unidad_medida !== $d->unidad_medida || bccomp($d->cantidad, '0', 3) <= 0) {
                            $this->error('Revisa los insumos de '.$p->nombre.'.');
                        }
                        $consumos[$insumo->id] = bcadd($consumos[$insumo->id] ?? '0', bcmul($d->cantidad, (string) $l['cantidad'], 3), 3);
                    }
                } else {
                    if (! empty($l['indicaciones'])) $this->error('Un producto sin receta no admite indicaciones de receta.');
                    $consumos[$p->id] = bcadd($consumos[$p->id] ?? '0', (string) $l['cantidad'], 3);
                }
                $subtotal = bcmul($p->precio_venta, (string) $l['cantidad'], 2);
                $total = bcadd($total, $subtotal, 2);
                if (bccomp($total, '9999999999.99', 2) > 0) {
                    $this->error('El total excede el límite permitido.');
                }
                $preparadas[] = ['producto_id' => $p->id, 'nombre_producto' => $p->nombre, 'cantidad' => $l['cantidad'], 'precio_unitario' => $p->precio_venta, 'subtotal' => $subtotal];
            }
            foreach ($consumos as $id => $cantidad) {
                $p = $productos[$id];
                if ($p->fecha_vencimiento && $p->fecha_vencimiento->isBefore(today())) {
                    $this->error('El producto o insumo '.$p->nombre.' está vencido.');
                }
                if (bccomp($p->stock_actual, $cantidad, 3) < 0) {
                    $this->error('Stock insuficiente: '.$p->nombre.'. Disponible: '.\App\Support\Cantidad::mostrar($p->stock_actual).' '.$p->unidad_medida.'.');
                }
            }
            if (bccomp((string) $datos['descuento'], $total, 2) > 0) {
                $this->error('El descuento no puede superar el subtotal.');
            }
            $neto = bcsub($total, (string) $datos['descuento'], 2);
            $efectivo = mb_strtolower($pago->nombre) === 'efectivo';
            $recibido = $efectivo ? ($datos['recibido'] ?? null) : $neto;
            if ($recibido === null || bccomp((string) $recibido, $neto, 2) < 0) {
                $this->error('El importe recibido no cubre el total.');
            }
            $venta = Venta::create(['cliente_id' => $cliente->id, 'usuario_id' => $usuarioId, 'metodo_pago_id' => $pago->id, 'pedido_id' => $pedido?->id,
                'numero' => 'TEMP-'.$datos['clave_idempotencia'], 'clave_idempotencia' => $datos['clave_idempotencia'], 'huella_solicitud' => $huella,
                'fecha' => now(), 'subtotal' => $total, 'descuento' => $datos['descuento'], 'total' => $neto, 'estado' => 'completada',
                'nombre_cliente' => $cliente->nombre, 'nombre_metodo_pago' => $pago->nombre, 'recibido' => $recibido, 'cambio' => bcsub((string) $recibido, $neto, 2), 'notas' => $datos['notas'] ?? null]);
            $venta->update(['numero' => 'V-'.str_pad((string) $venta->id, 6, '0', STR_PAD_LEFT)]);
            $venta->detalles()->createMany($preparadas);
            foreach ($consumos as $id => $cantidad) {
                $p = $productos[$id];
                $nuevo = bcsub($p->stock_actual, $cantidad, 3);
                MovimientoInventario::create(['producto_id' => $id, 'usuario_id' => $usuarioId, 'venta_id' => $venta->id, 'tipo' => 'salida_venta', 'cantidad' => $cantidad, 'stock_anterior' => $p->stock_actual, 'stock_nuevo' => $nuevo, 'motivo' => 'Venta '.$venta->numero, 'fecha' => now()]);
                $p->update(['stock_actual' => $nuevo]);
                app(ServicioAlertas::class)->sincronizar($p);
            }
            if ($pedido) {
                DB::table('pedidos')->where('id', $pedido->id)->update(['estado' => 'completado', 'updated_at' => now()]);
            }

            app(ServicioPreparaciones::class)->registrar($venta, $paraBarista);

            return $venta;
        }, 3);
    }

    public function anular(Venta $venta, int $usuarioId, string $motivo): void
    {
        DB::transaction(function () use ($venta, $usuarioId, $motivo) {
            $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
            if ($venta->estado === 'anulada') {
                return;
            }
            $salidas = MovimientoInventario::where('venta_id', $venta->id)->where('tipo', 'salida_venta')->orderBy('producto_id')->get();
            $productos = Producto::withTrashed()->whereIn('id', $salidas->pluck('producto_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($salidas as $m) {
                $p = $productos[$m->producto_id];
                $nuevo = bcadd($p->stock_actual, $m->cantidad, 3);
                if (bccomp($nuevo, '999999999.999', 3) > 0) {
                    $this->error('La devolución supera el stock máximo.');
                }
                MovimientoInventario::create(['producto_id' => $p->id, 'usuario_id' => $usuarioId, 'venta_id' => $venta->id, 'tipo' => 'ajuste', 'cantidad' => $m->cantidad, 'stock_anterior' => $p->stock_actual, 'stock_nuevo' => $nuevo, 'motivo' => 'Anulación '.$venta->numero.': '.mb_substr($motivo, 0, 180), 'fecha' => now()]);
                $p->update(['stock_actual' => $nuevo]);
                app(ServicioAlertas::class)->sincronizar($p);
            }
            $venta->update(['estado' => 'anulada', 'anulada_por' => $usuarioId, 'anulada_at' => now(), 'motivo_anulacion' => $motivo]);
            if ($venta->pedido_id) {
                DB::table('pedidos')->where('id',$venta->pedido_id)->update(['estado' => 'cancelado', 'updated_at' => now()]);
                DB::table('detalle_pedidos')->where('pedido_id', $venta->pedido_id)->whereIn('estado_preparacion', ['pendiente', 'en_preparacion'])->update(['estado_preparacion' => 'cancelado', 'updated_at' => now()]);
            }
        }, 3);
    }
}
