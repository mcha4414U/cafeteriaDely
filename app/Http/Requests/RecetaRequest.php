<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecetaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tienePermiso('productos.gestionar') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'], 'descripcion' => ['nullable', 'string', 'max:3000'], 'estado' => ['required', 'boolean'],
            'detalles' => ['required', 'array', 'min:1', 'max:100'],
            'detalles.*.insumo_id' => ['required', 'integer', 'distinct', Rule::exists('productos', 'id')->whereNull('deleted_at')->where('estado', true)->whereIn('tipo', ['insumo', 'ambos'])],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.001', 'max:999999999.999', 'decimal:0,3'],
            'detalles.*.unidad_medida' => ['required', 'string', 'max:100'],
        ];
    }
}
