@extends('layouts.panel')
@section('titulo','Inicio')
@section('contenido')
<div class="bienvenida"><p class="etiqueta">TU ESPACIO DE TRABAJO</p><h1>Hola, {{ auth()->user()->nombre }}.</h1><p class="text-secondary">Bienvenido a Cafetería Dely.</p></div>
<div class="row g-4 mt-2"><div class="col-lg-7"><section class="card p-4"><i data-lucide="shield-check" class="mb-3"></i><h3>Tu cuenta está lista</h3><p>Ingresaste como <strong>{{ auth()->user()->roles->pluck('nombre')->join(', ') }}</strong>.</p>
@if(auth()->user()->tienePermiso('usuarios.gestionar'))<p class="text-secondary">Administra las cuentas de tu equipo y revisa sus permisos.</p><a class="btn btn-cafe align-self-start" href="{{ route('usuarios.index') }}">Gestionar usuarios <i data-lucide="arrow-right"></i></a>@else<p class="text-secondary">Tu cuenta tiene permisos de caja, consulta de productos, clientes y ventas propias.</p>@endif
</section></div><div class="col-lg-5"><section class="card p-4"><p class="etiqueta">PRÓXIMAMENTE</p><h3>Seguimos preparando todo</h3><p class="text-secondary mb-0">Los catálogos de productos, categorías, clientes y proveedores ya están disponibles según tus permisos. Inventario y punto de venta se incorporarán en las siguientes fases.</p></section></div></div>
@endsection

