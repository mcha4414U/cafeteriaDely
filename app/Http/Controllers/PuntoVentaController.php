<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Producto;

class PuntoVentaController extends Controller
{
    public function index()
    {
        $productos = Producto::with(['categoria', 'receta.detalles.insumo'])->where('estado', true)->whereIn('tipo', ['venta', 'ambos'])->orderBy('nombre')->get();
        $categorias = Categoria::whereIn('id', $productos->pluck('categoria_id')->filter())->orderBy('nombre')->get();

        $clientes = \App\Models\Cliente::where('estado', true)->orderByDesc('es_generico')->orderBy('nombre')->get();
        $metodos = \Illuminate\Support\Facades\DB::table('metodos_pago')->where('estado', true)->get();
        $pedidoInicial=null;
        if(request()->filled('pedido')){
            request()->validate(['pedido'=>'integer']);
            $pedido=\Illuminate\Support\Facades\DB::table('pedidos')->find(request('pedido'));
            abort_unless($pedido && !$pedido->es_preparacion && in_array($pedido->estado,['pendiente','en_preparacion']),404);
            abort_unless(auth()->user()->tienePermiso('ventas.todas') || (int)$pedido->usuario_id===auth()->id(),403);
            $detalles=\Illuminate\Support\Facades\DB::table('detalle_pedidos')->where('pedido_id',$pedido->id)->get();
            abort_if($detalles->contains(fn($d)=>!$productos->contains('id',$d->producto_id)),422,'Un producto del pedido ya no está disponible.');
            $pedidoInicial=['id'=>$pedido->id,'cliente_id'=>$pedido->cliente_id,'notas'=>$pedido->notas,'detalles'=>$detalles->map(fn($d)=>['producto_id'=>$d->producto_id,'cantidad'=>(int)$d->cantidad])->all()];
        }
        $datosProductos = $productos->map(fn ($producto) => [
            'id' => $producto->id, 'nombre' => $producto->nombre, 'precio_venta' => $producto->precio_venta,
            'imagen' => $producto->imagen ? route('productos.imagen', $producto) : null,
            'requiere_receta' => $producto->requiereReceta(),
            'receta' => $producto->receta ? app(\App\Services\ServicioPreparaciones::class)->receta($producto->receta) : null,
        ])->values();
        return view('pos.indice', compact('productos', 'categorias', 'clientes', 'metodos','pedidoInicial', 'datosProductos'));
    }
}
