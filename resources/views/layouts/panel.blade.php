<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('titulo','Inicio') · Cafetería Dely</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="layout-fixed sidebar-expand-lg bg-body-tertiary"><div class="app-wrapper">
<aside class="app-sidebar sidebar-dely shadow" data-bs-theme="dark"><div class="sidebar-brand"><a href="{{ route('inicio') }}" class="marca"><i data-lucide="coffee"></i><span>Dely<small>CAFETERÍA</small></span></a></div><div class="sidebar-wrapper"><nav><ul class="nav sidebar-menu flex-column" role="menu">
<li class="nav-item"><a class="nav-link {{ request()->routeIs('inicio') ? 'active' : '' }}" href="{{ route('inicio') }}"><i data-lucide="house"></i><p>Inicio</p></a></li>
@if(auth()->user()->tienePermiso('usuarios.gestionar'))<li class="nav-item"><a class="nav-link {{ request()->routeIs('usuarios.*') ? 'active' : '' }}" href="{{ route('usuarios.index') }}"><i data-lucide="users"></i><p>Usuarios y permisos</p></a></li>@endif
</ul></nav><div class="perfil"><span>{{ auth()->user()->nombre }}</span><small>{{ auth()->user()->roles->pluck('nombre')->join(', ') }}</small><form method="POST" action="{{ route('sesion.salir') }}">@csrf<button class="btn btn-salir"><i data-lucide="log-out"></i> Cerrar sesión</button></form></div></div></aside>
<nav class="app-header navbar navbar-expand bg-white"><div class="container-fluid"><button type="button" class="btn" data-lte-toggle="sidebar" aria-label="Abrir o cerrar menú"><i data-lucide="menu"></i></button><span class="me-auto fw-semibold">@yield('titulo','Inicio')</span><span class="text-secondary small">{{ now()->format('d/m/Y') }}</span></div></nav>
<main class="app-main"><div class="app-content p-4"><div class="container-fluid">@include('partials.mensajes')@yield('contenido')</div></div></main>
<footer class="app-footer text-secondary">Cafetería Dely · Hecho para tu día a día</footer></div></body></html>

