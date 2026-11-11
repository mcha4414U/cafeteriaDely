<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class PresentacionInsumo
{
    public static function total(string $contenido, string $unidades): string
    {
        if (bccomp($contenido, '0', 3) <= 0 || bccomp($unidades, '0', 3) < 0) {
            throw ValidationException::withMessages(['unidades_stock' => 'El contenido debe ser positivo y las unidades no pueden ser negativas.']);
        }
        $total = bcmul($contenido, $unidades, 6);
        if (bccomp($total, '999999999.999', 6) > 0 || bccomp($total, bcadd($total, '0', 3), 6) !== 0) {
            throw ValidationException::withMessages(['unidades_stock' => 'El stock calculado supera el límite o requiere más de tres decimales. Usa cantidades exactas en la unidad de medida.']);
        }
        return bcadd($total, '0', 3);
    }
}
