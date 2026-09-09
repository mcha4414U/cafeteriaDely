<?php
// Prueba optativa en la base local: todas las filas creadas se revierten.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
if (!app()->environment('local') || config('database.default') !== 'mysql' || config('database.connections.mysql.database') !== 'cafeteria_dely_desarrollo') {
    throw new RuntimeException('Esta comprobación solo admite la base local cafeteria_dely_desarrollo.');
}
$clave = (string) Illuminate\Support\Str::uuid();
Illuminate\Support\Facades\DB::beginTransaction();
try {
    $usuario = App\Models\Usuario::create(['nombre'=>'Verificación temporal', 'email'=>$clave.'@example.test', 'password'=>Illuminate\Support\Str::random(40), 'estado'=>true]);
    $categoria = App\Models\Categoria::create(['nombre'=>'Verificación '.$clave, 'estado'=>true]);
    $proveedor = App\Models\Proveedor::create(['nombre'=>'Proveedor temporal '.$clave, 'estado'=>true]);
    $producto = App\Models\Producto::create(['nombre'=>'Producto temporal', 'codigo'=>$clave, 'categoria_id'=>$categoria->id, 'precio_compra'=>0, 'precio_venta'=>20, 'stock_minimo'=>1, 'unidad_medida'=>'kg', 'estado'=>true]);
    $datos = ['clave_idempotencia'=>$clave, 'proveedor_id'=>$proveedor->id, 'fecha'=>now()->format('Y-m-d'), 'detalles'=>[['producto_id'=>$producto->id, 'cantidad'=>'2.500', 'costo_unitario'=>'10.25']]];
    $servicio = app(App\Services\ServicioCompras::class);
    $compra = $servicio->registrar($datos,$usuario->id);
    $reintento = $servicio->registrar($datos,$usuario->id);
    if ($compra->id !== $reintento->id || $producto->fresh()->stock_actual !== '2.500' || $compra->total !== '25.63') throw new RuntimeException('Falló la compra o su idempotencia en MariaDB.');
    $vista = app(App\Http\Controllers\InventarioController::class)->index(Illuminate\Http\Request::create('/inventario'));
    $saldo = $vista->getData()['productos']->firstWhere('id',$producto->id);
    if (bccomp((string)$saldo->entradas,'2.500',3)!==0) throw new RuntimeException('Falló el agregado de entradas en MariaDB.');
    echo "MariaDB: compra, total, stock, reintento y consulta de inventario correctos.\n";
} finally {
    Illuminate\Support\Facades\DB::rollBack();
}
if (App\Models\Compra::where('clave_idempotencia',$clave)->exists()) throw new RuntimeException('No se revirtió la compra de prueba.');
echo "Filas de prueba revertidas.\n";
