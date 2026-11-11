@extends('layouts.acceso')
@section('titulo','Crear administrador')
@section('contenido')
<div class="icono-acceso"><i data-lucide="coffee"></i></div><p class="etiqueta">COMENCEMOS</p><h2>Crea tu cuenta de administrador</h2><p class="text-secondary">Esta será la primera cuenta de tu cafetería. Podrás crear las cuentas de tu equipo después.</p>
@include('partials.mensajes')
<form method="POST" action="{{ route('instalacion.guardar') }}">@csrf
<label for="nombre" class="form-label">Tu nombre</label><input id="nombre" name="nombre" class="form-control mb-3" value="{{ old('nombre') }}" required maxlength="120" autocomplete="name" autofocus>
<label for="email" class="form-label">Correo electrónico</label><input id="email" name="email" type="email" class="form-control" value="{{ old('email') }}" required maxlength="255" autocomplete="username"><p class="small text-secondary mt-2">Usa tu correo. No necesita estar registrado previamente en este sistema.</p>
<label for="password" class="form-label">Contraseña</label><div class="input-group mb-2"><input id="password" name="password" type="password" class="form-control" required minlength="12" maxlength="255" autocomplete="new-password"><button type="button" class="btn btn-outline-secondary" data-ver-clave="password" aria-label="Mostrar contraseña"><i data-lucide="eye"></i></button></div><p class="small text-secondary">Al menos 12 caracteres, con mayúsculas, minúsculas y números.</p>
<label for="password_confirmation" class="form-label">Repite tu contraseña</label><input id="password_confirmation" name="password_confirmation" type="password" class="form-control mb-4" required minlength="12" maxlength="255" autocomplete="new-password">
<button class="btn btn-cafe w-100">Crear administrador y entrar <i data-lucide="arrow-right"></i></button>
</form><a href="{{ route('login') }}" class="d-block text-center mt-3">Volver al acceso</a>
@endsection
