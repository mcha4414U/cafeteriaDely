@extends('layouts.panel')
@section('titulo','Punto de venta')
@section('contenido')
<div class="pos-distribucion" id="punto-venta">
<section aria-label="Catálogo para venta">
<div class="d-flex flex-wrap gap-3 mb-4"><h1 class="h3 me-auto mb-0">Punto de venta</h1><label class="visually-hidden" for="buscar-pos">Buscar producto</label><input class="form-control pos-buscar" type="search" id="buscar-pos" placeholder="Buscar por nombre o código"></div>
<div class="d-flex flex-wrap gap-2 mb-4" id="categorias-pos" aria-label="Filtrar categorías"><button type="button" class="btn btn-cafe" data-categoria="" aria-pressed="true">Todos</button>@foreach($categorias as $categoria)<button type="button" class="btn btn-outline-secondary" data-categoria="{{ $categoria->id }}" aria-pressed="false">{{ $categoria->nombre }}</button>@endforeach
@if($productos->contains(fn($producto)=>!$producto->categoria_id))<button type="button" class="btn btn-outline-secondary" data-categoria="sin-categoria" aria-pressed="false">Sin categoría</button>@endif
</div>
<div class="pos-productos" id="productos-pos">
@foreach($productos as $producto)
<button type="button" class="card pos-producto text-start" data-producto="{{ $producto->id }}" data-categoria="{{ $producto->categoria_id ?? 'sin-categoria' }}" data-busqueda="{{ $producto->nombre }} {{ $producto->codigo }}" aria-label="Añadir {{ $producto->nombre }} a la orden">
@if($producto->imagen)<img src="{{ route('productos.imagen',$producto) }}" alt="{{ $producto->nombre }}" loading="lazy">@else<span class="pos-sin-imagen"><i data-lucide="coffee"></i></span>@endif
<span class="p-3"><strong class="d-block">{{ $producto->nombre }}</strong><span class="pos-precio d-block mt-2">Bs. {{ \App\Support\Moneda::mostrar($producto->precio_venta) }}</span><small class="text-secondary d-block mt-2">{{ $producto->receta ? ($producto->receta->estado ? 'Preparado con receta' : 'Receta inactiva') : ($producto->requiereReceta() ? 'Falta configurar receta' : 'Stock: '.\App\Support\Cantidad::mostrar($producto->stock_actual).' '.$producto->unidad_medida) }}</small></span>
</button>
@endforeach
</div>
<p id="pos-sin-resultados" class="text-secondary mt-4" @if($productos->isNotEmpty()) hidden @endif>No hay productos de venta disponibles con estos filtros.</p>
</section>
<aside class="card p-4 pos-orden">
<h2 class="h4 mb-4">Orden actual</h2>
<div id="lineas-carrito" class="pos-lineas"></div>
<p id="carrito-vacio" class="text-secondary">Selecciona un producto para comenzar.</p>
<div class="border-top pt-3 mt-4"><div class="d-flex justify-content-between mb-3"><span>Subtotal</span><span id="subtotal-pos">Bs. 0,00</span></div><div class="d-flex justify-content-between fs-4 fw-semibold"><span>Total</span><span id="total-pos" aria-live="polite">Bs. 0,00</span></div></div>
<form id="cobro-pos" data-usuario="{{ auth()->id() }}" action="{{ route('ventas.guardar') }}" method="POST" class="mt-3">@csrf
@if($pedidoInicial)<div class="alert alert-info">Cobro del pedido. Se aplican los precios vigentes al confirmar.</div><input type="hidden" name="pedido_id" value="{{ $pedidoInicial['id'] }}">@endif
<input type="hidden" name="clave_idempotencia" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
<label for="cliente-pos" class="form-label">Cliente</label><select id="cliente-pos" name="cliente_id" class="form-select mb-2" required>@foreach($clientes as $cliente)<option value="{{ $cliente->id }}">{{ $cliente->nombre }}</option>@endforeach</select>
<label for="metodo-pos" class="form-label">Método de pago</label><select id="metodo-pos" name="metodo_pago_id" class="form-select mb-2" required>@foreach($metodos as $metodo)<option value="{{ $metodo->id }}">{{ $metodo->nombre }}</option>@endforeach</select>
<label for="descuento-pos" class="form-label">Descuento (Bs.)</label><input id="descuento-pos" name="descuento" class="form-control mb-2" type="number" min="0" step="0.01" value="0" required>
<label for="recibido-pos" class="form-label">Recibido en efectivo (Bs.)</label><input id="recibido-pos" name="recibido" class="form-control mb-2" type="number" min="0" step="0.01"><small id="cambio-pos" class="text-secondary"></small>
<label for="notas-pos" class="form-label d-block mt-2">Nota</label><textarea id="notas-pos" name="notas" maxlength="2000" class="form-control"></textarea>
<p class="small text-secondary mt-2">Confirma el pago recibido antes de cobrar. QR y tarjeta se registran aquí después de verificarlos.</p></form>
<button type="button" class="btn btn-outline-danger mt-4" id="limpiar-carrito" disabled>Limpiar orden</button>
<button type="button" class="btn btn-cafe btn-lg mt-3 d-flex justify-content-between" id="cobrar-pos" disabled><span>Cobrar</span><span id="cobrar-total">Bs. 0,00</span></button>
<p class="small text-secondary mt-3 mb-0"></p>
<div id="aviso-pos" role="status" class="small mt-3"></div>
</aside>
</div>
@include('pos.modal-receta')
<script type="application/json" id="datos-pos">{!! json_encode($datosProductos, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
<script type="application/json" id="pedido-pos">{!! json_encode($pedidoInicial, JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
<noscript><div class="alert alert-warning">Activa JavaScript para usar el carrito.</div></noscript>
@endsection
