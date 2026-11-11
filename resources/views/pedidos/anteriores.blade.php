@extends('layouts.panel')
@section('titulo','Pedidos anteriores')
@section('contenido')
<div class="d-flex justify-content-between mb-4"><h1>Pedidos anteriores</h1><a class="btn btn-cafe" href="{{ route('pedidos.index') }}">Volver a preparaciones</a></div>
<div class="card p-4 table-responsive"><table class="table"><thead><tr><th>Número</th><th>Cliente</th><th>Fecha</th><th>Total estimado</th><th>Estado</th><th></th></tr></thead><tbody>@forelse($pedidos as $p)<tr><td>{{ $p->numero }}</td><td>{{ $p->cliente }}</td><td>{{ $p->fecha }}</td><td>Bs. {{ \App\Support\Moneda::mostrar($p->total) }}</td><td>{{ str_replace('_',' ',$p->estado) }}</td><td><a href="{{ route('pedidos.mostrar',$p->id) }}">Ver pedido</a></td></tr>@empty<tr><td colspan="6">No hay pedidos.</td></tr>@endforelse</tbody></table>{{ $pedidos->links() }}</div>
@endsection
