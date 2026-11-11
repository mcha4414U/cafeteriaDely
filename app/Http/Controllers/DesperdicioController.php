<?php

namespace App\Http\Controllers;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Usuario;
use App\Services\ServicioAlertas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DesperdicioController extends Controller
{
    public function index()
    {
        $productos = Producto::where('estado', true)->whereDoesntHave('receta')->orderBy('nombre')->get();
        $desperdicios = DB::table('desperdicios as d')->join('productos as p', 'p.id', '=', 'd.producto_id')->select('d.*', 'p.nombre', 'p.unidad_medida')->orderByDesc('d.id')->paginate(25);

        return view('desperdicios.indice', compact('productos', 'desperdicios'));
    }

    public function guardar(Request $r)
    {
        $d = $r->validate(['clave' => 'required|uuid', 'producto_id' => 'required|integer', 'cantidad' => 'required|numeric|min:0.001|max:999999999.999|decimal:0,3', 'motivo' => 'required|in:vencido,danado,preparado_incorrectamente,desperdiciado,otro', 'observacion' => 'required|string|min:5|max:1000']);
        DB::transaction(function () use ($d, $r) {
            Usuario::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            $huella = hash('sha256', json_encode($d, JSON_THROW_ON_ERROR));
            $existente = DB::table('desperdicios')->where('clave_idempotencia', $d['clave'])->first();
            if ($existente) {
                if ((int) $existente->usuario_id !== $r->user()->id || $existente->huella_solicitud !== $huella) {
                    throw ValidationException::withMessages(['cantidad' => 'Este formulario ya fue utilizado.']);
                }

                return;
            }
            $p = Producto::whereKey($d['producto_id'])->where('estado', true)->lockForUpdate()->firstOrFail();
            if ($p->receta()->exists()) {
                throw ValidationException::withMessages(['producto_id' => 'Registra el desperdicio sobre los insumos utilizados.']);
            }
            if (bccomp($p->stock_actual, (string) $d['cantidad'], 3) < 0) {
                throw ValidationException::withMessages(['cantidad' => 'La cantidad supera las existencias.']);
            }
            $id = DB::table('desperdicios')->insertGetId(['clave_idempotencia' => $d['clave'], 'huella_solicitud' => $huella, 'producto_id' => $p->id, 'usuario_id' => $r->user()->id, 'cantidad' => $d['cantidad'], 'motivo' => $d['motivo'], 'observacion' => $d['observacion'], 'fecha' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $nuevo = bcsub($p->stock_actual, (string) $d['cantidad'], 3);
            MovimientoInventario::create(['producto_id' => $p->id, 'usuario_id' => $r->user()->id, 'desperdicio_id' => $id, 'tipo' => 'salida_desperdicio', 'cantidad' => $d['cantidad'], 'stock_anterior' => $p->stock_actual, 'stock_nuevo' => $nuevo, 'motivo' => 'Desperdicio: '.$d['motivo'], 'fecha' => now()]);
            $p->update(['stock_actual' => $nuevo]);
            app(ServicioAlertas::class)->sincronizar($p);
        }, 3);

        return back()->with('estado','Desperdicio registrado e inventario actualizado.');
    }
}
