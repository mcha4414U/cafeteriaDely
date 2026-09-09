<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Compra;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ServicioInventario;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ComprasInventarioTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $administrador;

    private Producto $producto;

    private Proveedor $proveedor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->administrador = Usuario::create(['nombre' => 'Administración', 'email' => 'compras@dely.test', 'password' => 'CafeSeguro12345', 'estado' => true]);
        $this->administrador->roles()->attach(Rol::where('nombre', 'Administrador')->firstOrFail());
        $categoria = Categoria::create(['nombre' => 'Insumos', 'estado' => true]);
        $this->producto = Producto::create(['nombre' => 'Café molido', 'codigo' => 'INS-01', 'categoria_id' => $categoria->id, 'precio_compra' => '10.00', 'precio_venta' => '15.00', 'stock_minimo' => 5, 'unidad_medida' => 'kg', 'estado' => true]);
        $this->proveedor = Proveedor::create(['nombre' => 'Proveedor Dely', 'estado' => true]);
        $this->actingAs($this->administrador);
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + ['clave_idempotencia' => (string) Str::uuid(), 'proveedor_id' => $this->proveedor->id, 'fecha' => now()->format('Y-m-d'), 'detalles' => [['producto_id' => $this->producto->id, 'cantidad' => '2.500', 'costo_unitario' => '10.25']]];
    }

    public function test_pantallas_de_compras_e_inventario(): void
    {
        foreach (['/compras', '/compras/crear', '/inventario', '/inventario/movimientos'] as $ruta) {
            $this->get($ruta)->assertOk();
        }
    }

    public function test_compra_calcula_total_y_actualiza_stock_con_movimiento(): void
    {
        $this->post('/compras', $this->datos() + ['total' => 1, 'usuario_id' => 999])->assertRedirect();
        $compra = Compra::firstOrFail();
        $this->assertSame('25.63', $compra->total);
        $this->assertSame('2.500', $this->producto->fresh()->stock_actual);
        $this->assertSame('10.25', $this->producto->fresh()->precio_compra);
        $this->assertSame($this->administrador->id, $compra->usuario_id);
        $this->assertDatabaseHas('movimientos_inventario', ['compra_id' => $compra->id, 'producto_id' => $this->producto->id, 'tipo' => 'entrada_compra', 'stock_anterior' => 0, 'stock_nuevo' => 2.5, 'cantidad' => 2.5]);
        $this->get('/compras/'.$compra->id)->assertOk()->assertSee('25.63')->assertSee('Café molido');
        $this->get('/inventario')->assertOk()->assertSee('2.500')->assertSee('Stock bajo');
        $this->get('/inventario/movimientos?compra_id='.$compra->id)->assertOk()->assertSee($compra->numero);
    }

    public function test_reintentar_no_duplica_compra_ni_stock(): void
    {
        $datos = $this->datos();
        $this->post('/compras', $datos)->assertRedirect();
        $this->post('/compras', $datos)->assertRedirect('/compras/'.Compra::firstOrFail()->id);
        $this->assertDatabaseCount('compras', 1);
        $this->assertDatabaseCount('detalle_compras', 1);
        $this->assertDatabaseCount('movimientos_inventario', 1);
        $this->assertSame('2.500', $this->producto->fresh()->stock_actual);
    }

    public function test_misma_clave_con_datos_distintos_se_rechaza(): void
    {
        $datos = $this->datos();
        $this->post('/compras', $datos);
        $datos['detalles'][0]['cantidad'] = '3.000';
        $this->post('/compras', $datos)->assertSessionHasErrors('clave_idempotencia');
        $this->assertSame('2.500', $this->producto->fresh()->stock_actual);
    }

    public function test_rechaza_cantidades_invalidas_y_productos_repetidos(): void
    {
        $datos = $this->datos();
        $datos['detalles'][0]['cantidad'] = '0';
        $this->post('/compras', $datos)->assertSessionHasErrors('detalles.0.cantidad');
        $datos = $this->datos();
        $datos['detalles'][] = $datos['detalles'][0];
        $this->post('/compras', $datos)->assertSessionHasErrors('detalles.0.producto_id');
        $datos = $this->datos();
        $datos['detalles'][0]['costo_unitario'] = '1.234';
        $this->post('/compras', $datos)->assertSessionHasErrors('detalles.0.costo_unitario');
        $this->assertDatabaseCount('compras', 0);
    }

    public function test_no_compra_proveedor_o_producto_inactivo(): void
    {
        $this->proveedor->update(['estado' => false]);
        $this->post('/compras', $this->datos())->assertSessionHasErrors('proveedor_id');
        $this->proveedor->update(['estado' => true]);
        $this->producto->delete();
        $this->post('/compras', $this->datos())->assertSessionHasErrors('detalles.0.producto_id');
        $this->assertDatabaseCount('compras', 0);
    }

    public function test_limite_stock_no_guarda_compra_parcial(): void
    {
        $this->producto->forceFill(['stock_actual' => '999999999.000'])->save();
        $this->post('/compras', $this->datos())->assertSessionHasErrors('detalles');
        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('detalle_compras', 0);
        $this->assertSame('999999999.000', $this->producto->fresh()->stock_actual);
    }

    public function test_total_excesivo_se_rechaza(): void
    {
        $datos = $this->datos();
        $datos['detalles'][0]['cantidad'] = '100';
        $datos['detalles'][0]['costo_unitario'] = '9999999999.99';
        $this->post('/compras', $datos)->assertSessionHasErrors('detalles');
        $this->assertDatabaseCount('compras', 0);
    }

    public function test_fallo_de_movimiento_revierte_toda_la_compra(): void
    {
        MovimientoInventario::creating(function () {
            throw new \RuntimeException('Fallo simulado');
        });
        $this->withoutExceptionHandling();
        try {
            $this->post('/compras', $this->datos());
            $this->fail('Debió fallar el movimiento');
        } catch (\RuntimeException $error) {
            $this->assertSame('Fallo simulado', $error->getMessage());
        } finally {
            MovimientoInventario::flushEventListeners();
        }
        $this->assertDatabaseCount('compras', 0);
        $this->assertDatabaseCount('detalle_compras', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
        $this->assertSame('0.000', $this->producto->fresh()->stock_actual);
    }

    public function test_historial_conserva_nombres_y_productos_archivados(): void
    {
        $this->post('/compras', $this->datos());
        $compra = Compra::firstOrFail();
        $this->producto->update(['nombre' => 'Nombre nuevo']);
        $this->producto->delete();
        $this->proveedor->update(['nombre' => 'Proveedor cambiado']);
        $this->proveedor->delete();
        $this->get('/compras/'.$compra->id)->assertOk()->assertSee('Café molido')->assertSee('Proveedor Dely');
        $this->get('/inventario?estado=archivado')->assertOk()->assertSee('Nombre nuevo')->assertSee('2.500');
        $this->get('/inventario/movimientos')->assertOk()->assertSee($compra->numero);
    }

    public function test_entradas_y_salidas_no_se_cancelan(): void
    {
        $this->post('/compras', $this->datos());
        app(ServicioInventario::class)->ajustar($this->producto, ['stock_esperado' => '2.500', 'stock_nuevo' => '1.250', 'motivo' => 'Conteo físico'], $this->administrador->id);
        $respuesta = $this->get('/inventario')->assertOk();
        $registro = $respuesta->viewData('productos')->first();
        $this->assertEquals(2.5, $registro->entradas);
        $this->assertEquals(1.25, $registro->salidas);
        $this->assertSame('1.250', $registro->stock_actual);
    }

    public function test_filtros_fecha_y_compra(): void
    {
        $this->post('/compras', $this->datos(['fecha' => '2025-01-15']));
        $compra = Compra::firstOrFail();
        $this->get('/compras?hasta=2025-01-31')->assertOk()->assertSee($compra->numero);
        $this->get('/compras?desde=2025-02-01')->assertOk()->assertDontSee($compra->numero);
        $this->get('/compras?desde=2025-02-01&hasta=2025-01-01')->assertSessionHasErrors('hasta');
        $this->get('/inventario/movimientos?tipo=ajuste')->assertOk()->assertDontSee($compra->numero);
        $this->get('/inventario/movimientos?hasta=2025-01-31')->assertOk()->assertDontSee($compra->numero);
    }

    public function test_cajero_no_puede_ver_ni_registrar_compras(): void
    {
        $cajero = Usuario::create(['nombre' => 'Caja', 'email' => 'caja@dely.test', 'password' => 'CafeSeguro12345', 'estado' => true]);
        $cajero->roles()->attach(Rol::where('nombre', 'Cajero')->firstOrFail());
        $this->flushSession();
        $this->actingAs($cajero);
        foreach (['/compras', '/compras/crear', '/inventario', '/inventario/movimientos'] as $ruta) {
            $this->get($ruta)->assertForbidden();
        }
        $this->post('/compras',$this->datos())->assertForbidden();
        $this->assertDatabaseCount('compras',0);
    }
}
