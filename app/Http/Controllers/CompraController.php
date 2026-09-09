<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompraRequest;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Services\ServicioCompras;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CompraController extends Controller
{
    public function index(Request $solicitud)
    {
        $solicitud->validate(['desde' => 'nullable|date_format:Y-m-d', 'hasta' => ['nullable', 'date_format:Y-m-d', ...($solicitud->filled('desde') ? ['after_or_equal:desde'] : [])], 'proveedor_id' => 'nullable|integer|exists:proveedores,id']);
        $consulta = Compra::with(['proveedor', 'usuario']);
        if ($solicitud->filled('desde')) {
            $consulta->where('fecha', '>=', $solicitud->desde.' 00:00:00');
        }
        if ($solicitud->filled('hasta')) {
            $consulta->where('fecha', '<=', $solicitud->hasta.' 23:59:59');
        }
        if ($solicitud->filled('proveedor_id')) {
            $consulta->where('proveedor_id', $solicitud->proveedor_id);
        }

        return view('compras.indice', ['compras' => $consulta->latest('fecha')->get(), 'proveedores' => Proveedor::withTrashed()->orderBy('nombre')->get()]);
    }

    public function crear()
    {
        return view('compras.formulario', ['productos' => Producto::where('estado', true)->orderBy('nombre')->get(),
            'proveedores' => Proveedor::where('estado', true)->orderBy('nombre')->get(), 'clave' => (string) Str::uuid()]);
    }

    public function guardar(CompraRequest $solicitud, ServicioCompras $servicio)
    {
        $compra = $servicio->registrar($solicitud->validated(), $solicitud->user()->id);

        return redirect()->route('compras.mostrar', $compra)->with('estado', 'Compra registrada. El inventario está actualizado.');
    }

    public function mostrar(Compra $compra)
    {
        $compra->load(['proveedor', 'usuario', 'detalles.producto']);

        return view('compras.mostrar', compact('compra'));
    }
}
