@extends('layouts.acceso')
@section('titulo', 'Sesión caducada')
@section('contenido')<h2>Tu sesión ha caducado</h2><p>Vuelve a ingresar para continuar.</p><a class="btn btn-cafe" href="{{ route('login') }}">Iniciar sesión</a>@endsection
