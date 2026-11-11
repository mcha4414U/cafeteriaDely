<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use App\Support\Cantidad;
use Database\Seeders\RolesPermisosSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormatoStockInternoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function producto(array $datos = []): Producto
    {
        return Producto::create($datos + ['nombre' => 'Producto de prueba', 'tipo' => 'venta',
            'estado' => true, 'precio_venta' => '15.00', 'precio_compra' => '0.00',
            'stock_actual' => '15.000', 'stock_minimo' => '0.000', 'unidad_medida' => 'unidad']);
    }

    public function test_stock_se_presenta_sin_ceros_y_no_cambia_valores_ni_precios(): void
    {
        $this->seed(RolesPermisosSeeder::class);
        $usuario = Usuario::create(['nombre' => 'Prueba', 'email' => 'publica@example.test', 'password' => 'ClavePrueba123', 'estado' => true]);
        $usuario->roles()->attach(Rol::where('nombre', 'Administrador')->firstOrFail());
        $this->actingAs($usuario);
        foreach (['15.000' => '15', '72.000' => '72', '20.500' => '20,5', '20.250' => '20,25', '20.125' => '20,125'] as $stock => $visible) {
            $producto = $this->producto(['nombre' => 'Stock '.$visible, 'stock_actual' => $stock]);
            $this->get('/punto-de-venta')->assertOk()->assertSee('Stock: '.$visible.' unidad')->assertSee('Bs. 15,00');
            $this->get('/productos/'.$producto->id)->assertOk()->assertSee($visible.' unidad');
            $this->assertSame($stock, $producto->fresh()->stock_actual);
            $this->assertSame($visible, Cantidad::mostrar($stock));
        }
        $this->get('/inventario')->assertOk();
        $this->get('/productos')->assertOk();
        $this->assertSame('2,5', Cantidad::mostrar(2.5));
        $this->assertSame('100', Cantidad::mostrar('100'));
        $this->assertSame('0', Cantidad::mostrar('0.000'));
        $this->assertSame('999.999.999,999', Cantidad::mostrar('999999999.999'));
    }

}

