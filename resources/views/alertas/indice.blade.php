@extends('layouts.panel')
@section('titulo','Alertas de inventario')
@section('contenido')
<h1>Alertas de inventario</h1><p class="text-secondary">Existencias bajas, productos agotados y fechas de vencimiento. Las alertas se resuelven cuando se corrige la condición.</p>
<div class="d-flex gap-2 mb-4"><a href="{{ route('alertas.index') }}" class="btn btn-outline-secondary">Activas</a><a href="{{ route('alertas.index',['estado'=>'resuelta']) }}" class="btn btn-outline-secondary">Resueltas</a><form method="POST" action="{{ route('alertas.actualizar') }}">@csrf<button class="btn btn-cafe">Revisar existencias</button></form></div>
@forelse($alertas as $a)<section class="card p-4 mb-3"><h2 class="h5">{{ str_replace('_',' ',$a->tipo_alerta) }}</h2><p>{{ $a->mensaje }}</p><p>Alerta: {{ $a->estado }}@if(config('services.whatsapp.enabled')) · WhatsApp: {{ $a->envio_estado }}@endif</p>@if(config('services.whatsapp.enabled') && $a->error_envio)<p class="text-danger">{{ $a->error_envio }}</p>@endif
@if(config('services.whatsapp.enabled') && $a->estado==='activa' && in_array($a->envio_estado,['error','incierto']))<form method="POST" action="{{ route('alertas.reintentar',$a->id) }}" data-confirmar="Revisa primero si llegó el aviso. ¿Volver a enviarlo?" data-accion="Reintentar">@csrf<button class="btn btn-outline-secondary">Reintentar WhatsApp</button></form>@endif</section>@empty<div class="alert alert-light">No hay alertas en este estado.</div>@endforelse
{{ $alertas->links() }}
@endsection
