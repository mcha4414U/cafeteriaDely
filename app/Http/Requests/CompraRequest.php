<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tienePermiso('compras.gestionar') ?? false;
    }

    public function rules(): array
    {
        return [
            'clave_idempotencia' => ['required', 'uuid'], 'proveedor_id' => ['required', 'integer', Rule::exists('proveedores', 'id')->whereNull('deleted_at')->where('estado', true)],
            'fecha' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'observaciones' => ['nullable', 'string', 'max:3000'],
            'detalles' => ['required', 'array', 'min:1', 'max:100'],
            'detalles.*.producto_id' => ['required', 'integer', 'distinct', Rule::exists('productos', 'id')->whereNull('deleted_at')->where('estado', true)],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.001', 'max:999999999.999', 'decimal:0,3'],
            'detalles.*.costo_unitario' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ];
    }

    public function messages(): array
    {
        return ['detalles.required' => 'Agrega al menos un producto.', 'detalles.max' => 'Una compra admite hasta 100 productos.',
            'detalles.*.producto_id.distinct' => 'No repitas productos; suma la cantidad en una sola fila.',
            'detalles.*.producto_id.exists' => 'Selecciona productos activos y sin archivar.'];
    }

    public function attributes(): array
    {
        return ['proveedor_id' => 'proveedor', 'fecha' => 'fecha de compra', 'detalles.*.producto_id' => 'producto', 'detalles.*.cantidad' => 'cantidad', 'detalles.*.costo_unitario' => 'costo unitario', 'clave_idempotencia' => 'identificador de la compra'];
    }
}
