@extends('layouts.acceso')
@section('titulo', 'Espera un momento')
@section('contenido')<h2>Demasiados intentos</h2><p>Espera un minuto antes de intentarlo de nuevo.</p><a class="btn btn-cafe" href="{{ route('login') }}">Volver al acceso</a>@endsection
