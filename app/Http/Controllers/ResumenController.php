<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResumenController extends Controller
{
    public function inicio()
    {
        $q = Venta::whereDate('fecha', today())->where('estado', 'completada');
        if (! auth()->user()->tienePermiso('ventas.todas')) {
            $q->where('usuario_id', auth()->id());
        }
        $total = (clone $q)->sum('total');
        $cantidad = (clone $q)->count();
        $recientes = (clone $q)->latest('id')->limit(5)->get();
        $alertas = auth()->user()->tienePermiso('alertas.gestionar') ? DB::table('alertas_stock')->where('estado', 'activa')->count() : null;

        return view('inicio', compact('total', 'cantidad', 'recientes', 'alertas'));
    }

    public function reportes(Request $r)
    {
        $r->merge([
            'desde' => $r->input('desde') ?: today()->startOfMonth()->toDateString(),
            'hasta' => $r->input('hasta') ?: today()->toDateString(),
        ]);
        $r->validate(['desde' => 'required|date_format:Y-m-d', 'hasta' => 'required|date_format:Y-m-d|after_or_equal:desde']);
        $desde = $r->input('desde', today()->startOfMonth()->toDateString());
        $hasta = $r->input('hasta', today()->toDateString());
        $q = Venta::whereDate('fecha', '>=', $desde)->whereDate('fecha', '<=', $hasta)->where('estado', 'completada');
        $total = (clone $q)->sum('total');
        $cantidad = (clone $q)->count();
        $descuentos = (clone $q)->sum('descuento');
        $pagos = (clone $q)->select('nombre_metodo_pago')->selectRaw('COUNT(*) as cantidad, SUM(total) as total')->groupBy('nombre_metodo_pago')->get();
        $dias = (clone $q)->selectRaw('DATE(fecha) as dia, COUNT(*) as cantidad, SUM(total) as total')->groupByRaw('DATE(fecha)')->orderBy('dia')->get();
        $productos = DB::table('detalle_ventas as d')->join('ventas as v', 'v.id', '=', 'd.venta_id')->where('v.estado', 'completada')->whereDate('v.fecha', '>=', $desde)->whereDate('v.fecha', '<=', $hasta)->select('d.nombre_producto')->selectRaw('SUM(d.cantidad) as cantidad, SUM(d.subtotal) as total')->groupBy('d.nombre_producto')->orderByDesc('cantidad')->limit(20)->get();
        $compras = DB::table('compras')->where('estado', 'registrada')->whereDate('fecha', '>=', $desde)->whereDate('fecha', '<=', $hasta)->sum('total');

        return view('reportes.indice', compact('desde','hasta','total','cantidad','descuentos','pagos','dias','productos','compras'));
    }
}
