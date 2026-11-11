<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductoRequest extends FormRequest
{
    public const UNIDADES = ['unidad', 'g', 'kg', 'ml', 'L'];
    public const PRESENTACIONES = ['Bolsa', 'Botella', 'Paquete', 'Caja', 'Lata', 'Saco', 'Bidón'];

    protected function prepareForValidation(): void
    {
        if ($this->routeIs('productos.*') && $this->has('modo_costo')) {
            // El modo de cálculo no cambia la unidad física ni las recetas existentes.
            $producto = $this->route('producto');
            $this->merge(['unidad_medida' => $producto ? $producto->unidad_medida : 'unidad']);
        }
    }

    public function validated($key = null, $default = null)
    {
        $datos = parent::validated();
        if ($this->routeIs('insumos.*') && ($datos['presentacion'] ?? null) === 'Otro') {
            $datos['presentacion'] = trim($datos['otra_presentacion']);
        }
        unset($datos['otra_presentacion']);
        if ($this->routeIs('productos.*') && ($datos['modo_costo'] ?? null) === 'cantidad') {
            $cantidad = $this->route('producto') ? $datos['cantidad_compra'] : ($datos['stock_actual'] ?? $datos['stock_inicial']);
            $costo = bcadd(bcdiv((string) $datos['precio_total_compra'], (string) $cantidad, 3), '0.005', 2);
            if (bccomp($costo, '9999999999.99', 2) > 0) {
                throw \Illuminate\Validation\ValidationException::withMessages(['precio_total_compra' => 'El costo unitario supera el máximo permitido.']);
            }
            $datos['precio_compra'] = $costo;
        }
        unset($datos['modo_costo'], $datos['precio_total_compra'], $datos['cantidad_compra']);
        return data_get($datos, $key, $default);
    }

    public function authorize(): bool
    {
        return $this->user()?->tienePermiso('productos.gestionar') ?? false;
    }

    public function rules(): array
    {
        $reglas = [
            'nombre' => ['required', 'string', 'max:255'], 'descripcion' => ['nullable', 'string', 'max:3000'],
            'categoria_id' => ['nullable', 'integer', Rule::exists('categorias', 'id')->whereNull('deleted_at')->where('estado', true)],
            'codigo' => ['nullable', 'string', 'max:100', Rule::unique('productos')->ignore($this->route('producto'))],
            'tipo' => ['required', Rule::in($this->routeIs('insumos.*') ? ['insumo', 'ambos'] : ['venta', 'ambos'])],
            'precio_compra' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'requiere_receta' => ['sometimes', 'boolean'],
            'precio_venta' => [Rule::requiredIf($this->input('tipo') !== 'insumo'), 'nullable', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'stock_inicial' => [$this->route('producto') ? 'prohibited' : 'required_without_all:stock_actual,unidades_stock', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
            'stock_actual' => ['required_without_all:stock_inicial,unidades_stock', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
            'stock_esperado' => ['nullable', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
            'motivo_stock' => ['nullable', 'string', 'max:255'],
            'stock_minimo' => ['required', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
            'unidad_medida' => [Rule::requiredIf(in_array($this->input('tipo'), ['insumo', 'ambos'])), 'required_with:contenido_por_unidad', 'nullable', 'string', 'max:100'],
            'contenido_por_unidad' => ['nullable', 'required_with:unidades_stock', 'numeric', 'min:0.001', 'max:999999999.999', 'decimal:0,3', Rule::prohibitedIf($this->input('tipo') !== 'insumo')],
            'presentacion' => ['nullable', 'string', 'max:100', Rule::prohibitedIf($this->input('tipo') !== 'insumo')],
            'unidades_stock' => ['nullable', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3', Rule::prohibitedIf($this->input('tipo') !== 'insumo')],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'fecha_vencimiento' => ['nullable', 'date_format:Y-m-d'], 'estado' => ['required', 'boolean'],
        ];
        if ($this->routeIs('insumos.*')) {
            $producto = $this->route('producto');
            $reglas['unidad_medida'][] = Rule::in(array_filter([...self::UNIDADES, $producto?->unidad_medida]));
            $reglas['presentacion'][] = function ($atributo, $valor, $fallar) use ($producto) {
                if (!is_string($valor) || ($valor !== 'Otro' && $valor !== $producto?->presentacion && !in_array(mb_strtolower($valor), array_map('mb_strtolower', self::PRESENTACIONES), true))) {
                    $fallar('Selecciona una presentación permitida o la opción Otro.');
                }
            };
            $reglas['otra_presentacion'] = ['exclude_unless:presentacion,Otro', 'required', 'string', 'max:100', function ($atributo, $valor, $fallar) {
                if (!is_string($valor) || trim($valor) === '') $fallar('Especifica otra presentación; no puede contener solo espacios.');
            }];
        }
        if ($this->routeIs('productos.*')) {
            $reglas['modo_costo'] = ['sometimes', 'required', Rule::in(['unidad', 'cantidad'])];
            $reglas['precio_total_compra'] = ['exclude_unless:modo_costo,cantidad', 'required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'];
            if ($this->input('modo_costo') === 'cantidad') {
                $reglas['precio_compra'] = ['exclude'];
                if ($this->route('producto')) {
                    $reglas['cantidad_compra'] = ['required', 'numeric', 'min:0.001', 'max:999999999.999', 'decimal:0,3'];
                } else {
                    $campo = $this->has('stock_actual') ? 'stock_actual' : 'stock_inicial';
                    $reglas[$campo] = ['required', 'numeric', 'min:0.001', 'max:999999999.999', 'decimal:0,3'];
                }
            }
        }
        return $reglas;
    }
}
