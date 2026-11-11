<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tienePermiso('pos.usar') ?? false;
    }

    public function rules(): array
    {
        return [
            'clave_idempotencia' => ['required', 'uuid'],
            'cliente_id' => ['required', 'integer'],
            'metodo_pago_id' => ['required', 'integer'],
            'pedido_id' => ['nullable', 'integer'],
            'descuento' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'recibido' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'notas' => ['nullable', 'string', 'max:2000'],
            'detalles' => ['required', 'array', 'min:1', 'max:100'],
            'detalles.*.producto_id' => ['required', 'integer', 'distinct'],
            'detalles.*.cantidad' => ['required', 'integer', 'min:1', 'max:9999'],
            'detalles.*.indicaciones' => ['sometimes', 'array', 'max:200'],
            'detalles.*.indicaciones.*' => ['array:unidad,version,insumos,nota'],
            'detalles.*.indicaciones.*.unidad' => ['required', 'integer', 'min:1', 'max:9999'],
            'detalles.*.indicaciones.*.version' => ['required', 'string', 'size:64'],
            'detalles.*.indicaciones.*.nota' => ['nullable', 'string', 'max:500'],
            'detalles.*.indicaciones.*.insumos' => ['required', 'array', 'min:1', 'max:100'],
            'detalles.*.indicaciones.*.insumos.*' => ['array:insumo_id,cantidad'],
            'detalles.*.indicaciones.*.insumos.*.insumo_id' => ['required', 'integer'],
            'detalles.*.indicaciones.*.insumos.*.cantidad' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
            'detalles.*.precio_esperado' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ];
    }
}
