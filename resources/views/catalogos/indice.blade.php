@extends('layouts.panel')
@section('titulo',$configuracion['titulo'])
@section('contenido')
@php($gestiona=auth()->user()->tienePermiso($modulo.'.gestionar'))
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4"><div><p class="etiqueta">CAFETERÍA DELY</p><h1>{{ $configuracion['titulo'] }}</h1><p class="text-secondary mb-0">Información organizada para el día a día.</p></div>
@if($gestiona || ($modulo==='clientes' && auth()->user()->tienePermiso('clientes.registrar')))<a class="btn btn-cafe" href="{{ route($modulo.'.crear') }}"><i data-lucide="plus"></i> Crear {{ $configuracion['singular'] }}</a>@endif</div>
<form class="d-flex gap-2 mb-3 align-items-center" method="GET"><label for="filtro-estado">Estado</label><select id="filtro-estado" name="estado" class="form-select w-auto"><option value="">Todos los vigentes</option><option value="activo" @selected(request('estado')==='activo')>Activos</option><option value="inactivo" @selected(request('estado')==='inactivo')>Inactivos</option>@if($gestiona)<option value="archivado" @selected(request('estado')==='archivado')>Archivados</option>@endif</select><button class="btn btn-outline-secondary">Filtrar</button><a href="{{ route($modulo.'.index') }}" class="btn btn-link">Limpiar</a></form>
<div class="card p-4"><div class="table-responsive"><table class="table align-middle tabla-datos"><thead><tr><th>Nombre</th>@foreach($configuracion['campos'] as $campo=>$etiqueta)<th>{{ $etiqueta }}</th>@endforeach<th>Estado</th><th>Acciones</th></tr></thead>
<tbody>@foreach($registros as $registro)<tr><td class="fw-semibold">{{ $registro->nombre }}@if($registro->es_generico ?? false)<span class="badge text-bg-light">Genérico</span>@endif</td>@foreach($configuracion['campos'] as $campo=>$etiqueta)<td class="celda-texto">{{ $registro->$campo ?: '—' }}</td>@endforeach<td>{{ $registro->trashed() ? 'Archivado' : ($registro->estado ? 'Activo' : 'Inactivo') }}</td><td>
@if($gestiona && !($registro->es_generico ?? false))
@if($registro->trashed())<form method="POST" action="{{ route($modulo.'.restaurar',$registro->id) }}" data-confirmar="¿Restaurar este registro?">@csrf<button class="btn btn-sm btn-outline-success">Restaurar</button></form>
@else<div class="d-flex gap-2"><a class="btn btn-sm btn-outline-secondary" href="{{ route($modulo.'.editar',$registro->id) }}">Editar</a><form method="POST" action="{{ route($modulo.'.archivar',$registro->id) }}" data-confirmar="¿Archivar este registro?" data-accion="Archivar">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Archivar</button></form></div>@endif
@else<span class="text-secondary small">Solo consulta</span>@endif</td></tr>@endforeach</tbody></table></div></div>
@endsection
