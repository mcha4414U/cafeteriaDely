@php
    $recetaPreparacion = json_decode($preparacion->preparacion, true);
    $estadoPreparacion = $preparacion->estado_venta === 'anulada' ? 'cancelado' : $preparacion->estado_preparacion;
    $etiquetaPreparacion = ['pendiente'=>'Pendiente','en_preparacion'=>'En preparación','listo'=>'Listo','cancelado'=>'Cancelada'][$estadoPreparacion];
@endphp
<article class="card h-100 p-3">
<div class="d-flex justify-content-between gap-2"><strong>{{ $preparacion->numero_venta }}</strong><span class="badge bg-secondary">{{ $etiquetaPreparacion }}</span></div>
<small class="text-secondary">{{ $preparacion->fecha_venta }}</small>
<h2 class="h5 mt-3">{{ $preparacion->nombre_producto }}</h2>
<p>Cantidad: 1 · Unidad #{{ $preparacion->unidad_numero }}</p>
@if($recetaPreparacion['personalizado'])<span class="badge bg-primary align-self-start mb-2">PERSONALIZADO</span>@endif
<strong>Receta: {{ $recetaPreparacion['receta'] }}</strong>
@if($recetaPreparacion['descripcion'])<p>{{ $recetaPreparacion['descripcion'] }}</p>@endif
<ul class="list-unstyled my-3">
@foreach($recetaPreparacion['insumos'] as $insumoPreparacion)
<li>{{ $insumoPreparacion['nombre'] }}: <strong>{{ \App\Support\Cantidad::mostrar($insumoPreparacion['cantidad']) }} {{ $insumoPreparacion['unidad_medida'] }}</strong></li>
@endforeach
</ul>
@if($recetaPreparacion['nota'])<p><strong>Indicación:</strong> {{ $recetaPreparacion['nota'] }}</p>@endif
@if($preparacion->nota_venta)<p><strong>Nota de venta:</strong> {{ $preparacion->nota_venta }}</p>@endif
@if(in_array($estadoPreparacion,['pendiente','en_preparacion']))
<form class="mt-auto" method="POST" action="{{ route('pedidos.estado',$preparacion->pedido_id) }}">
@csrf
<input type="hidden" name="detalle_id" value="{{ $preparacion->id }}">
<input type="hidden" name="estado" value="{{ $estadoPreparacion === 'pendiente' ? 'en_preparacion' : 'listo' }}">
<button class="btn btn-cafe w-100">{{ $estadoPreparacion === 'pendiente' ? 'Iniciar preparación' : 'Marcar como listo' }}</button>
</form>
@endif
</article>
