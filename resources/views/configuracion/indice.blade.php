@extends('layouts.panel')
@section('titulo','Configuración')
@section('contenido')
<h1>Configuración</h1><form method="POST" action="{{ route('configuracion.guardar') }}" class="card p-4 mb-4">@csrf
<label for="nombre" class="form-label">Nombre del negocio en comprobantes</label><input id="nombre" class="form-control mb-3" name="nombre" value="{{ old('nombre',$nombre) }}" maxlength="100" required>
<fieldset><legend class="h5">Métodos de pago habilitados</legend>@foreach($metodos as $m)<label class="form-check"><input class="form-check-input" type="checkbox" name="metodos[]" value="{{ $m->id }}" @checked(in_array($m->id,old('metodos',$metodos->where('estado',true)->pluck('id')->all())))>{{ $m->nombre }}</label>@endforeach</fieldset><button class="btn btn-cafe align-self-start mt-3">Guardar</button></form>
<section class="card p-4"><h2 class="h4">WhatsApp</h2><p>{{ config('services.whatsapp.enabled') ? 'Las credenciales se administran en el servidor.' : 'WhatsApp está pendiente y desactivado. Las alertas internas funcionan sin Meta.' }}</p>@if(config('services.whatsapp.enabled'))@foreach($whatsapp as $clave=>$configurada)<p>{{ $clave }}: <strong>{{ $configurada ? 'Configurada' : 'Ausente' }}</strong></p>@endforeach
@endif<p>Las alertas de stock se actualizan al registrar movimientos. El programador de tareas revisa también los vencimientos periódicamente.</p><a href="{{ route('alertas.index') }}">Ver alertas</a></section>
@endsection
