<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecetaRequest;
use App\Models\Producto;
use App\Services\ServicioRecetas;

class RecetaController extends Controller
{
    public function editar(Producto $producto)
    {
        abort_unless(in_array($producto->tipo, ['venta', 'ambos']), 404);
        $producto->load('receta.detalles.insumo');
        $insumos = Producto::where('estado', true)->whereIn('tipo', ['insumo', 'ambos'])->where('id', '!=', $producto->id)->orderBy('nombre')->get();

        return view('recetas.formulario', compact('producto', 'insumos'));
    }

    public function guardar(RecetaRequest $solicitud, Producto $producto, ServicioRecetas $servicio)
    {
        $servicio->guardar($producto, $solicitud->validated());

        return redirect()->route('recetas.editar', $producto)->with('estado', 'Receta guardada correctamente.');
    }
}
