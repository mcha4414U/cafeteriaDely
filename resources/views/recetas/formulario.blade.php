@extends('layouts.panel')
@section('titulo','Receta')
@section('contenido')
<a href="{{ route('productos.index') }}" class="d-inline-block mb-3">← Volver a productos</a>
<h1 class="mb-4">Receta: {{ $producto->nombre }}</h1>
<form method="POST" action="{{ route('recetas.guardar',$producto) }}" class="card p-4" id="formulario-receta">
@csrf @method('PUT')
<div class="row g-3 mb-4">
<div class="col-md-8"><label for="nombre" class="form-label">Nombre de receta</label><input id="nombre" name="nombre" class="form-control" maxlength="255" value="{{ old('nombre',$producto->receta?->nombre ?? $producto->nombre) }}" required></div>
<div class="col-md-4"><label for="estado" class="form-label">Estado</label><select id="estado" name="estado" class="form-select"><option value="1" @selected(old('estado',$producto->receta?->estado ?? true))>Activa</option><option value="0" @selected(!old('estado',$producto->receta?->estado ?? true))>Inactiva</option></select></div>
<div class="col-12"><label for="descripcion" class="form-label">Descripción</label><textarea id="descripcion" name="descripcion" class="form-control" maxlength="3000">{{ old('descripcion',$producto->receta?->descripcion) }}</textarea></div>
</div>
<p class="text-secondary">Usa la misma unidad que el stock del insumo. Por ejemplo, 200 ml de leche si registras su stock en ml. Las cantidades de receta consumen el contenido disponible, no envases: 150 ml descuentan 150 ml de una bolsa de 946 ml. No se realizan conversiones entre ml y L ni entre g y kg.</p>
@if($insumos->isEmpty())<div class="alert alert-warning">Registra primero un producto de tipo Insumo o Producto e insumo, activo y con unidad de medida.</div>@endif
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Insumo</th><th>Cantidad</th><th>Unidad</th><th>Acciones</th></tr></thead><tbody id="filas-receta"></tbody></table></div>
<div id="aviso-receta" class="text-danger mb-3" role="status"></div>
<div class="d-flex gap-3"><button type="button" class="btn btn-outline-secondary" id="agregar-insumo">+ Agregar insumo</button><button class="btn btn-cafe" @disabled($insumos->isEmpty())>Guardar receta</button></div>
</form>
@php
$datosInsumos = $insumos->map(fn($insumo)=>['id'=>$insumo->id,'nombre'=>$insumo->nombre,'unidad_medida'=>$insumo->unidad_medida])->values();
$detallesIniciales = old('detalles',$producto->receta?->detalles->map(fn($detalle)=>['insumo_id'=>$detalle->insumo_id,'cantidad'=>$detalle->cantidad,'unidad_medida'=>$detalle->unidad_medida])->toArray() ?? []);
@endphp
<script type="application/json" id="datos-receta">{!! json_encode(['insumos'=>$datosInsumos,'detalles'=>$detallesIniciales], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
<noscript><div class="alert alert-warning mt-3">Activa JavaScript para editar los ingredientes de la receta.</div></noscript>
@endsection
