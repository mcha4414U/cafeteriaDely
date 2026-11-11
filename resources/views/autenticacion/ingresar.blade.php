@extends('layouts.acceso')
@section('titulo','Iniciar sesión')
@section('contenido')
<div class="icono-acceso"><i data-lucide="coffee"></i></div><p class="etiqueta">BIENVENIDO DE NUEVO</p><h2>Tu cafetería te espera</h2><p class="text-secondary mb-4">Ingresa a tu cuenta para comenzar el día.</p>
@include('partials.mensajes')
@if($puedeCrearAdministrador)
<div class="alert alert-light border"><strong>Aún no tienes una cuenta.</strong><p class="small mb-3">Crea el primer administrador para comenzar. No necesitas un correo registrado en el sistema.</p><a class="btn btn-cafe w-100" href="{{ route('instalacion.formulario') }}">Crear administrador</a></div>
@endif
<form method="POST" action="{{ route('sesion.ingresar') }}">@csrf
<label for="email" class="form-label">Correo electrónico</label><input id="email" class="form-control mb-3" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="tu@correo.com">
<label for="password" class="form-label">Contraseña</label><div class="input-group mb-3"><input id="password" class="form-control" name="password" type="password" required autocomplete="current-password"><button class="btn btn-outline-secondary" type="button" data-ver-clave="password" aria-label="Mostrar contraseña"><i data-lucide="eye"></i></button></div>
<div class="d-flex justify-content-between gap-3 mb-4"><label class="form-check"><input class="form-check-input" type="checkbox" name="recordarme" value="1"> Recordarme</label><a href="{{ route('password.request') }}">Olvidé mi contraseña</a></div>
<button class="btn btn-cafe w-100" type="submit">Iniciar sesión <i data-lucide="arrow-right"></i></button>
</form>@unless($puedeCrearAdministrador)<p class="ayuda">¿Necesitas una cuenta? Contacta al administrador.</p>@endunless
@endsection
