# Fase 4 — compras, inventario y movimientos

## Uso

En el menú del administrador aparecen Compras e Inventario.

1. Crear productos y proveedores activos desde sus catálogos.
2. Abrir Compras → Registrar compra.
3. Elegir proveedor y fecha, agregar productos, cantidades y costos unitarios.
4. Confirmar. Se guarda la compra y su detalle, aumenta el stock y se registra una entrada por producto.
5. Consultar el detalle de la compra o su historial de entradas.

El costo de compra del producto se actualiza con el último costo recibido. El precio de venta se conserva. Las compras registradas no se editan ni eliminan desde esta fase: sus importes y movimientos permanecen como historial.

## Cálculos e integridad

- Cantidades con tres decimales e importes con dos; BCMath está declarado como requisito en Composer.
- Redondeo comercial por línea y suma de los subtotales redondeados.
- El navegador muestra una vista previa con enteros BigInt; el servidor vuelve a calcular todos los importes y no acepta el total ni el usuario enviados por el cliente.
- La fecha de compra no puede ser futura. La fecha del movimiento registra el momento efectivo de recepción en el sistema.
- Una transacción guarda compra, detalles, movimientos y saldos. Ante un error se revierte todo.
- Bloqueos de fila sobre el usuario, proveedor y productos. Los productos se bloquean por ID en orden ascendente.
- Cada formulario tiene una clave única. Repetir la misma solicitud devuelve la compra ya registrada sin duplicar stock; reutilizar la clave con datos distintos se rechaza.
- Límites de cantidad, stock e importe evitan desbordar las columnas DECIMAL.
- Se conservan nombres de proveedor, producto y unidad en las nuevas compras para mantener el detalle aunque después se edite o archive el catálogo.

## Inventario

Se muestran producto, unidad, stock actual, mínimo, entradas, salidas y disponibilidad. Entradas y salidas se calculan a partir de las diferencias de saldo de todos los movimientos; no se compensan entre sí.

El inventario incluye productos archivados para que sus existencias sigan visibles. Cada fila enlaza a su historial y, cuando corresponde, al ajuste de stock de la fase 3.

El historial permite filtrar por producto, tipo, fecha y compra. Muestra usuario, motivo, cantidad, saldo anterior, saldo nuevo y referencia. Los registros de ventas y desperdicios aparecerán cuando se implementen esos módulos.

No se suman unidades distintas en un único indicador de stock. Los importes y cantidades se presentan por producto y unidad.

## Permisos

Compras requiere `compras.gestionar`; inventario e historial requieren `inventario.gestionar`. El cajero no accede a estas rutas ni puede registrar compras. Se mantienen los controles de autenticación, estado de cuenta y CSRF de las fases anteriores.

## Archivos

- `app/Services/ServicioCompras.php`: transacción, importes, idempotencia y entradas.
- `app/Http/Requests/CompraRequest.php`: validación de cabecera y líneas.
- `app/Models/Compra.php`, `DetalleCompra.php`, `MovimientoInventario.php`: relaciones Eloquent.
- `app/Http/Controllers/CompraController.php` e `InventarioController.php`: listados, filtros y detalle.
- `routes/inventario.php`, vistas `compras` e `inventario`.
- `resources/js/compras.js`: filas dinámicas y vista previa de importes.
- La migración de esta fase amplía compras y detalles sin borrar las tablas existentes.

## Verificación

```powershell
php artisan migrate
php artisan test
npm.cmd run build
composer check-platform-reqs
```

También existe una comprobación optativa contra MariaDB local:

```powershell
php tests/Support/ComprobarCompraMariaDB.php
```

Solo admite el entorno local y la base `cafeteria_dely_desarrollo`. Crea datos temporales en una transacción y los revierte, incluida la cuenta temporal. Puede consumir números autoincrementales sin conservar filas.

Las pruebas cubren redondeo, alta de stock, reintentos, datos inválidos, límites, rollback, históricos, filtros y permisos. La prueba real en MariaDB comprueba compra, total, stock, idempotencia y agregados de inventario.

Composer tenía TLS desactivado en la configuración heredada; el proyecto ahora configura `disable-tls=false` y `secure-http=true`. No se actualizaron las bibliotecas al declarar BCMath.

La siguiente fase es POS, carrito, ventas y comprobantes. Las alertas automáticas, WhatsApp y recomendaciones IA se mantienen para sus fases previstas.

## Resultado de esta fase

54 pruebas aprobadas (318 aserciones), compilación Vite y Blade correctas, requisitos de Composer verificados y comprobación real en MariaDB aprobada con reversión de las filas temporales. No se modificó la base antigua `cafeteria_dely`. La revisión visual automatizada sigue pendiente porque no hay navegador conectado.
