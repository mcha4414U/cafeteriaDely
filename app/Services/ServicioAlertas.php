<?php

namespace App\Services;

use App\Jobs\EnviarAlertaStock;
use App\Models\Producto;
use App\Support\Cantidad;
use Illuminate\Support\Facades\DB;

class ServicioAlertas
{
    public function sincronizar(Producto $producto): void
    {
        DB::transaction(function () use ($producto) {
            $p = Producto::withTrashed()->whereKey($producto->id)->lockForUpdate()->first();
            if (! $p) {
                return;
            }
            $tipos = [];
            if (! $p->trashed() && $p->estado) {
                // Una bebida preparada se controla por sus insumos, no por existencias terminadas.
                if (! $p->requiereReceta()) {
                    if (bccomp($p->stock_actual, '0', 3) <= 0) {
                        $tipos[] = 'agotado';
                    } elseif (bccomp($p->stock_actual, $p->stock_minimo, 3) <= 0) {
                        $tipos[] = 'stock_bajo';
                    }
                }
                if ($p->fecha_vencimiento && $p->fecha_vencimiento->lte(today()->addDays(7))) {
                    $tipos[] = 'proximo_vencimiento';
                }
            }
            DB::table('alertas_stock')->where('producto_id', $p->id)->where('estado', 'activa')->whereNotIn('tipo_alerta', $tipos)->update(['estado' => 'resuelta', 'clave_activa' => null, 'updated_at' => now()]);
            foreach ($tipos as $tipo) {
                $mensaje = $tipo === 'proximo_vencimiento'
                    ? 'Cafetería Dely: revisar vencimiento de '.$p->nombre.' ('.$p->fecha_vencimiento->format('d/m/Y').').'
                    : 'Cafetería Dely: stock bajo'.($tipo === 'agotado' ? ' / agotado' : '').'. '.$p->nombre.': '.Cantidad::mostrar($p->stock_actual).' '.$p->unidad_medida.'. Mínimo: '.Cantidad::mostrar($p->stock_minimo).'.';
                $activa = DB::table('alertas_stock')->where('clave_activa', $p->id.':'.$tipo)->first();
                if ($activa) {
                    DB::table('alertas_stock')->where('id', $activa->id)->update(['mensaje' => $mensaje, 'updated_at' => now()]);

                    continue;
                }
                $id = DB::table('alertas_stock')->insertGetId(['producto_id' => $p->id, 'tipo_alerta' => $tipo, 'mensaje' => $mensaje, 'estado' => 'activa', 'clave_activa' => $p->id.':'.$tipo, 'enviada' => false, 'envio_estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now()]);
                if (config('services.whatsapp.enabled', false)) {
                    EnviarAlertaStock::dispatch($id)->onConnection('database')->onQueue('whatsapp')->afterCommit();
                }
            }
        }, 3);
    }

    public function operativas(): \Illuminate\Database\Query\Builder
    {
        return DB::table('alertas_stock as a')->join('productos as p', 'p.id', '=', 'a.producto_id')
            ->where('a.estado', 'activa')->whereIn('a.tipo_alerta', ['stock_bajo', 'agotado'])
            ->whereNull('p.deleted_at')->where('p.estado', true)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('recetas')->whereColumn('recetas.producto_id', 'p.id'))
            ->when(\Illuminate\Support\Facades\Schema::hasColumn('productos', 'requiere_receta'),
                fn ($q) => $q->where(fn ($q) => $q->whereNull('p.requiere_receta')->orWhere('p.requiere_receta', false)))
            ->select('a.id', 'a.tipo_alerta', 'p.nombre', 'p.stock_actual', 'p.stock_minimo', 'p.unidad_medida')
            ->orderByDesc('a.id');
    }

    public function reavisar(int $alertaId, int $usuarioId): array
    {
        return DB::transaction(function () use ($alertaId, $usuarioId) {
            $productoId = DB::table('alertas_stock')->where('id', $alertaId)->value('producto_id');
            abort_unless($productoId, 404);
            // Igual orden de bloqueos que sincronizar: producto, después alerta.
            Producto::whereKey($productoId)->lockForUpdate()->firstOrFail();
            abort_unless($this->operativas()->where('a.id', $alertaId)->exists(), 404);
            $alerta = DB::table('alertas_stock')->where('id', $alertaId)->lockForUpdate()->first();

            if (! config('services.whatsapp.enabled', false)
                || ! filled(config('services.whatsapp.token'))
                || ! filled(config('services.whatsapp.phone_number_id'))
                || ! filled(config('services.whatsapp.destinatario'))) {
                return ['estado' => 503, 'mensaje' => 'El envío al administrador no está habilitado. La alerta sigue disponible en el sistema.'];
            }
            $fechas = array_filter([$alerta->ultimo_aviso_manual_at, $alerta->intento_at, $alerta->fecha_envio, $alerta->created_at]);
            $ultimo = max(array_map(fn ($fecha) => \Illuminate\Support\Carbon::parse($fecha)->timestamp, $fechas));
            if ($alerta->envio_estado === 'procesando' || $ultimo > now()->subMinutes(10)->timestamp) {
                return ['estado' => 429, 'mensaje' => 'El administrador ya fue avisado recientemente o hay un aviso pendiente. Espera 10 minutos antes de solicitar otro.'];
            }

            DB::table('alertas_stock')->where('id', $alertaId)->update([
                'ultimo_aviso_manual_at' => now(), 'ultimo_aviso_manual_por' => $usuarioId,
                'envio_estado' => 'pendiente', 'error_envio' => null, 'mensaje_meta_id' => null,
                'meta_evento_at' => null, 'enviada' => false, 'fecha_envio' => null, 'updated_at' => now(),
            ]);
            EnviarAlertaStock::dispatch($alertaId)->onConnection('database')->onQueue('whatsapp')->afterCommit();

            return ['estado' => 202, 'mensaje' => 'Aviso al administrador encolado. La entrega todavía no está confirmada.'];
        }, 3);
    }

    public function actualizarTodas(): void
    {
        Producto::withTrashed()->orderBy('id')->chunkById(100, function ($productos) {
            foreach ($productos as $p) {
                $this->sincronizar($p);
            }
        });
    }
}
