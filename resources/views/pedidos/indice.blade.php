@extends('layouts.panel')
@section('titulo','Preparaciones')
@section('contenido')
<div class="d-flex flex-wrap justify-content-between gap-2 mb-4"><h1>Preparaciones</h1><div>
@if(auth()->user()->tienePermiso('pos.usar') || auth()->user()->tienePermiso('ventas.todas'))
<a class="btn btn-outline-secondary" href="{{ route('pedidos.index',['anteriores'=>1]) }}">Pedidos anteriores</a>
@endif
<a class="btn btn-cafe" href="{{ request()->fullUrl() }}">Actualizar</a></div></div>
<div class="d-flex flex-wrap gap-2 mb-4">
@foreach(['activas'=>'Activas','pendiente'=>'Pendientes','en_preparacion'=>'En preparación','listo'=>'Listos','cancelado'=>'Canceladas'] as $valor=>$etiqueta)
<a class="btn {{ $estado === $valor ? 'btn-cafe' : 'btn-outline-secondary' }}" href="{{ route('pedidos.index',['estado'=>$valor]) }}">{{ $etiqueta }}</a>
@endforeach
</div>
<p class="text-secondary">Una tarjeta por unidad. Las preparaciones más antiguas aparecen primero.</p>
<div class="row g-3">
@forelse($preparaciones as $preparacion)
<div class="col-12 col-md-6 col-xl-4">@include('pedidos.tarjeta-preparacion')</div>
@empty
<div class="col-12"><div class="card p-4">
<h2 class="h5">No hay preparaciones en este estado.</h2>
<p class="mb-2">Las preparaciones aparecen después de cobrar en el Punto de venta un producto con una receta activa. Las ventas realizadas antes de registrar la receta no generan preparaciones retroactivamente.</p>
<p>Agrega el producto a la orden. Si tiene receta, verás el botón «Receta» junto a su cantidad; puedes personalizarlo y después cobrar. Aquí aparecerán los botones «Iniciar preparación» y «Marcar como listo».</p>
@if(auth()->user()->tienePermiso('pos.usar'))
<div><a class="btn btn-cafe" href="{{ route('pos.index') }}">Ir al punto de venta</a></div>
@else
<p class="text-secondary mb-0">Cuando caja registre una venta con receta, pulsa Actualizar para verla.</p>
@endif
</div></div>
@endforelse
</div>
<div class="mt-4">{{ $preparaciones->links() }}</div>
@endsection
