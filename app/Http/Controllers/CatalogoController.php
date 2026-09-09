<?php

namespace App\Http\Controllers;

use App\Http\Requests\CatalogoRequest;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Proveedor;
use App\Services\ServicioCatalogos;
use Illuminate\Http\Request;

class CatalogoController extends Controller
{
    private function configuracion(string $modulo): array
    {
        return match ($modulo) {
            'categorias' => ['modelo' => Categoria::class, 'titulo' => 'Categorías', 'singular' => 'categoría', 'campos' => ['descripcion' => 'Descripción']],
            'clientes' => ['modelo' => Cliente::class, 'titulo' => 'Clientes', 'singular' => 'cliente', 'campos' => ['telefono' => 'Teléfono', 'direccion' => 'Dirección', 'observaciones' => 'Observaciones']],
            'proveedores' => ['modelo' => Proveedor::class, 'titulo' => 'Proveedores', 'singular' => 'proveedor', 'campos' => ['empresa' => 'Empresa', 'telefono' => 'Teléfono', 'direccion' => 'Dirección', 'observaciones' => 'Observaciones']],
        };
    }

    public function index(Request $solicitud)
    {
        $modulo = $solicitud->route('modulo');
        $configuracion = $this->configuracion($modulo);
        $solicitud->validate(['estado' => ['nullable', 'in:activo,inactivo,archivado']]);
        $consulta = $configuracion['modelo']::query();
        if ($solicitud->estado === 'archivado') {
            abort_unless($solicitud->user()->tienePermiso($modulo.'.gestionar'), 403);
            $consulta->onlyTrashed();
        } elseif (in_array($solicitud->estado, ['activo', 'inactivo'])) {
            $consulta->where('estado', $solicitud->estado === 'activo');
        }

        return view('catalogos.indice', compact('modulo', 'configuracion') + ['registros' => $consulta->orderBy('nombre')->get()]);
    }

    public function crear(Request $solicitud)
    {
        $modulo = $solicitud->route('modulo');
        $configuracion = $this->configuracion($modulo);

        return view('catalogos.formulario', compact('modulo', 'configuracion') + ['registro' => new $configuracion['modelo']]);
    }

    public function editar(Request $solicitud, string $registro)
    {
        $modulo = $solicitud->route('modulo');
        $configuracion = $this->configuracion($modulo);
        $registro = $configuracion['modelo']::findOrFail($registro);
        abort_if($registro instanceof Cliente && $registro->es_generico, 403);

        return view('catalogos.formulario', compact('modulo', 'configuracion', 'registro'));
    }

    public function guardar(CatalogoRequest $solicitud, ServicioCatalogos $servicio)
    {
        $modulo = $solicitud->route('modulo');
        $configuracion = $this->configuracion($modulo);
        $servicio->guardar(new $configuracion['modelo'], $solicitud->validated());

        return redirect()->route($modulo.'.index')->with('estado', 'Registro creado correctamente.');
    }

    public function actualizar(CatalogoRequest $solicitud, string $registro, ServicioCatalogos $servicio)
    {
        $modulo = $solicitud->route('modulo');
        $configuracion = $this->configuracion($modulo);
        $servicio->guardar($configuracion['modelo']::findOrFail($registro), $solicitud->validated());

        return redirect()->route($modulo.'.index')->with('estado', 'Registro actualizado correctamente.');
    }

    public function archivar(Request $solicitud, string $registro, ServicioCatalogos $servicio)
    {
        $modulo = $solicitud->route('modulo');
        $configuracion = $this->configuracion($modulo);
        $servicio->archivar($configuracion['modelo']::findOrFail($registro));

        return redirect()->route($modulo.'.index')->with('estado', 'Registro archivado. Puedes recuperarlo desde el filtro Archivados.');
    }

    public function restaurar(Request $solicitud, string $registro)
    {
        $modulo = $solicitud->route('modulo');
        $configuracion = $this->configuracion($modulo);
        $registro = $configuracion['modelo']::onlyTrashed()->findOrFail($registro);
        $registro->restore();

        return redirect()->route($modulo.'.index')->with('estado', 'Registro restaurado correctamente.');
    }
}
