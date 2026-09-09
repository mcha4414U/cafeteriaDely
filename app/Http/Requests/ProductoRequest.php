<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tienePermiso('productos.gestionar') ?? false;
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:150'], 'descripcion' => ['nullable', 'string', 'max:3000'],
            'categoria_id' => ['required', 'integer', Rule::exists('categorias', 'id')->whereNull('deleted_at')->where('estado', true)],
            'codigo' => ['required', 'string', 'max:80', Rule::unique('productos')->ignore($this->route('producto'))],
            'precio_compra' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'precio_venta' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'stock_inicial' => [$this->route('producto') ? 'prohibited' : 'required', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
            'stock_actual' => ['prohibited'],
            'stock_minimo' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
            'unidad_medida' => ['required', Rule::in(['unidad', 'kg', 'g', 'litro', 'ml'])],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'fecha_vencimiento' => ['nullable', 'date_format:Y-m-d'], 'estado' => ['required', 'boolean'],
        ];
    }
}
