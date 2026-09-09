<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogosTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $administrador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(DatabaseSeeder::class);
        $this->administrador = $this->crearUsuario('Administrador');
        $this->actingAs($this->administrador);
    }

    private function crearUsuario(string $rol): Usuario
    {
        $usuario = Usuario::create(['nombre' => $rol, 'email' => uniqid().'@dely.test', 'password' => 'ClaveCafe1234', 'estado' => true]);
        $usuario->roles()->attach(Rol::where('nombre', $rol)->firstOrFail());

        return $usuario;
    }

    private function datosProducto(array $cambios = []): array
    {
        return $cambios + ['nombre' => 'Cappuccino', 'codigo' => 'CAF-001', 'categoria_id' => Categoria::firstOrCreate(['nombre' => 'Café'], ['estado' => true])->id,
            'precio_compra' => '8.50', 'precio_venta' => '16.00', 'stock_inicial' => '12.500', 'stock_minimo' => '5', 'unidad_medida' => 'unidad', 'estado' => 1];
    }

    private function crearProducto(): Producto
    {
        $this->post('/productos', $this->datosProducto())->assertRedirect('/productos');

        return Producto::firstOrFail();
    }

    public function test_listados_y_formularios_funcionan(): void
    {
        foreach (['categorias', 'clientes', 'proveedores', 'productos'] as $modulo) {
            $this->get('/'.$modulo)->assertOk();
            $this->get('/'.$modulo.'/crear')->assertOk();
        }
    }

    public function test_categoria_crud_y_restauracion(): void
    {
        $this->post('/categorias', ['nombre' => 'Pastelería', 'descripcion' => 'Dulces', 'estado' => 1])->assertRedirect('/categorias');
        $categoria = Categoria::firstOrFail();
        $this->get('/categorias/'.$categoria->id.'/editar')->assertOk()->assertSee('Pastelería');
        $this->put('/categorias/'.$categoria->id, ['nombre' => 'Repostería', 'estado' => 1])->assertRedirect('/categorias');
        $this->delete('/categorias/'.$categoria->id)->assertRedirect('/categorias');
        $this->assertSoftDeleted('categorias', ['id' => $categoria->id]);
        $this->get('/categorias?estado=archivado')->assertOk()->assertSee('Repostería');
        $this->post('/categorias/'.$categoria->id.'/restaurar')->assertRedirect('/categorias');
        $this->assertNotSoftDeleted('categorias', ['id' => $categoria->id]);
    }

    public function test_categoria_con_productos_no_se_desactiva_ni_archiva(): void
    {
        $producto = $this->crearProducto();
        $this->delete('/categorias/'.$producto->categoria_id)->assertSessionHasErrors('estado');
        $this->put('/categorias/'.$producto->categoria_id, ['nombre' => 'Café', 'estado' => 0])->assertSessionHasErrors('estado');
        $this->assertNotSoftDeleted('categorias', ['id' => $producto->categoria_id]);
    }

    public function test_cliente_generico_esta_protegido(): void
    {
        $cliente = Cliente::where('es_generico', true)->firstOrFail();
        $this->put('/clientes/'.$cliente->id, ['nombre' => 'Otro', 'estado' => 0])->assertSessionHasErrors('nombre');
        $this->delete('/clientes/'.$cliente->id)->assertSessionHasErrors('nombre');
        $this->assertDatabaseHas('clientes', ['id' => $cliente->id, 'nombre' => 'Cliente genérico', 'estado' => true, 'deleted_at' => null]);
    }

    public function test_clientes_y_proveedores_crud(): void
    {
        foreach (['clientes' => Cliente::class, 'proveedores' => Proveedor::class] as $modulo => $modelo) {
            $this->post('/'.$modulo, ['nombre' => 'Ana', 'telefono' => '70000000', 'estado' => 1])->assertRedirect('/'.$modulo);
            $registro = $modelo::where('nombre', 'Ana')->firstOrFail();
            $this->get('/'.$modulo.'/'.$registro->id.'/editar')->assertOk();
            $this->put('/'.$modulo.'/'.$registro->id, ['nombre' => 'Ana María', 'estado' => 0])->assertRedirect('/'.$modulo);
            $this->delete('/'.$modulo.'/'.$registro->id)->assertRedirect('/'.$modulo);
            $this->assertSoftDeleted($modulo, ['id' => $registro->id]);
            $this->post('/'.$modulo.'/'.$registro->id.'/restaurar')->assertRedirect('/'.$modulo);
            $this->assertNotSoftDeleted($modulo, ['id' => $registro->id]);
        }
    }

    public function test_producto_registra_stock_inicial_y_movimiento(): void
    {
        $producto = $this->crearProducto();
        $this->assertSame('12.500', $producto->stock_actual);
        $this->assertDatabaseHas('movimientos_inventario', ['producto_id' => $producto->id, 'usuario_id' => $this->administrador->id, 'tipo' => 'ajuste', 'motivo' => 'Carga inicial del producto', 'stock_nuevo' => 12.5]);
        $this->get('/productos/'.$producto->id)->assertOk()->assertSee('Cappuccino');
        $this->get('/productos/'.$producto->id.'/editar')->assertOk();
    }

    public function test_editar_precio_no_modifica_stock(): void
    {
        $producto = $this->crearProducto();
        $datos = $this->datosProducto(['precio_venta' => '18.50']);
        unset($datos['stock_inicial']);
        $this->put('/productos/'.$producto->id, $datos)->assertRedirect('/productos');
        $this->assertSame('18.50', $producto->fresh()->precio_venta);
        $this->assertSame('12.500', $producto->fresh()->stock_actual);
        $this->assertDatabaseCount('movimientos_inventario', 1);
        $this->put('/productos/'.$producto->id, $datos + ['stock_actual' => 999])->assertSessionHasErrors('stock_actual');
    }

    public function test_archivar_y_restaurar_producto(): void
    {
        $producto = $this->crearProducto();
        $this->delete('/productos/'.$producto->id)->assertRedirect('/productos');
        $this->assertSoftDeleted('productos', ['id' => $producto->id]);
        $this->get('/productos/'.$producto->id)->assertNotFound();
        $this->get('/productos?estado=archivado')->assertOk()->assertSee('Cappuccino');
        $this->post('/productos/'.$producto->id.'/restaurar')->assertRedirect('/productos');
        $this->assertSame('12.500', $producto->fresh()->stock_actual);
    }

    public function test_no_restaura_producto_con_categoria_archivada(): void
    {
        $producto = $this->crearProducto();
        $this->delete('/productos/'.$producto->id);
        $this->delete('/categorias/'.$producto->categoria_id);
        $this->post('/productos/'.$producto->id.'/restaurar')->assertSessionHasErrors('categoria_id');
        $this->assertSoftDeleted('productos', ['id' => $producto->id]);
    }

    public function test_rechaza_valores_negativos_codigo_repetido_y_categoria_inactiva(): void
    {
        $producto = $this->crearProducto();
        $this->post('/productos', $this->datosProducto(['precio_venta' => -1, 'stock_inicial' => -2]))->assertSessionHasErrors(['codigo', 'precio_venta', 'stock_inicial']);
        Categoria::whereKey($producto->categoria_id)->update(['estado' => false]);
        $this->post('/productos', $this->datosProducto(['codigo' => 'OTRO']))->assertSessionHasErrors('categoria_id');
    }

    public function test_filtros_stock_y_categoria(): void
    {
        $producto = $this->crearProducto();
        $this->get('/productos?stock=normal')->assertOk()->assertSee('Cappuccino');
        $this->get('/productos?stock=agotado')->assertOk()->assertDontSee('Cappuccino');
        Producto::whereKey($producto->id)->update(['stock_actual' => 2]);
        $this->get('/productos?stock=bajo')->assertOk()->assertSee('Cappuccino');
        $otra = Categoria::create(['nombre' => 'Snacks', 'estado' => true]);
        $this->get('/productos?categoria_id='.$otra->id)->assertOk()->assertDontSee('Cappuccino');
    }

    public function test_imagen_valida_se_guarda_y_sirve_con_autenticacion(): void
    {
        Storage::fake('local');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a6XcAAAAASUVORK5CYII=');
        $this->post('/productos', $this->datosProducto(['imagen' => UploadedFile::fake()->createWithContent('cafe.png', $png)]))->assertRedirect('/productos');
        $producto = Producto::firstOrFail();
        Storage::disk('local')->assertExists($producto->imagen);
        $this->get('/productos/'.$producto->id.'/imagen')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        auth()->logout();
        $this->get('/productos/'.$producto->id.'/imagen')->assertRedirect('/ingresar');
    }

    public function test_rechaza_archivos_no_permitidos_y_grandes(): void
    {
        Storage::fake('local');
        $this->post('/productos', $this->datosProducto(['imagen' => UploadedFile::fake()->createWithContent('malicioso.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')]))->assertSessionHasErrors('imagen');
        $this->post('/productos', $this->datosProducto(['imagen' => UploadedFile::fake()->create('grande.jpg', 3000, 'image/jpeg')]))->assertSessionHasErrors('imagen');
        $this->assertDatabaseCount('productos', 0);
    }

    public function test_cajero_consulta_productos_y_registra_clientes_sin_editar_catalogos(): void
    {
        $producto = $this->crearProducto();
        $cajero = $this->crearUsuario('Cajero');
        $this->flushSession();
        $this->actingAs($cajero);
        $this->get('/productos')->assertOk();
        $this->get('/productos/'.$producto->id)->assertOk()->assertDontSee('Precio de compra');
        $this->post('/productos', $this->datosProducto(['codigo' => 'OTRO']))->assertForbidden();
        $this->delete('/productos/'.$producto->id)->assertForbidden();
        $this->get('/productos?estado=archivado')->assertForbidden();
        $this->get('/categorias')->assertForbidden();
        $this->get('/proveedores')->assertForbidden();
        $this->get('/clientes')->assertOk();
        $this->post('/clientes', ['nombre' => 'Nuevo cliente', 'estado' => 1, 'es_generico' => true])->assertRedirect('/clientes');
        $cliente = Cliente::where('nombre', 'Nuevo cliente')->firstOrFail();
        $this->assertFalse($cliente->es_generico);
        $this->put('/clientes/'.$cliente->id, ['nombre' => 'Cambio', 'estado' => 1])->assertForbidden();
        $this->delete('/clientes/'.$cliente->id)->assertForbidden();
    }

    public function test_ajuste_stock_registra_diferencia_y_rechaza_formulario_desactualizado(): void
    {
        $producto = $this->crearProducto();
        $this->post('/productos/'.$producto->id.'/ajustar-stock', ['stock_esperado' => '12.500', 'stock_nuevo' => '10.250', 'motivo' => 'Conteo físico'])->assertRedirect('/productos/'.$producto->id);
        $this->assertSame('10.250', $producto->fresh()->stock_actual);
        $this->assertDatabaseHas('movimientos_inventario', ['producto_id' => $producto->id, 'cantidad' => -2.250, 'stock_anterior' => 12.500, 'stock_nuevo' => 10.250, 'motivo' => 'Conteo físico']);
        $this->post('/productos/'.$producto->id.'/ajustar-stock', ['stock_esperado' => '12.500', 'stock_nuevo' => '9', 'motivo' => 'Conteo viejo'])->assertSessionHasErrors('stock_nuevo');
        $this->assertSame('10.250', $producto->fresh()->stock_actual);
        $this->assertDatabaseCount('movimientos_inventario', 2);
    }

    public function test_cajero_no_puede_ajustar_stock(): void
    {
        $producto = $this->crearProducto();
        $this->flushSession();
        $this->actingAs($this->crearUsuario('Cajero'))->post('/productos/'.$producto->id.'/ajustar-stock', ['stock_esperado' => '12.500', 'stock_nuevo' => 0, 'motivo' => 'Cambio'])->assertForbidden();
        $this->assertSame('12.500', $producto->fresh()->stock_actual);
    }

    public function test_texto_se_escapa_al_mostrar_catalogos(): void
    {
        Cliente::create(['nombre' => '<script>alert(1)</script>', 'estado' => true]);
        $this->get('/clientes')->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }
}
