<?php

namespace App\Http\Requests;

use App\Models\Categoria;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $modulo = $this->route('modulo');
        $permiso = $modulo === 'clientes' && $this->isMethod('POST') ? 'clientes.registrar' : $modulo.'.gestionar';

        return $this->user()?->tienePermiso($permiso) ?? false;
    }

    public function rules(): array
    {
        $reglas = ['nombre' => ['required', 'string', 'max:150'], 'estado' => ['required', 'boolean']];
        if ($this->route('modulo') === 'categorias') {
            $categoria = $this->route('registro') ? Categoria::findOrFail($this->route('registro')) : null;
            $reglas['nombre'][] = Rule::unique('categorias')->ignore($categoria);
            $reglas['descripcion'] = ['nullable', 'string', 'max:3000'];
        } else {
            $reglas += ['telefono' => ['nullable', 'string', 'max:30'], 'direccion' => ['nullable', 'string', 'max:1000'], 'observaciones' => ['nullable', 'string', 'max:3000']];
            if ($this->route('modulo') === 'proveedores') {
                $reglas['empresa'] = ['nullable', 'string', 'max:150'];
            }
        }

        return $reglas;
    }
}
