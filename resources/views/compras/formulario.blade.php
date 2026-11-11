@extends('layouts.panel')
@section('titulo','Registrar compra')
@section('contenido')
<a class="d-inline-block mb-3" href="{{ route('compras.index') }}">← Volver a compras</a><h1 class="mb-2">Una nueva recepción</h1><p class="text-secondary mb-4">Agrega los productos recibidos. Para insumos con presentación puedes elegir envases: cantidad y costo corresponden a cada envase. En modo unidad de medida corresponden a ml, g, kg, L o unidades. El inventario siempre recibe el contenido total.</p>
@if($proveedores->isEmpty() || $productos->isEmpty())<div class="alert alert-warning">Necesitas al menos un proveedor activo y un producto activo. Puedes crearlos desde sus módulos.</div>@endif
<form method="POST" action="{{ route('compras.guardar') }}" data-compra data-confirmar="¿Registrar esta compra y aumentar el inventario?" data-accion="Registrar compra">@csrf<input name="clave_idempotencia" type="hidden" value="{{ old('clave_idempotencia',$clave) }}">
<div class="card p-4 mb-4"><div class="row g-3"><div class="col-md-8"><label for="proveedor_id" class="form-label">Proveedor</label><select class="form-select" id="proveedor_id" name="proveedor_id" required><option value="">Selecciona un proveedor</option>@foreach($proveedores as $proveedor)<option value="{{ $proveedor->id }}" @selected(old('proveedor_id')==$proveedor->id)>{{ $proveedor->nombre }}{{ $proveedor->empresa ? ' · '.$proveedor->empresa : '' }}</option>@endforeach</select></div><div class="col-md-4"><label for="fecha" class="form-label">Fecha de compra</label><input class="form-control" id="fecha" name="fecha" type="date" value="{{ old('fecha',now()->format('Y-m-d')) }}" max="{{ now()->format('Y-m-d') }}" required></div></div></div>
<div class="card p-4"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h4 mb-0">Artículos recibidos</h2><button class="btn btn-outline-secondary" type="button" data-agregar-linea>+ Agregar artículo</button></div>
<div class="table-responsive"><table class="table align-middle tabla-compra"><thead><tr><th>Producto</th><th>Cantidad</th><th>Costo unitario (Bs.)</th><th>Subtotal</th><th></th></tr></thead><tbody data-lineas-compra>
@foreach(old('detalles',[['producto_id'=>'','cantidad'=>1,'costo_unitario'=>0]]) as $indice=>$detalle)
@include('compras.linea',['indice'=>$indice,'detalle'=>$detalle])
@endforeach
</tbody></table></div>
<template data-plantilla-linea>@include('compras.linea',['indice'=>'INDICE','detalle'=>['producto_id'=>'','cantidad'=>1,'costo_unitario'=>0]])</template>
<div class="row g-4 mt-2"><div class="col-md-8"><label for="observaciones" class="form-label">Observaciones (opcional)</label><textarea class="form-control" id="observaciones" name="observaciones" rows="3" maxlength="3000">{{ old('observaciones') }}</textarea></div><div class="col-md-4"><div class="resumen-compra"><span>Total de la compra</span><strong data-total-compra>Bs. 0,00</strong><small>El total se verifica al guardar.</small></div></div></div>
<div class="d-flex gap-3 mt-4"><button class="btn btn-cafe" @disabled($proveedores->isEmpty() || $productos->isEmpty())>Registrar compra</button><a class="btn btn-outline-secondary" href="{{ route('compras.index') }}">Cancelar</a></div></div></form>
@endsection
