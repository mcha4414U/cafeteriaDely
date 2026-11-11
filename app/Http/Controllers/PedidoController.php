<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PedidoController extends Controller
{
    private function permitir(): void
    {
        abort_unless(auth()->user()->puedeVerPreparaciones(), 403);
    }

    private function pedido(int $id)
    {
        $this->permitir();
        $p = DB::table('pedidos')->find($id);
        abort_unless($p, 404);
        abort_unless(auth()->user()->tienePermiso('ventas.todas') || ($p->es_preparacion && auth()->user()->tienePermiso('preparaciones.gestionar')) || (auth()->user()->tienePermiso('pos.usar') && (int) $p->usuario_id === auth()->id()), 403);

        return $p;
    }

    private function anteriores()
    {
        $this->permitir();
        $q = DB::table('pedidos as p')->where('p.es_preparacion', false)->join('clientes as c', 'c.id', '=', 'p.cliente_id')->select('p.*', 'c.nombre as cliente');
        if (! auth()->user()->tienePermiso('ventas.todas')) {
            $q->where('p.usuario_id', auth()->id());
        }
        $pedidos = $q->orderByDesc('p.id')->paginate(25);

        return view('pedidos.anteriores', compact('pedidos'));
    }

    public function index(Request $r, \App\Services\ServicioPreparaciones $servicio)
    {
        $this->permitir();
        if ($r->boolean('anteriores')) {
            abort_unless($r->user()->tienePermiso('pos.usar') || $r->user()->tienePermiso('ventas.todas'), 403);
            return $this->anteriores();
        }
        $r->validate(['estado' => 'nullable|in:activas,pendiente,en_preparacion,listo,cancelado']);
        $estado = $r->input('estado') ?: 'activas';
        $q = $servicio->consulta($r->user());
        if ($estado === 'cancelado') {
            $q->where('v.estado', 'anulada');
        } else {
            $q->where('v.estado', 'completada');
            $estado === 'activas'
                ? $q->whereIn('d.estado_preparacion', ['pendiente', 'en_preparacion'])
                : $q->where('d.estado_preparacion', $estado);
        }
        $preparaciones = $q->orderBy('v.fecha')->orderBy('d.id')->paginate(24)->withQueryString();
        return view('pedidos.indice', compact('preparaciones', 'estado'));
    }

    public function crear()
    {
        abort_unless(auth()->user()->tienePermiso('pos.usar'), 403);
        return redirect()->route('pos.index')->with('estado', 'Las preparaciones se generan automáticamente al cobrar en Punto de venta.');
    }

    public function guardar(Request $r)
    {
        abort_unless($r->user()->tienePermiso('pos.usar'), 403);
        throw ValidationException::withMessages(['pedido' => 'Registra la venta desde Punto de venta. Las preparaciones se generan al cobrar.']);
    }
    public function mostrar(int $pedido)
    {
        $p = $this->pedido($pedido);
        if ($p->es_preparacion) {
            $preparaciones = app(\App\Services\ServicioPreparaciones::class)->consulta(auth()->user())->where('p.id', $pedido)->orderBy('d.id')->get();
            return view('pedidos.preparacion', compact('preparaciones'));
        }
        $detalles = DB::table('detalle_pedidos')->where('pedido_id', $pedido)->get();
        $venta = Venta::where('pedido_id', $pedido)->first();

        return view('pedidos.mostrar', ['pedido' => $p, 'detalles' => $detalles, 'venta' => $venta]);
    }

    public function estado(Request $r, int $pedido)
    {
        $p = $this->pedido($pedido);
        if ($p->es_preparacion) {
            $d = $r->validate(['detalle_id' => 'required|integer', 'estado' => 'required|in:en_preparacion,listo']);
            app(\App\Services\ServicioPreparaciones::class)->cambiarEstado($pedido, $d['detalle_id'], $d['estado'], $r->user());
            return back()->with('estado', 'Preparación actualizada.');
        }
        $d = $r->validate(['estado' => 'required|in:en_preparacion,cancelado']);
        DB::transaction(function () use ($pedido, $d) {
            $p = DB::table('pedidos')->where('id', $pedido)->lockForUpdate()->first();
            if (! in_array($p->estado, ['pendiente', 'en_preparacion']) || Venta::where('pedido_id', $pedido)->exists()) {
                throw ValidationException::withMessages(['estado' => 'Este pedido ya fue cerrado.']);
            }
            if ($d['estado'] === 'en_preparacion' && $p->estado !== 'pendiente') {
                return;
            }
            DB::table('pedidos')->where('id', $pedido)->update(['estado' => $d['estado'], 'updated_at' => now()]);
        });

        return back()->with('estado','Estado del pedido actualizado.');
    }
}
