<div class="dropdown me-3" data-notificaciones-stock data-url="{{ route('notificaciones.stock') }}">
<button class="btn btn-outline-secondary position-relative" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notificaciones de stock">
<i data-lucide="bell" aria-hidden="true"></i><span class="badge rounded-pill text-bg-danger ms-1" data-contador-stock>{{ $avisosStock->count() }}</span>
</button>
<div class="dropdown-menu dropdown-menu-end avisos-stock-menu p-0">
<div class="p-3 border-bottom"><strong>Notificaciones de stock</strong><p class="small text-secondary mb-0">Alertas activas de stock bajo y productos agotados.</p></div>
<div class="small px-3 pt-2" role="status" aria-live="polite" data-estado-avisos></div>
<div class="avisos-stock-lista" data-lista-avisos>@include('partials.lista-avisos-stock')</div>
</div>
</div>
