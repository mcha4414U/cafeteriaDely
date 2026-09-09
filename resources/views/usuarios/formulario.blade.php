@extends('layouts.panel')
@section('titulo', $usuario->exists ? 'Editar usuario' : 'Nuevo usuario')
@section('contenido')
<a href="{{ route('usuarios.index') }}" class="d-inline-block mb-3">← Volver al equipo</a><h1 class="mb-4">{{ $usuario->exists ? 'Editar usuario' : 'Un nuevo integrante' }}</h1>
<form class="card p-4 formulario-usuario" method="POST" action="{{ $usuario->exists ? route('usuarios.actualizar',$usuario) : route('usuarios.guardar') }}" data-confirmar="¿Guardar los cambios de este usuario?">@csrf @if($usuario->exists)@method('PUT')@endif
<div class="row g-3"><div class="col-md-6"><label for="nombre" class="form-label">Nombre completo</label><input id="nombre" class="form-control" name="nombre" value="{{ old('nombre',$usuario->nombre) }}" required maxlength="120"></div>
<div class="col-md-6"><label for="email" class="form-label">Correo electrónico</label><input id="email" class="form-control" name="email" type="email" value="{{ old('email',$usuario->email) }}" required autocomplete="off"></div>
<div class="col-md-6"><label for="rol_id" class="form-label">Rol</label><select id="rol_id" class="form-select" name="rol_id" required><option value="">Selecciona un rol</option>@foreach($roles as $rol)<option value="{{ $rol->id }}" @selected(old('rol_id',$usuario->exists ? $usuario->roles->first()?->id : null) == $rol->id)>{{ $rol->nombre }}</option>@endforeach</select></div>
<div class="col-md-6"><label for="estado" class="form-label">Estado de la cuenta</label><select id="estado" class="form-select" name="estado"><option value="1" @selected(old('estado',$usuario->exists ? (int)$usuario->estado : 1) == 1)>Activo</option><option value="0" @selected(old('estado',$usuario->exists ? (int)$usuario->estado : 1) == 0)>Inactivo</option></select></div>
<div class="col-12"><hr><p class="text-secondary mb-0">La contraseña debe tener 12 caracteres como mínimo, mayúsculas, minúsculas y números. {{ $usuario->exists ? 'Deja estos campos vacíos para mantener la actual.' : '' }}</p></div>
<div class="col-md-6"><label for="password" class="form-label">Contraseña</label><input id="password" class="form-control" name="password" type="password" autocomplete="new-password" @required(!$usuario->exists)></div><div class="col-md-6"><label for="password_confirmation" class="form-label">Confirmar contraseña</label><input id="password_confirmation" class="form-control" name="password_confirmation" type="password" autocomplete="new-password" @required(!$usuario->exists)></div></div>
<div class="d-flex gap-3 mt-4"><button class="btn btn-cafe">Guardar usuario</button><a class="btn btn-outline-secondary" href="{{ route('usuarios.index') }}">Cancelar</a></div></form>
@endsection

