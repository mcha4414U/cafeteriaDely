<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AjustarStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tienePermiso('inventario.gestionar') ?? false;
    }

    public function rules(): array
    {
        return ['stock_nuevo' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'], 'stock_esperado' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'], 'motivo' => ['required', 'string', 'max:255']];
    }
}
