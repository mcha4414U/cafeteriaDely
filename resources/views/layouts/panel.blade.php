<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('titulo','Inicio') · Cafetería Dely</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary"><div class="app-wrapper">
<aside class="app-sidebar sidebar-dely shadow" data-bs-theme="dark"><div class="sidebar-brand"><a href="{{ route('inicio') }}" class="marca"><i data-lucide="coffee"></i><span>Dely<small>CAFETERÍA</small></span></a></div><div class="sidebar-wrapper"><nav><ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" data-accordion="false" role="menu">
@if(auth()->user()->tienePermiso('pos.usar'))
<li class="nav-item"><a class="nav-link {{ request()->routeIs('pos.*') ? 'active' : '' }}" href="{{ route('pos.index') }}"><i data-lucide="shopping-cart"></i><p>Punto de venta</p></a></li>
@endif
@if(auth()->user()->puedeVerPreparaciones())
<li class="nav-item"><a class="nav-link {{ request()->routeIs('pedidos.*') ? 'active' : '' }}" href="{{ route('pedidos.index') }}"><i data-lucide="coffee"></i><p>Preparaciones</p></a></li>
@endif
@php
    $gruposSidebar = [
        'inventario' => [
            'titulo' => 'Inventario', 'icono' => 'package',
            'opciones' => [
                ['Productos', 'productos.index', 'productos.consultar', ['productos.*', 'recetas.*'], 'coffee'],
                ['Insumos', 'insumos.index', 'productos.consultar', ['insumos.*'], 'package'],
                ['Categorías', 'categorias.index', 'categorias.gestionar', ['categorias.*'], 'users'],
                ['Existencias y movimientos', 'inventario.index', 'inventario.gestionar', ['inventario.*'], 'package'],
                ['Reposición', 'recomendaciones.index', 'recomendaciones.ver', ['recomendaciones.*'], 'bell'],
                ['Desperdicios', 'desperdicios.index', 'desperdicios.gestionar', ['desperdicios.*'], 'bell'],
            ],
        ],
        'compras' => [
            'titulo' => 'Compras', 'icono' => 'shopping-cart',
            'opciones' => [
                ['Compras', 'compras.index', 'compras.gestionar', ['compras.*'], 'shopping-cart'],
                ['Proveedores', 'proveedores.index', 'proveedores.gestionar', ['proveedores.*'], 'users'],
            ],
        ],
    ];
@endphp
@foreach($gruposSidebar as $grupoSidebar => $datosSidebar)
    @php
        $opcionesSidebar = collect($datosSidebar['opciones'])->filter(fn ($opcion) => auth()->user()->tienePermiso($opcion[2]));
        $abiertoSidebar = $opcionesSidebar->contains(fn ($opcion) => request()->routeIs(...$opcion[3]));
    @endphp
    @if($opcionesSidebar->isNotEmpty())
    <li class="nav-item {{ $abiertoSidebar ? 'menu-open' : '' }}">
        <button type="button" class="nav-link sidebar-toggle {{ $abiertoSidebar ? 'active' : '' }}" aria-expanded="{{ $abiertoSidebar ? 'true' : 'false' }}" aria-controls="submenu-{{ $grupoSidebar }}">
            <i data-lucide="{{ $datosSidebar['icono'] }}"></i><p>{{ $datosSidebar['titulo'] }}</p>
            <svg class="nav-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m9 6 6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
        </button>
        <ul class="nav nav-treeview" id="submenu-{{ $grupoSidebar }}">
        @foreach($opcionesSidebar as $opcion)
            <li class="nav-item"><a class="nav-link {{ request()->routeIs(...$opcion[3]) ? 'active' : '' }}" href="{{ route($opcion[1]) }}"><i data-lucide="{{ $opcion[4] }}"></i><p>{{ $opcion[0] }}</p></a></li>
        @endforeach
        </ul>
    </li>
    @endif
@endforeach
@foreach([
    ['Alertas','alertas.index',['alertas.gestionar'],'alertas.*','bell'],
    ['Clientes','clientes.index',['clientes.consultar'],'clientes.*','users'],
    ['Usuarios y permisos','usuarios.index',['usuarios.gestionar'],'usuarios.*','users'],
    ['Reportes','reportes.index',['reportes.ver'],'reportes.*','bell'],
    ['Historial de ventas','ventas.index',['ventas.todas','ventas.propias'],'ventas.*','receipt'],
    ['Configuración','configuracion.index',['configuracion.gestionar'],'configuracion.*','bell'],
] as $opcion)
@if(collect($opcion[2])->contains(fn ($permiso) => auth()->user()->tienePermiso($permiso)))
<li class="nav-item"><a class="nav-link {{ request()->routeIs($opcion[3]) ? 'active' : '' }}" href="{{ route($opcion[1]) }}"><i data-lucide="{{ $opcion[4] }}"></i><p>{{ $opcion[0] }}</p></a></li>
@endif
@endforeach
</ul></nav><div class="perfil"><span>{{ auth()->user()->nombre }}</span><small>{{ auth()->user()->roles->pluck('nombre')->join(', ') }}</small><form method="POST" action="{{ route('sesion.salir') }}">@csrf<button class="btn btn-salir"><i data-lucide="log-out"></i> Cerrar sesión</button></form></div></div></aside>
<nav class="app-header navbar navbar-expand bg-white"><div class="container-fluid"><button type="button" class="btn" data-lte-toggle="sidebar" aria-label="Abrir o cerrar menú"><i data-lucide="menu"></i></button><span class="me-auto fw-semibold">@yield('titulo','Inicio')</span>@if(auth()->user()->puedeConsultarAvisosStock())@include('partials.notificaciones-stock')@endif<span class="text-secondary small">{{ now()->format('d/m/Y') }}</span></div></nav>
<main class="app-main"><div class="app-content p-4"><div class="container-fluid">@include('partials.mensajes')@yield('contenido')</div></div></main>
<footer class="app-footer text-secondary">Cafetería Dely · Hecho para tu día a día</footer></div></body></html>
