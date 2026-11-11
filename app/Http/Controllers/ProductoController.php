<?php

namespace App\Http\Controllers;

use App\Http\Requests\AjustarStockRequest;
use App\Http\Requests\ProductoRequest;
use App\Models\Categoria;
use App\Models\Producto;
use App\Services\ServicioInventario;
use App\Services\ServicioProductos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProductoController extends Controller
{
    private function contexto(): array
    {
        $esInsumo = request()->routeIs('insumos.*');
        return ['modulo' => $esInsumo ? 'insumos' : 'productos', 'esInsumo' => $esInsumo];
    }

    private function comprobarModulo(Producto $producto): void
    {
        $tipos = $this->contexto()['esInsumo'] ? ['insumo', 'ambos'] : ['venta', 'ambos'];
        abort_unless(in_array($producto->tipo, $tipos, true), 404);
    }

    public function ajustarStock(AjustarStockRequest $solicitud, Producto $producto, ServicioInventario $servicio)
    {
        if ($solicitud->routeIs('insumos.*')) $this->comprobarModulo($producto);
        $servicio->ajustar($producto, $solicitud->validated(), $solicitud->user()->id);

        return redirect()->route(($producto->tipo === 'insumo' ? 'insumos' : $this->contexto()['modulo']).'.mostrar', $producto)->with('estado', 'Stock actualizado y movimiento registrado.');
    }

    public function index(Request $solicitud)
    {
        $solicitud->validate(['categoria_id' => 'nullable|integer|exists:categorias,id', 'stock' => 'nullable|in:normal,bajo,agotado', 'estado' => 'nullable|in:activo,inactivo,archivado']);
        $consulta = Producto::with(['categoria', 'receta'])->whereIn('tipo', $this->contexto()['esInsumo'] ? ['insumo', 'ambos'] : ['venta', 'ambos']);
        if ($solicitud->filled('categoria_id')) {
            $consulta->where('categoria_id', $solicitud->categoria_id);
        }
        if ($solicitud->stock === 'agotado') {
            $consulta->where('stock_actual', '<=', 0);
        }
        if ($solicitud->stock === 'bajo') {
            $consulta->where('stock_actual', '>', 0)->whereColumn('stock_actual', '<=', 'stock_minimo');
        }
        if ($solicitud->stock === 'normal') {
            $consulta->whereColumn('stock_actual', '>', 'stock_minimo');
        }
        if ($solicitud->estado === 'archivado') {
            abort_unless($solicitud->user()->tienePermiso('productos.gestionar'), 403);
            $consulta->onlyTrashed();
        } elseif (in_array($solicitud->estado, ['activo', 'inactivo'])) {
            $consulta->where('estado', $solicitud->estado === 'activo');
        }

        return view('productos.indice', ['productos' => $consulta->orderBy('nombre')->get(), 'categorias' => Categoria::orderBy('nombre')->get()] + $this->contexto());
    }

    public function crear()
    {
        return view('productos.formulario', ['producto' => new Producto(['tipo' => $this->contexto()['esInsumo'] ? 'insumo' : 'venta']), 'categorias' => Categoria::where('estado', true)->orderBy('nombre')->get()] + $this->contexto());
    }

    public function editar(Producto $producto)
    {
        if (! request()->routeIs('insumos.*') && $producto->tipo === 'insumo') return redirect()->route('insumos.editar', $producto);
        $this->comprobarModulo($producto);
        return view('productos.formulario', compact('producto') + ['categorias' => Categoria::where('estado', true)->orderBy('nombre')->get()] + $this->contexto());
    }

    public function guardar(ProductoRequest $solicitud, ServicioProductos $servicio)
    {
        $servicio->guardar($solicitud->validated(), $solicitud->user()->id);

        return redirect()->route($this->contexto()['modulo'].'.index')->with('estado', $this->contexto()['esInsumo'] ? 'Insumo creado correctamente.' : 'Producto creado correctamente.');
    }

    public function actualizar(ProductoRequest $solicitud, Producto $producto, ServicioProductos $servicio)
    {
        $this->comprobarModulo($producto);
        $servicio->guardar($solicitud->validated(), $solicitud->user()->id, $producto);

        return redirect()->route($this->contexto()['modulo'].'.index')->with('estado', $this->contexto()['esInsumo'] ? 'Insumo actualizado correctamente.' : 'Producto actualizado correctamente.');
    }

    public function mostrar(Producto $producto)
    {
        if (! request()->routeIs('insumos.*') && $producto->tipo === 'insumo') return redirect()->route('insumos.mostrar', $producto);
        $this->comprobarModulo($producto);
        return view('productos.mostrar', compact('producto') + $this->contexto());
    }

    public function imagen(Producto $producto)
    {
        if (request()->routeIs('insumos.*')) $this->comprobarModulo($producto);
        $disco = $producto->imagen && Storage::disk('public')->exists($producto->imagen) ? 'public' : 'local';
        abort_unless($producto->imagen && Storage::disk($disco)->exists($producto->imagen), 404);

        return Storage::disk($disco)->response($producto->imagen, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=300']);
    }

    public function archivar(Producto $producto)
    {
        $this->comprobarModulo($producto);
        $producto->delete();
        app(\App\Services\ServicioAlertas::class)->sincronizar($producto);

        return redirect()->route($this->contexto()['modulo'].'.index')->with('estado', 'Artículo archivado. Su historial e imagen se conservan.');
    }

    public function restaurar(string $producto)
    {
        $producto = Producto::onlyTrashed()->findOrFail($producto);
        $this->comprobarModulo($producto);
        if ($producto->categoria_id && (! $producto->categoria || ! $producto->categoria->estado || $producto->categoria->trashed())) {
            throw ValidationException::withMessages(['categoria_id' => 'Primero restaura y activa la categoría del producto.']);
        }
        $producto->restore();
        app(\App\Services\ServicioAlertas::class)->sincronizar($producto);

        return redirect()->route($this->contexto()['modulo'].'.index')->with('estado', 'Artículo restaurado correctamente.');
    }
}
