<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('titulo', 'Bienvenido') · Cafetería Dely</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="pagina-acceso"><main class="acceso">
<section class="presentacion"><header>@yield('volver')<a class="marca" href="{{ route('login') }}"><i data-lucide="coffee"></i><span>Dely<small>CAFETERÍA</small></span></a></header><div><span class="etiqueta">HECHO CON CALIDEZ</span><h1>Un buen café.<br>Un gran comienzo.</h1><p>Todo lo que necesitas para cuidar de tu cafetería, en un solo lugar.</p></div><footer>Gestión cercana. Cada día.</footer></section>
<section class="formulario-acceso"><div class="acceso-contenido">@yield('contenido')</div><footer>Cafetería Dely · Sistema de gestión</footer></section>
</main></body></html>

