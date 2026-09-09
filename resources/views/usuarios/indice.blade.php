@extends('layouts.panel')
@section('titulo','Usuarios y permisos')
@section('contenido')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4"><div><p class="etiqueta">ADMINISTRACIÓN</p><h1>Tu equipo</h1><p class="text-secondary mb-0">Organiza quién puede acceder a tu cafetería.</p></div><a class="btn btn-cafe" href="{{ route('usuarios.crear') }}"><i data-lucide="plus"></i> Nuevo usuario</a></div>
<div class="card p-4"><div class="table-responsive"><table class="table align-middle tabla-datos"><thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>@foreach($usuarios as $usuario)<tr><td class="fw-semibold">{{ $usuario->nombre }}</td><td>{{ $usuario->email }}</td><td>{{ $usuario->roles->pluck('nombre')->join(', ') }}</td><td><span class="badge {{ $usuario->estado ? 'text-bg-success' : 'text-bg-secondary' }}">{{ $usuario->estado ? 'Activo' : 'Inactivo' }}</span></td><td><a href="{{ route('usuarios.editar',$usuario) }}" class="btn btn-sm btn-outline-secondary" aria-label="Editar a {{ $usuario->nombre }}"><i data-lucide="pencil"></i> Editar</a></td></tr>@endforeach</tbody></table></div></div>
<h2 class="h4 mt-5 mb-3">Roles y permisos</h2><div class="row g-3">@foreach($roles as $rol)<div class="col-lg-6"><section class="card p-4 h-100"><h3 class="h5">{{ $rol->nombre }}</h3><p class="text-secondary">{{ $rol->descripcion }}</p><ul class="permisos">@foreach($rol->permisos as $permiso)<li>{{ __('permisos.'.$permiso->nombre) }}</li>@endforeach</ul></section></div>@endforeach</div>
@endsection

