# Fase 3 — categorías, productos, clientes y proveedores

## Uso

Ingresar en `/ingresar`. El menú muestra los módulos según los permisos de la cuenta.

- Categorías: crear, editar, consultar, activar/desactivar, archivar y restaurar.
- Clientes: datos de contacto y observaciones. El cliente genérico no se modifica ni archiva.
- Proveedores: nombre, empresa, teléfono, dirección y observaciones.
- Productos: código único, categoría activa, descripción, precios, unidad de medida, stock mínimo, vencimiento, estado e imagen opcional.
- Listados DataTables con búsqueda, orden y paginación. Los filtros de estado, categoría y stock se aplican en el servidor.
- Archivado lógico: conserva los datos y relaciones. El filtro Archivados permite restaurar registros. Los nombres de categoría y códigos siguen reservados mientras estén archivados.
- No se archiva ni desactiva una categoría mientras tenga productos vigentes. Para restaurar un producto su categoría debe estar activa y sin archivar.

## Stock

El stock inicial genera un movimiento de ajuste cuando es mayor que cero. Editar precios y otros campos del catálogo no modifica existencias. El detalle del producto permite un ajuste separado con permiso `inventario.gestionar` y motivo obligatorio.

Los ajustes bloquean el producto dentro de una transacción, guardan usuario, fecha, cantidad con signo, saldo anterior y nuevo saldo. Comparan el stock del formulario con el actual para rechazar formularios desactualizados. Usan BCMath, disponible en el PHP de XAMPP revisado, para operaciones exactas con tres decimales. No se cambia la unidad de medida mientras quede stock.

El listado completo de movimientos, las compras y sus entradas automáticas pertenecen a la fase 4. Las alertas automáticas y WhatsApp pertenecen a fases posteriores. Archivar un producto conserva su saldo e historial; no representa una salida de stock.

## Permisos

- Administrador: gestión de los cuatro catálogos y ajustes de stock.
- Cajero: consulta de productos y clientes, y registro de nuevos clientes. No edita ni archiva catálogos, no consulta proveedores ni categorías administrativas y no ajusta existencias. El precio de compra no se muestra al cajero.

La actualización del seeder sustituye el antiguo permiso general de clientes del cajero por `clientes.consultar` y `clientes.registrar`.

## Imágenes

Se aceptan JPG, PNG y WebP de hasta 2 MB y 4000 × 4000 píxeles. Se verifica el contenido del archivo. Las imágenes se guardan en el disco local privado y se sirven mediante una ruta autenticada con permiso de consulta, sin requerir `storage:link`.

La edición sin imagen conserva la anterior; una nueva imagen reemplaza la referencia y elimina el archivo anterior. Si la transacción falla, se limpia la imagen recién cargada. Las imágenes de productos archivados se conservan para restauración.

## Organización

`Categoria`, `Producto`, `Cliente` y `Proveedor` son modelos Eloquent con eliminación lógica. `ProductoController` gestiona el producto; `CatalogoController` comparte las operaciones repetidas de los otros tres catálogos con configuraciones internas cerradas. No toma nombres de tabla del usuario. Los Form Requests y servicios contienen validaciones y reglas de negocio.

No fue necesario crear tablas nuevas: se utiliza el esquema de la fase 1.

## Verificación y ejecución

```powershell
php artisan db:seed --class=RolesPermisosSeeder
php artisan test
npm.cmd run build
php artisan serve
```

La suite de catálogos prueba CRUD, archivado/restauración, integridad de categorías, cliente genérico, stock inicial, edición de precios, permisos del cajero, imágenes, escape HTML y ajustes con protección contra formularios desactualizados. Los tests usan SQLite en memoria y un disco falso para imágenes; no agregan productos de demostración a MariaDB.

La inspección visual automatizada sigue pendiente por falta de navegador conectado. La compilación y las pruebas HTTP/Blade verifican la integración funcional, no sustituyen esa revisión visual.

Resultado: 41 pruebas aprobadas (237 aserciones), compilación Vite correcta, vistas Blade compiladas y permisos actualizados en MariaDB. No se modificó la base antigua `cafeteria_dely`.
