<?php

namespace App\Support;

final class Moneda
{
    public static function mostrar(string|int|float|null $valor): string
    {
        return number_format((float) ($valor ?? 0), 2, ',', '.');
    }
}
