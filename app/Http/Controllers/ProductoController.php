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
    public function ajustarStock(AjustarStockRequest $solicitud, Producto $producto, ServicioInventario $servicio)
    {
        $servicio->ajustar($producto, $solicitud->validated(), $solicitud->user()->id);

        return redirect()->route('productos.mostrar', $producto)->with('estado', 'Stock actualizado y movimiento registrado.');
    }

    public function index(Request $solicitud)
    {
        $solicitud->validate(['categoria_id' => 'nullable|integer|exists:categorias,id', 'stock' => 'nullable|in:normal,bajo,agotado', 'estado' => 'nullable|in:activo,inactivo,archivado']);
        $consulta = Producto::with('categoria');
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

        return view('productos.indice', ['productos' => $consulta->orderBy('nombre')->get(), 'categorias' => Categoria::orderBy('nombre')->get()]);
    }

    public function crear()
    {
        return view('productos.formulario', ['producto' => new Producto, 'categorias' => Categoria::where('estado', true)->orderBy('nombre')->get()]);
    }

    public function editar(Producto $producto)
    {
        return view('productos.formulario', compact('producto') + ['categorias' => Categoria::where('estado', true)->orderBy('nombre')->get()]);
    }

    public function guardar(ProductoRequest $solicitud, ServicioProductos $servicio)
    {
        $servicio->guardar($solicitud->validated(), $solicitud->user()->id);

        return redirect()->route('productos.index')->with('estado', 'Producto creado correctamente.');
    }

    public function actualizar(ProductoRequest $solicitud, Producto $producto, ServicioProductos $servicio)
    {
        $servicio->guardar($solicitud->validated(), $solicitud->user()->id, $producto);

        return redirect()->route('productos.index')->with('estado', 'Producto actualizado correctamente.');
    }

    public function mostrar(Producto $producto)
    {
        return view('productos.mostrar', compact('producto'));
    }

    public function imagen(Producto $producto)
    {
        abort_unless($producto->imagen && Storage::disk('local')->exists($producto->imagen), 404);

        return Storage::disk('local')->response($producto->imagen, null, ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=300']);
    }

    public function archivar(Producto $producto)
    {
        $producto->delete();

        return redirect()->route('productos.index')->with('estado', 'Producto archivado. Su historial e imagen se conservan.');
    }

    public function restaurar(string $producto)
    {
        $producto = Producto::onlyTrashed()->findOrFail($producto);
        if (! $producto->categoria || ! $producto->categoria->estado || $producto->categoria->trashed()) {
            throw ValidationException::withMessages(['categoria_id' => 'Primero restaura y activa la categoría del producto.']);
        }
        $producto->restore();

        return redirect()->route('productos.index')->with('estado', 'Producto restaurado correctamente.');
    }
}
