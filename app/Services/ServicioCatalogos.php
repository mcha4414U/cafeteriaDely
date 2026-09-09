<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Cliente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServicioCatalogos
{
    private function validar(Model $registro, bool $desactivar): void
    {
        if ($registro instanceof Cliente && $registro->es_generico) {
            throw ValidationException::withMessages(['nombre' => 'El cliente genérico está protegido y no se puede modificar ni archivar.']);
        }
        if ($registro instanceof Categoria && $desactivar && $registro->productos()->exists()) {
            throw ValidationException::withMessages(['estado' => 'Esta categoría tiene productos. Reasígnalos o archívalos antes de desactivarla.']);
        }
    }

    public function guardar(Model $registro, array $datos): Model
    {
        return DB::transaction(function () use ($registro, $datos) {
            if ($registro->exists) {
                $registro = $registro->newQuery()->lockForUpdate()->findOrFail($registro->id);
            }
            $this->validar($registro, ! $datos['estado']);
            $registro->fill($datos)->save();

            return $registro;
        });
    }

    public function archivar(Model $registro): void
    {
        DB::transaction(function () use ($registro) {
            $registro = $registro->newQuery()->lockForUpdate()->findOrFail($registro->id);
            $this->validar($registro, true);
            $registro->delete();
        });
    }
}
