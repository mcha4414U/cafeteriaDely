<?php

namespace App\Services;

use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioRecetas
{
    public function guardar(Producto $producto, array $datos): void
    {
        DB::transaction(function () use ($producto, $datos) {
            $ids = collect($datos['detalles'])->pluck('insumo_id')->push($producto->id)->unique()->sort()->values();
            $productos = Producto::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $producto = $productos->get($producto->id);
            if (! $producto || ! in_array($producto->tipo, ['venta', 'ambos'])) {
                throw ValidationException::withMessages(['nombre' => 'Solo los productos de venta o ambos pueden tener receta.']);
            }
            foreach ($datos['detalles'] as $indice => $detalle) {
                $insumo = $productos->get($detalle['insumo_id']);
                if (! $insumo || ! $insumo->estado || ! in_array($insumo->tipo, ['insumo', 'ambos']) || $insumo->id === $producto->id) {
                    throw ValidationException::withMessages(["detalles.$indice.insumo_id" => 'Selecciona un insumo activo distinto al producto preparado.']);
                }
                if (! $insumo->unidad_medida || $insumo->unidad_medida !== $detalle['unidad_medida']) {
                    throw ValidationException::withMessages(["detalles.$indice.unidad_medida" => 'La unidad debe coincidir exactamente con la unidad del stock del insumo.']);
                }
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('productos', 'requiere_receta')) {
                $producto->requiere_receta = true;
                $producto->save();
            }
            $receta = $producto->receta()->updateOrCreate([], ['nombre' => $datos['nombre'], 'descripcion' => $datos['descripcion'] ?? null, 'estado' => $datos['estado']]);
            $receta->detalles()->delete();
            $receta->detalles()->createMany($datos['detalles']);
            app(ServicioAlertas::class)->sincronizar($producto);
        }, 3);
    }
}
