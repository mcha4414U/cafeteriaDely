<?php

namespace Tests\Unit;

use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Support\Cantidad;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

#[Group('unitarias-academicas')]
class ReglasDominioTest extends TestCase
{
    #[TestDox('PU-01: formato de cantidades sin redondear ni perder decimales')]
    public function test_formato_de_cantidades_aislado(): void
    {
        foreach ([
            ['15.000', '15'], ['72.000', '72'], ['20.500', '20,5'],
            ['20.250', '20,25'], ['20.125', '20,125'], ['0.000', '0'],
            ['999999999.999', '999.999.999,999'], [100, '100'], [null, '0'], [2.5, '2,5'], ['2000.000', '2.000'], ['0.250', '0,25'], ['150.125', '150,125'], ['23.841437', '23,841437'],
        ] as [$entrada, $esperado]) {
            $this->assertSame($esperado, Cantidad::mostrar($entrada));
        }
    }

    #[TestDox('PU-02: clasificación de stock agotado, bajo y normal en sus límites')]
    public function test_estado_de_stock_sin_base_de_datos(): void
    {
        foreach ([
            ['0.000', '5.000', 'Agotado'],
            ['0.001', '5.000', 'Stock bajo'],
            ['5.000', '5.000', 'Stock bajo'],
            ['5.001', '5.000', 'Normal'],
            ['0.000', '0.000', 'Agotado'],
            ['0.001', '0.000', 'Normal'],
        ] as [$stock, $minimo, $esperado]) {
            // Instancia en memoria: no se llama a save(), consultas ni contenedor Laravel.
            $producto = new Producto(['stock_actual' => $stock, 'stock_minimo' => $minimo]);
            $this->assertSame($esperado, $producto->estadoStock());
        }
    }

    #[TestDox('PU-03: etiquetas de movimientos y rama por defecto')]
    public function test_etiquetas_de_movimientos_sin_base_de_datos(): void
    {
        foreach ([
            'entrada_compra' => 'Entrada por compra',
            'salida_venta' => 'Salida por venta',
            'salida_desperdicio' => 'Salida por desperdicio',
            'ajuste' => 'Ajuste manual',
            'tipo_desconocido' => 'tipo_desconocido',
        ] as $tipo => $esperado) {
            $movimiento = new MovimientoInventario;
            $movimiento->tipo = $tipo;
            $this->assertSame($esperado, $movimiento->etiquetaTipo());
        }
    }
}
