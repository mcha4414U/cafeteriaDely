@extends('layouts.panel')
@section('titulo','Preparaciones')
@section('contenido')
<h1>Preparaciones</h1>
<a class="btn btn-outline-secondary mb-4" href="{{ route('pedidos.index') }}">Volver a preparaciones</a>
<div class="row g-3">@foreach($preparaciones as $preparacion)<div class="col-12 col-md-6 col-xl-4">@include('pedidos.tarjeta-preparacion')</div>@endforeach</div>
@endsection
