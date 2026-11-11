<?php

namespace App\Http\Controllers;

use App\Http\Requests\VentaRequest;
use App\Models\Venta;
use App\Services\ServicioVentas;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    private function permitir(?Venta $venta = null): void
    {
        $u = auth()->user();
        $todas = $u->tienePermiso('ventas.todas');
        abort_unless($todas || $u->tienePermiso('ventas.propias') || $u->tienePermiso('pos.usar'), 403);
        if ($venta) {
            abort_unless($todas || $venta->usuario_id === $u->id, 403);
        }
    }

    public function index(Request $r)
    {
        $this->permitir();
        $r->validate(['desde' => 'nullable|date_format:Y-m-d', 'hasta' => ['nullable', 'date_format:Y-m-d', ...($r->filled('desde') ? ['after_or_equal:desde'] : [])], 'estado' => 'nullable|in:completada,anulada']);
        $q = Venta::query();
        if (! $r->user()->tienePermiso('ventas.todas')) {
            $q->where('usuario_id', $r->user()->id);
        }
        if ($r->filled('desde')) {
            $q->whereDate('fecha', '>=', $r->desde);
        }
        if ($r->filled('hasta')) {
            $q->whereDate('fecha', '<=', $r->hasta);
        }
        if ($r->filled('estado')) {
            $q->where('estado', $r->estado);
        }
        $ventas = $q->latest('id')->paginate(25)->withQueryString();

        return view('ventas.indice', compact('ventas'));
    }

    public function guardar(VentaRequest $r, ServicioVentas $s)
    {
        $v = $s->registrar($r->validated(), $r->user()->id);

        return response()->json(['url' => route('ventas.mostrar', $v), 'numero' => $v->numero]);
    }

    public function mostrar(Venta $venta)
    {
        $this->permitir($venta);
        $venta->load('detalles', 'usuario');

        return view('ventas.mostrar', compact('venta'));
    }

    public function anular(Request $r, Venta $venta, ServicioVentas $s)
    {
        $d = $r->validate(['motivo' => 'required|string|min:5|max:1000']);
        $s->anular($venta, $r->user()->id, $d['motivo']);

        return redirect()->route('ventas.mostrar',$venta)->with('estado','Venta anulada. Se restituyeron las cantidades originales al inventario.');
    }
}
