# Revisión integral de Cafetería Dely — 27/10/2026

## Estado
Implementación y pruebas terminadas. Falta autorización para ejecutar la migración en la base real. No se ejecutó migrate:fresh, db:wipe, ningún seeder ni una migración sobre la base real durante esta revisión.

## Cambios funcionales
- Se reutiliza productos.tipo: Producto (venta) e Insumo. Los registros existentes de tipo ambos conservan su clasificación y su opción de edición.
- ¿Requiere receta? aparece para productos; en insumos se oculta junto al precio de venta. El servidor fuerza el indicador a falso para insumos y conserva el precio anterior aunque el campo esté oculto.
- Un producto marcado como preparado debe tener receta activa con ingredientes válidos antes del cobro. Los productos normales siguen vendiéndose por unidades.
- Las recetas existentes continúan determinando la preparación cuando el nuevo indicador es NULL. No se borran ni se convierten registros antiguos.
- Se bloquea desactivar el indicador si hay receta o preparaciones relacionadas. Las personalizaciones por unidad siguen siendo indicaciones del barista; el descuento de inventario sigue usando la receta maestra una sola vez.
- Alertas y reposición excluyen los productos que requieren receta, incluso cuando esta está pendiente de configurar.
- Crear/editar, catálogo y ficha distinguen los tipos. La presentación conserva contenido, unidad base, envases equivalentes y stock fraccionado.

## Formato reutilizable
- Cantidad::mostrar: texto es-BO exacto, sin convertir cantidades a float. 2.000 canónico → 2; 2000 → 2.000; 1.500 → 1,5; 150.125 → 150,125.
- Cantidad::entrada: valor canónico para input number; sin miles y con punto decimal. Nunca se envía 2.000,25 a cálculos del servidor.
- Moneda::mostrar: importes con dos decimales y separadores locales, por ejemplo Bs. 15,00.
- formatos.js aplica las mismas reglas a carrito, compras, presentaciones y modales.
- Las tablas usan coma decimal y punto de miles para reconocer cantidades al ordenar.
- No se modificaron los casts DECIMAL, las columnas numéricas ni las fórmulas monetarias.
- Los JSON de recetas/POS conservan números canónicos; las notificaciones JSON entregan el HTML con cantidades localizadas.
- Vistas revisadas: POS, Preparaciones, catálogo, inventario/movimientos, reposición, desperdicios, compras, alertas/notificaciones, reportes, ventas/comprobantes, formularios, página pública y modales.
- Las vistas de inventario, movimientos, desperdicios y tarjetas de preparación ya usaban Cantidad::mostrar; reciben el formato nuevo sin duplicar cambios en cada archivo.
- Categorías, clientes, usuarios y configuración no tenían campos de cantidad decimal que exigieran conversión. No se encontraron exportadores CSV/XLSX/PDF; las impresiones existentes reutilizan las vistas revisadas.
- Los textos históricos de alertas permanecen guardados sin reescribir su contenido. Los nuevos mensajes usan el formateador común; la campana muestra existencias actuales formateadas.

## Migración pendiente y alcance
Archivo: database/migrations/2026_10_27_000001_agregar_requiere_receta_a_productos.php

Añade solamente productos.requiere_receta, booleano nullable. No ejecuta UPDATE de datos anteriores. NULL conserva el comportamiento según la receta existente. No modifica stock, importes, clasificaciones, recetas, ventas, compras ni movimientos. No crea tablas.

Mientras esté pendiente, las operaciones anteriores siguen funcionando. Intentar seleccionar un comportamiento nuevo que necesite guardar el indicador muestra un mensaje de activación pendiente.

Después de autorización:
1. Crear respaldo privado con php artisan dely:respaldar.
2. Ejecutar únicamente php artisan migrate --path=database/migrations/2026_10_27_000001_agregar_requiere_receta_a_productos.php --force.
3. Comprobar conservación de datos anteriores y funcionamiento del indicador.

## Verificación ejecutada
- php artisan test --compact: 141 pruebas aprobadas, 1186 aserciones.
- node --test tests/js/*.test.js: 13 pruebas aprobadas.
- npm run build: compilación realizada.
- La suite Laravel usa SQLite :memory: y contiene una protección que impide ejecutar sus migraciones contra una base real.
- Se ajustaron únicamente expectativas de presentación antiguas: puntos decimales, miles y textos monetarios. Se conservan las verificaciones de valores almacenados.
- Se añadieron pruebas de tipos, indicador, bloqueo sin receta, conservación de receta/historial, POS/Preparaciones, formularios y cantidades fraccionarias, monedas y campos dinámicos.

## Archivos creados o modificados en esta revisión
- app/Http/Controllers/ProductoController.php
- app/Http/Controllers/PuntoVentaController.php
- app/Http/Controllers/RecomendacionController.php
- app/Http/Requests/ProductoRequest.php
- app/Models/Producto.php
- app/Services/ServicioAlertas.php
- app/Services/ServicioProductos.php
- app/Services/ServicioRecetas.php
- app/Services/ServicioVentas.php
- app/Support/Cantidad.php
- app/Support/Moneda.php
- resources/js/app.js
- resources/js/carrito.js
- resources/js/compras.js
- resources/js/formatos.js
- resources/js/presentaciones.js
- resources/js/producto-formulario.js
- resources/js/receta-pos.js
- resources/js/recetas.js
- resources/views/inicio.blade.php
- resources/views/cliente/inicio.blade.php
- resources/views/compras/formulario.blade.php
- resources/views/compras/indice.blade.php
- resources/views/compras/linea.blade.php
- resources/views/compras/mostrar.blade.php
- resources/views/partials/lista-avisos-stock.blade.php
- resources/views/pedidos/anteriores.blade.php
- resources/views/pedidos/crear.blade.php
- resources/views/pedidos/mostrar.blade.php
- resources/views/pos/indice.blade.php
- resources/views/productos/formulario.blade.php
- resources/views/productos/indice.blade.php
- resources/views/productos/mostrar.blade.php
- resources/views/recomendaciones/indice.blade.php
- resources/views/reportes/indice.blade.php
- resources/views/ventas/indice.blade.php
- resources/views/ventas/mostrar.blade.php
- database/migrations/2026_10_27_000001_agregar_requiere_receta_a_productos.php
- tests/Feature/ComprasInventarioTest.php
- tests/Feature/PaginaClienteTest.php
- tests/Feature/PresentacionesInsumosTest.php
- tests/Feature/TiposRecetaFormatoTest.php
- tests/Feature/VentasAlertasTest.php
- tests/js/carrito.test.js
- tests/js/formatos.test.js
- tests/js/presentaciones.test.js
- tests/js/producto-formulario.test.js
- tests/Unit/ReglasDominioTest.php

Además: recursos compilados en public/build y este informe con el resultado de la suite en evidencias/pruebas/revision-integral-2026-10-27.txt.
