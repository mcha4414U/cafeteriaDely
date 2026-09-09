<?php

namespace App\Http\Controllers;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventarioController extends Controller
{
    public function index(Request $solicitud)
    {
        $solicitud->validate(['stock' => 'nullable|in:normal,bajo,agotado', 'estado' => 'nullable|in:vigente,archivado']);
        $consulta = Producto::withTrashed()->with('categoria')
            ->withSum(['movimientos as entradas' => fn ($consulta) => $consulta->whereColumn('stock_nuevo', '>', 'stock_anterior')], DB::raw('stock_nuevo - stock_anterior'))
            ->withSum(['movimientos as salidas' => fn ($consulta) => $consulta->whereColumn('stock_nuevo', '<', 'stock_anterior')], DB::raw('stock_anterior - stock_nuevo'));
        if ($solicitud->stock === 'normal') {
            $consulta->whereColumn('stock_actual', '>', 'stock_minimo');
        }
        if ($solicitud->stock === 'bajo') {
            $consulta->where('stock_actual', '>', 0)->whereColumn('stock_actual', '<=', 'stock_minimo');
        }
        if ($solicitud->stock === 'agotado') {
            $consulta->where('stock_actual', '<=', 0);
        }
        if ($solicitud->estado === 'vigente') {
            $consulta->whereNull('deleted_at');
        }
        if ($solicitud->estado === 'archivado') {
            $consulta->onlyTrashed();
        }

        return view('inventario.indice', ['productos' => $consulta->orderBy('nombre')->get()]);
    }

    public function movimientos(Request $solicitud)
    {
        $solicitud->validate(['producto_id' => 'nullable|integer|exists:productos,id', 'compra_id' => 'nullable|integer|exists:compras,id', 'tipo' => 'nullable|in:entrada_compra,salida_venta,salida_desperdicio,ajuste', 'desde' => 'nullable|date_format:Y-m-d', 'hasta' => ['nullable', 'date_format:Y-m-d', ...($solicitud->filled('desde') ? ['after_or_equal:desde'] : [])]]);
        $consulta = MovimientoInventario::with(['producto', 'usuario', 'compra']);
        foreach (['producto_id', 'tipo', 'compra_id'] as $campo) {
            if ($solicitud->filled($campo)) {
                $consulta->where($campo, $solicitud->$campo);
            }
        }
        if ($solicitud->filled('desde')) {
            $consulta->where('fecha', '>=', $solicitud->desde.' 00:00:00');
        }
        if ($solicitud->filled('hasta')) {
            $consulta->where('fecha', '<=', $solicitud->hasta.' 23:59:59');
        }

        return view('inventario.movimientos', ['movimientos' => $consulta->latest('fecha')->latest('id')->get(), 'productos' => Producto::withTrashed()->orderBy('nombre')->get()]);
    }
}
