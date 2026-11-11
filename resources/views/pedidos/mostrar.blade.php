@extends('layouts.panel')
@section('titulo','Detalle de pedido')
@section('contenido')
<a class="btn btn-outline-secondary mb-4" href="{{ route('pedidos.index',['anteriores'=>1]) }}">Volver a pedidos anteriores</a>
<section class="card p-4"><h1>{{ $pedido->numero }}</h1><p>Estado: {{ str_replace('_',' ',$pedido->estado) }}</p><p>{{ $pedido->notas }}</p><div class="table-responsive"><table class="table"><thead><tr><th>Producto</th><th>Cantidad</th><th>Precio al pedir</th><th>Subtotal</th></tr></thead><tbody>@foreach($detalles as $d)<tr><td>{{ $d->nombre_producto }}</td><td>{{ \App\Support\Cantidad::mostrar($d->cantidad) }}</td><td>Bs. {{ \App\Support\Moneda::mostrar($d->precio_unitario) }}</td><td>Bs. {{ \App\Support\Moneda::mostrar($d->subtotal) }}</td></tr>@endforeach</tbody></table></div><h2>Total estimado: Bs. {{ \App\Support\Moneda::mostrar($pedido->total) }}</h2>
<div class="d-flex gap-3 flex-wrap mt-3">@if(in_array($pedido->estado,['pendiente','en_preparacion']))@if(auth()->user()->tienePermiso('pos.usar'))<a class="btn btn-cafe" href="{{ route('pos.index',['pedido'=>$pedido->id]) }}">Cobrar en POS</a>@endif
@foreach(['en_preparacion'=>'Preparar','cancelado'=>'Cancelar pedido'] as $estado=>$etiqueta)@if($estado!==$pedido->estado)<form method="POST" action="{{ route('pedidos.estado',$pedido->id) }}">@csrf<input type="hidden" name="estado" value="{{ $estado }}"><button class="btn btn-outline-secondary">{{ $etiqueta }}</button></form>@endif @endforeach
@endif @if($venta)<a class="btn btn-outline-secondary" href="{{ route('ventas.mostrar',$venta) }}">Ver venta</a>@endif</div></section>
@endsection
