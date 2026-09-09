<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EsquemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_catalogos_iniciales_no_se_duplican(): void
    {
        $this->seed();
        $this->seed();
        $this->assertDatabaseCount('metodos_pago', 4);
        $this->assertDatabaseCount('clientes', 1);
        $this->assertDatabaseHas('clientes', ['nombre' => 'Cliente genérico']);
    }

    public function test_no_se_permite_un_producto_con_categoria_inexistente(): void
    {
        $this->expectException(QueryException::class);
        DB::table('productos')->insert(['categoria_id' => 999, 'nombre' => 'Café', 'codigo' => 'CAFE-01']);
    }

    public function test_no_se_elimina_una_categoria_con_productos(): void
    {
        $categoria = DB::table('categorias')->insertGetId(['nombre' => 'Café']);
        DB::table('productos')->insert(['categoria_id' => $categoria, 'nombre' => 'Espresso', 'codigo' => 'ESP-01']);
        $this->expectException(QueryException::class);
        DB::table('categorias')->where('id', $categoria)->delete();
    }

    public function test_no_se_duplican_alertas_activas(): void
    {
        $categoria = DB::table('categorias')->insertGetId(['nombre' => 'Café']);
        $producto = DB::table('productos')->insertGetId(['categoria_id' => $categoria, 'nombre' => 'Espresso', 'codigo' => 'ESP-01']);
        $alerta = ['producto_id' => $producto, 'tipo_alerta' => 'stock_bajo', 'mensaje' => 'Reponer', 'clave_activa' => $producto.':stock_bajo'];
        DB::table('alertas_stock')->insert($alerta);
        $this->expectException(QueryException::class);
        DB::table('alertas_stock')->insert($alerta);
    }
}

