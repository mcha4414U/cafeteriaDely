@extends('layouts.acceso')
@section('titulo','Recuperar contraseña')
@section('contenido')
<p class="etiqueta">RECUPERA TU ACCESO</p><h2>¿Olvidaste tu contraseña?</h2><p class="text-secondary">Te enviaremos un enlace para crear una nueva.</p>@include('partials.mensajes')
<form method="POST" action="{{ route('password.email') }}">@csrf<label for="email" class="form-label">Correo electrónico</label><input class="form-control mb-4" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email"><button class="btn btn-cafe w-100">Enviar enlace</button></form><a class="d-block mt-4" href="{{ route('login') }}">Volver a iniciar sesión</a>
@endsection

