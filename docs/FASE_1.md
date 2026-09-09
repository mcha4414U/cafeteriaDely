# Cafetería Dely — fase 1

## Entorno revisado

- Proyecto Laravel 12.69.2, PHP 8.2.12 (XAMPP), Composer 2.8.12.
- Node.js 22.18.0, npm 10.9.3, Git 2.50.1.
- MariaDB 10.4.32 y phpMyAdmin disponibles en XAMPP.
- Se corrigieron los directorios de ejecución y los atributos de solo lectura que impedían arrancar Laravel en OneDrive.
- Base nueva: `cafeteria_dely_desarrollo`, codificación utf8mb4. La base anterior `cafeteria_dely` se conserva sin cambios.
- `.env` configura español y la nueva conexión local. La zona horaria es America/La_Paz. No publicar `.env`.

## Estructura y decisiones

Mantener la arquitectura de Laravel: `app/Models`, `app/Http/Controllers`, `app/Http/Requests`, `app/Http/Middleware`, `app/Policies`, `app/Services`, `app/Jobs`, `app/Events`, `app/Listeners`, `app/Livewire`, `resources/views`, `routes`, `database/migrations`, `database/seeders` y `tests`.

Las carpetas de módulos se incorporarán cuando exista su implementación. La fase 1 define persistencia, no funcionalidades de venta ni autenticación.

- Usuarios se almacenan en `usuarios`, con campo `nombre`; el modelo base `User` sigue siendo el punto de integración del esqueleto y se adaptará en fase 2. Se conservan los campos internos de autenticación y tablas técnicas de Laravel.
- Roles y permisos se relacionan mediante `rol_usuario` y `permiso_rol`.
- Productos pertenecen a categorías. Clientes y vendedores se relacionan con pedidos y ventas; proveedores con compras.
- Compras, pedidos y ventas tienen tablas de detalle. Las ventas guardan el nombre histórico del producto y una clave de idempotencia única.
- Movimientos de inventario referencian productos, usuarios y, cuando corresponde, compras, ventas o desperdicios.
- Importes usan DECIMAL(12,2); cantidades DECIMAL(12,3), para admitir unidades fraccionarias. Las validaciones de valores positivos y las transacciones con bloqueo de stock se incorporarán en los servicios de sus módulos.
- Claves foráneas restringen el borrado de registros referenciados. Categorías, productos, clientes y proveedores tienen eliminación lógica.
- Alertas activas usan `clave_activa` única por producto/tipo. Al resolver una alerta, el servicio deberá asignar NULL a esa clave para permitir un nuevo ciclo.
- No se aplica automáticamente el impuesto de la imagen: la referencia visual no establece una regla fiscal del proyecto.
- Configuraciones guarda preferencias; las claves de WhatsApp e IA permanecerán en variables de entorno.

## Dependencias por incorporar

La instalación actual incluye Laravel, Eloquent, Blade y Vite. Se retiró Tailwind de la configuración activa.

- Interfaz: Bootstrap, AdminLTE, Lucide Icons, SweetAlert2, DataTables y Chart.js.
- Interacción: Livewire y su integración de Alpine.js, evitando cargar Alpine dos veces.
- Reportes: DomPDF y exportaciones CSV/Excel.
- API: Sanctum; integraciones WhatsApp e IA mediante servicios HTTP y variables de entorno.

Se instalarán y verificarán versiones compatibles al iniciar la fase que las utilice. No se han implementado todavía estas integraciones.

## Plan de trabajo

1. Entorno, conexión, esquema, migraciones y pruebas de integridad.
2. Autenticación, usuarios, roles y permisos.
3. Categorías, productos, clientes y proveedores.
4. Compras, inventario y movimientos.
5. POS, carrito, ventas y comprobantes.
6. Dashboard, gráficas y reportes.
7. Desperdicios y alertas.
8. WhatsApp Cloud API.
9. Recomendaciones IA.
10. REST API, Sanctum y colección Postman.
11. Pruebas generales, seguridad y documentación.

Las imágenes aportadas guiarán el dashboard y el POS: barra lateral café oscuro, fondo crema, tarjetas blancas y carrito lateral, con la marca Cafetería Dely.

## Ejecución local

Desde esta carpeta, con MariaDB iniciado desde XAMPP:

```powershell
composer install
npm.cmd install
npm.cmd run build
php artisan migrate --seed
php artisan serve
```

Abrir http://localhost:8000. La portada es provisional, no es el dashboard terminado. Para Apache, el DocumentRoot debe apuntar a la carpeta `public`.

En una instalación nueva copiar `.env.example` a `.env`, configurar credenciales locales y ejecutar `php artisan key:generate` una sola vez. No regenerar la clave de una instalación en uso.

## Verificación

```powershell
php artisan migrate:status
php artisan test
composer check-platform-reqs
npm.cmd run build
```

Los tests usan SQLite en memoria para aislar los datos; las migraciones de desarrollo se ejecutan sobre MariaDB real. No usar `migrate:fresh` contra la base anterior. Los datos iniciales son el cliente genérico, cuatro métodos de pago y el nombre del negocio; no hay usuarios con claves predeterminadas.

Documentación oficial: https://laravel.com/docs/12.x/installation y https://laravel.com/docs/12.x/migrations.
