@forelse($avisosStock as $aviso)
<div class="p-3 border-bottom">
<span class="badge {{ $aviso->tipo_alerta === 'agotado' ? 'text-bg-danger' : 'text-bg-warning' }}">{{ $aviso->tipo_alerta === 'agotado' ? 'Producto agotado' : 'Stock bajo' }}</span>
<p class="fw-semibold mt-2 mb-1">{{ $aviso->nombre }}</p>
<p class="small mb-1">Disponible: {{ \App\Support\Cantidad::mostrar($aviso->stock_actual) }} {{ $aviso->unidad_medida }}</p>
@if($aviso->tipo_alerta === 'stock_bajo')<p class="small mb-2">Mínimo: {{ \App\Support\Cantidad::mostrar($aviso->stock_minimo) }} {{ $aviso->unidad_medida }}</p>@endif
<form method="POST" action="{{ route('notificaciones.stock.avisar', $aviso->id) }}" data-avisar-stock>@csrf
<button type="submit" class="btn btn-sm btn-outline-secondary">Avisar al administrador</button>
</form>
</div>
@empty
<p class="p-3 mb-0 text-secondary">No hay notificaciones de stock pendientes.</p>
@endforelse
