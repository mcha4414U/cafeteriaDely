@extends(auth()->check() ? 'layouts.panel' : 'layouts.acceso')
@section('titulo','Acceso restringido')
@section('contenido')<section class="card p-5"><i data-lucide="lock"></i><h1 class="mt-3">No tienes permiso para entrar aquí</h1><p>Contacta al administrador si necesitas acceso a este módulo.</p><a href="{{ route('inicio') }}">Volver al inicio</a></section>@endsection
