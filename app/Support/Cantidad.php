<?php

namespace App\Support;

final class Cantidad
{
    // Canónico para inputs number y cálculos: sin miles, punto decimal.
    public static function entrada(string|int|float|null $valor): string
    {
        $texto = (string) ($valor ?? 0);
        if (! preg_match('/^([+-]?)(\d+)(?:\.(\d*))?$/D', $texto, $partes)) return $texto;
        $entero = ltrim($partes[2], '0') ?: '0';
        $decimal = rtrim($partes[3] ?? '', '0');
        $signo = $partes[1] === '-' && ($entero !== '0' || $decimal !== '') ? '-' : '';
        return $signo.$entero.($decimal !== '' ? '.'.$decimal : '');
    }

    // Presentación es-BO sin convertir a float ni perder precisión.
    public static function mostrar(string|int|float|null $valor): string
    {
        $texto = self::entrada($valor);
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/D', $texto, $partes)) return $texto;
        $entero = preg_replace('/\B(?=(\d{3})+(?!\d))/', '.', $partes[2]);
        return $partes[1].$entero.(isset($partes[3]) ? ','.$partes[3] : '');
    }
}
