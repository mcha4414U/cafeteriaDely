<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecetasPosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed();
        $usuario = Usuario::create(['nombre' => 'Ana', 'email' => 'ana@example.test', 'password' => 'ClaveCafe1234', 'estado' => true]);
        $usuario->roles()->attach(Rol::where('nombre', 'Administrador')->firstOrFail());
        $this->actingAs($usuario);
    }

    private function datos(array $cambios = []): array
    {
        return $cambios + ['nombre' => 'Cappuccino', 'tipo' => 'venta', 'categoria_id' => null, 'codigo' => null, 'precio_compra' => null, 'precio_venta' => '16.00', 'stock_actual' => '0.000', 'stock_minimo' => '0', 'unidad_medida' => 'unidad', 'estado' => 1];
    }

    private function producto(array $cambios = []): Producto
    {
        $ruta = ($cambios['tipo'] ?? 'venta') === 'insumo' ? '/insumos' : '/productos';
        $this->post($ruta, $this->datos($cambios))->assertRedirect($ruta);

        return Producto::latest('id')->firstOrFail();
    }

    private function receta(Producto $insumo, array $cambios = []): array
    {
        return $cambios + ['nombre' => 'Receta Cappuccino', 'estado' => 1, 'detalles' => [['insumo_id' => $insumo->id, 'cantidad' => '200.000', 'unidad_medida' => $insumo->unidad_medida]]];
    }

    public function test_crear_tipos_sin_categoria_ni_codigo_y_stock_auditado(): void
    {
        $insumo = $this->producto(['nombre' => 'Leche', 'tipo' => 'insumo', 'unidad_medida' => 'ml', 'stock_actual' => '1000.125']);
        $venta = $this->producto();
        $this->assertNull($venta->codigo);
        $this->assertNull($venta->categoria_id);
        $this->assertSame('0.00', $venta->precio_compra);
        $this->assertSame('1000.125', $insumo->stock_actual);
        $this->assertDatabaseHas('movimientos_inventario', ['producto_id' => $insumo->id, 'tipo' => 'ajuste', 'stock_nuevo' => '1000.125']);
        $this->post('/productos', $this->datos(['tipo' => 'otro']))->assertSessionHasErrors('tipo');
    }

    public function test_guardar_editar_receta_sin_duplicar_y_sin_mover_stock(): void
    {
        $leche = $this->producto(['nombre' => 'Leche', 'tipo' => 'insumo', 'unidad_medida' => 'ml', 'stock_actual' => '1000']);
        $cafe = $this->producto(['nombre' => 'Café molido', 'tipo' => 'insumo', 'unidad_medida' => 'g', 'stock_actual' => '100']);
        $venta = $this->producto();
        $ruta = '/productos/'.$venta->id.'/receta';
        $this->get($ruta)->assertOk()->assertSee('Leche')->assertSee('Agregar insumo');
        $datos = $this->receta($leche);
        $datos['detalles'][] = ['insumo_id' => $cafe->id, 'cantidad' => '10', 'unidad_medida' => 'g'];
        $this->put($ruta, $datos)->assertRedirect($ruta);
        $this->put($ruta, $datos)->assertRedirect($ruta);
        $this->assertDatabaseCount('recetas', 1);
        $this->assertDatabaseCount('detalle_recetas', 2);
        $this->assertSame('200.000', $venta->receta->detalles->first()->cantidad);
        $this->put($ruta, $this->receta($leche, ['nombre' => 'Receta nueva']))->assertRedirect($ruta);
        $this->assertDatabaseCount('detalle_recetas', 1);
        $this->assertSame('1000.000', $leche->fresh()->stock_actual);
        $this->assertDatabaseCount('movimientos_inventario', 2);
    }

    public function test_rechaza_duplicados_unidades_incorrectas_y_guarda_receta_anterior(): void
    {
        $insumo = $this->producto(['tipo' => 'insumo', 'unidad_medida' => 'ml']);
        $venta = $this->producto();
        $ruta = '/productos/'.$venta->id.'/receta';
        $datos = $this->receta($insumo);
        $this->put($ruta, $datos)->assertRedirect($ruta);
        $repetidos = $datos;
        $repetidos['detalles'][] = $datos['detalles'][0];
        $this->put($ruta, $repetidos)->assertSessionHasErrors('detalles.0.insumo_id');
        $datos['detalles'][0]['unidad_medida'] = 'L';
        $this->put($ruta, $datos)->assertSessionHasErrors('detalles.0.unidad_medida');
        $datos['detalles'][0]['cantidad'] = 0;
        $this->put($ruta, $datos)->assertSessionHasErrors('detalles.0.cantidad');
        $this->assertDatabaseHas('detalle_recetas', ['insumo_id' => $insumo->id, 'unidad_medida' => 'ml', 'cantidad' => 200]);
        $this->assertDatabaseCount('detalle_recetas', 1);
    }

    public function test_rechaza_ingredientes_de_venta_inactivos_y_al_mismo_producto(): void
    {
        $venta = $this->producto();
        $ruta = '/productos/'.$venta->id.'/receta';
        $this->put($ruta, $this->receta($venta))->assertSessionHasErrors('detalles.0.insumo_id');
        $ambos = $this->producto(['tipo' => 'ambos']);
        $this->put('/productos/'.$ambos->id.'/receta', $this->receta($ambos))->assertSessionHasErrors('detalles.0.insumo_id');
        $insumo = $this->producto(['tipo' => 'insumo', 'estado' => 0]);
        $this->put($ruta, $this->receta($insumo))->assertSessionHasErrors('detalles.0.insumo_id');
        $this->assertDatabaseCount('recetas', 0);
    }

    public function test_no_cambia_unidad_o_tipo_de_insumo_usado_en_receta(): void
    {
        $insumo = $this->producto(['tipo' => 'insumo', 'unidad_medida' => 'ml']);
        $venta = $this->producto();
        $this->put('/productos/'.$venta->id.'/receta', $this->receta($insumo))->assertRedirect();
        $this->put('/insumos/'.$insumo->id, $this->datos(['tipo' => 'insumo', 'unidad_medida' => 'L']))->assertSessionHasErrors('unidad_medida');
        $this->put('/insumos/'.$insumo->id, $this->datos(['tipo' => 'venta', 'unidad_medida' => 'ml']))->assertSessionHasErrors('tipo');
        $this->put('/productos/'.$venta->id, $this->datos(['tipo' => 'insumo']))->assertSessionHasErrors('tipo');
    }

    public function test_pos_filtra_insumos_inactivos_archivados_y_conserva_stock(): void
    {
        $venta = $this->producto(['nombre' => 'Cappuccino visible']);
        $this->producto(['nombre' => 'Chocolate ambos', 'tipo' => 'ambos']);
        $this->producto(['nombre' => 'Leche oculta', 'tipo' => 'insumo']);
        $this->producto(['nombre' => 'Venta inactiva', 'estado' => 0]);
        $archivado = $this->producto(['nombre' => 'Venta archivada']);
        $archivado->delete();
        $this->get('/punto-de-venta')->assertOk()->assertSee('Cappuccino visible')->assertSee('Chocolate ambos')
            ->assertDontSee('Leche oculta')->assertDontSee('Venta inactiva')->assertDontSee('Venta archivada');
        $this->assertSame('0.000', $venta->fresh()->stock_actual);
        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('movimientos_inventario', 0);
    }

    public function test_cajero_puede_ver_pos_pero_no_editar_recetas(): void
    {
        $producto = $this->producto();
        $usuario = auth()->user();
        $usuario->roles()->sync([Rol::where('nombre', 'Cajero')->firstOrFail()->id]);
        $usuario->unsetRelation('roles');
        $this->get('/punto-de-venta')->assertOk();
        $this->get('/productos/'.$producto->id.'/receta')->assertForbidden();
        $this->put('/productos/'.$producto->id.'/receta', [])->assertForbidden();
        auth()->logout();
        $this->get('/punto-de-venta')->assertRedirect('/ingresar');
    }

    public function test_editar_stock_exige_motivo_y_rechaza_conteo_desactualizado(): void
    {
        $producto = $this->producto(['stock_actual' => '10']);
        $ruta = '/productos/'.$producto->id;
        $this->put($ruta, $this->datos(['stock_actual' => '8']))->assertSessionHasErrors('stock_actual');
        $datos = $this->datos(['stock_actual' => '8', 'stock_esperado' => '10', 'motivo_stock' => 'Conteo físico']);
        $this->put($ruta, $datos)->assertRedirect('/productos');
        $this->assertDatabaseHas('movimientos_inventario', ['producto_id' => $producto->id, 'stock_anterior' => 10, 'stock_nuevo' => 8, 'motivo' => 'Conteo físico']);
        $datos['stock_actual'] = '7';
        $this->put($ruta, $datos)->assertSessionHasErrors('stock_nuevo');
        $this->assertSame('8.000', $producto->fresh()->stock_actual);
    }

    public function test_reemplaza_imagen_publica_y_conserva_compatibilidad_privada(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6XcAAAAASUVORK5CYII=');
        $producto = $this->producto();
        Storage::disk('local')->put('productos/anterior.png', $png);
        $producto->update(['imagen' => 'productos/anterior.png']);
        $this->get('/productos/'.$producto->id.'/imagen')->assertOk();
        $this->put('/productos/'.$producto->id, $this->datos(['imagen' => UploadedFile::fake()->createWithContent('nueva.png', $png)]))->assertRedirect('/productos');
        Storage::disk('public')->assertExists($producto->fresh()->imagen);
        Storage::disk('local')->assertMissing('productos/anterior.png');
        $anterior = $producto->fresh()->imagen;
        $this->put('/productos/'.$producto->id, $this->datos())->assertRedirect('/productos');
        $this->assertSame($anterior,$producto->fresh()->imagen);
        $this->get('/productos/'.$producto->id.'/imagen')->assertOk();
    }
}
