@extends('layouts.acceso')
@section('titulo','Restablecer contraseña')
@section('contenido')
<h2>Elige una nueva contraseña</h2><p class="text-secondary">Usa al menos 12 caracteres, mayúsculas, minúsculas y números.</p>@include('partials.mensajes')
<form method="POST" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}">
<label for="email" class="form-label">Correo electrónico</label><input class="form-control mb-3" id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="username">
<label for="password" class="form-label">Nueva contraseña</label><input class="form-control mb-3" id="password" name="password" type="password" minlength="12" required autocomplete="new-password">
<label for="password_confirmation" class="form-label">Confirmar contraseña</label><input class="form-control mb-4" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"><button class="btn btn-cafe w-100">Guardar contraseña</button></form>
@endsection

