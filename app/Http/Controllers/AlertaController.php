<?php

namespace App\Http\Controllers;

use App\Jobs\EnviarAlertaStock;
use App\Services\ServicioAlertas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AlertaController extends Controller
{
    public function notificaciones(Request $r, ServicioAlertas $s)
    {
        abort_unless($r->user()?->puedeConsultarAvisosStock(), 403);
        $avisosStock = $s->operativas()->get();
        return response()->json([
            'cantidad' => $avisosStock->count(),
            'html' => view('partials.lista-avisos-stock', compact('avisosStock'))->render(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function avisarAdministrador(Request $r, int $alerta, ServicioAlertas $s)
    {
        abort_unless($r->user()?->puedeConsultarAvisosStock(), 403);
        $resultado = $s->reavisar($alerta, $r->user()->id);
        if ($r->expectsJson()) {
            return response()->json(['mensaje' => $resultado['mensaje']], $resultado['estado']);
        }
        return $resultado['estado'] < 400
            ? back()->with('estado', $resultado['mensaje'])
            : back()->withErrors(['aviso' => $resultado['mensaje']]);
    }

    public function index(Request $r)
    {
        $r->validate(['estado' => 'nullable|in:activa,resuelta']);
        $alertas = DB::table('alertas_stock')->where('estado', ($r->input('estado') ?: 'activa'))->latest('id')->paginate(25)->withQueryString();

        return view('alertas.indice', compact('alertas'));
    }

    public function actualizar(ServicioAlertas $s)
    {
        $s->actualizarTodas();

        return back()->with('estado', 'Alertas internas actualizadas.');
    }

    public function reintentar(int $alerta)
    {
        abort_unless(config('services.whatsapp.enabled', false), 403);
        $n = DB::table('alertas_stock')->where('id', $alerta)->where('estado', 'activa')->whereIn('envio_estado', ['error', 'incierto'])->update(['envio_estado' => 'pendiente', 'error_envio' => null, 'updated_at' => now()]);
        if ($n) {
            EnviarAlertaStock::dispatch($alerta)->onConnection('database')->onQueue('whatsapp')->afterCommit();
        }

        return back()->with('estado', $n ? 'Aviso encolado.' : 'El aviso no necesita un nuevo envío.');
    }
}
