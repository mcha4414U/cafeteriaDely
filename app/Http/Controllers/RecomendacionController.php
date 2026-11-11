<?php

namespace App\Http\Controllers;

use App\Models\Producto;

class RecomendacionController extends Controller
{
    public function index()
    {
        $productos = Producto::where('estado', true)->whereDoesntHave('receta')->when(\Illuminate\Support\Facades\Schema::hasColumn('productos', 'requiere_receta'),
            fn ($q) => $q->where(fn ($q) => $q->whereNull('requiere_receta')->orWhere('requiere_receta', false)))->whereColumn('stock_actual', '<=', 'stock_minimo')->orderBy('nombre')->get();
        $recomendaciones = $productos->map(function ($p) {
            $objetivo = bccomp($p->stock_minimo, '0', 3) > 0 ? bcmul($p->stock_minimo, '2', 3) : '1.000';

            return ['producto' => $p, 'cantidad' => bcsub($objetivo, $p->stock_actual, 3)];
        });

        return view('recomendaciones.indice', compact('recomendaciones'));
    }
}
