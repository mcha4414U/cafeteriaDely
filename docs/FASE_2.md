# Fase 2 — acceso y administración de usuarios

## Funcionalidades

- Inicio de sesión con correo, contraseña y opción de recordar la cuenta.
- Cierre de sesión por POST, regeneración de sesión al ingresar y protección CSRF.
- Límite de cinco intentos fallidos por correo/IP durante un minuto; límite adicional por IP.
- Recuperación con el broker de Laravel, enlace temporal y token de un solo uso.
- Roles Administrador y Cajero, relacionados con permisos persistidos en la base.
- Listado de usuarios con búsqueda, orden y paginación mediante DataTables.
- Creación, edición, cambio de rol y desactivación. La baja es reversible y conserva referencias históricas.
- Las contraseñas se almacenan mediante el cast hashed de Laravel.
- No es posible desactivar ni quitar el rol al último administrador activo. El servicio serializa estos cambios con un bloqueo transaccional del rol.
- Cuentas desactivadas pierden sus sesiones; el cambio de contraseña invalida sesiones anteriores y la cookie de recuerdo.
- Rutas y formulario administrativo verifican permisos en el servidor. Ocultar menús es una comodidad adicional, no el control de seguridad.

## Primera cuenta

Desde la terminal de VS Code, en la carpeta del proyecto:

```powershell
php artisan dely:crear-administrador
```

El comando solicita nombre, correo y contraseña sin mostrarla. No crea credenciales predeterminadas, no guarda la contraseña en archivos y se bloquea cuando ya existe un administrador activo. Después entrar en http://localhost:8000/ingresar.

## Correo de recuperación

El entorno local conserva `MAIL_MAILER=log`: no se envían correos reales. Configurar un servidor SMTP en `.env` con MAIL_MAILER, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD y MAIL_FROM_ADDRESS para recibir enlaces por correo. APP_URL debe apuntar al dominio real de la instalación. No publicar logs de correo porque pueden contener enlaces de recuperación.

En producción usar HTTPS, APP_DEBUG=false y SESSION_SECURE_COOKIE=true. La aplicación no tiene registro público: el administrador crea las cuentas.

## Archivos principales

- `app/Models/Usuario.php`, `Rol.php`, `Permiso.php`.
- `app/Http/Controllers/AutenticacionController.php`, `ContrasenaController.php`, `UsuarioController.php`.
- `app/Http/Requests` contiene la validación de acceso y usuarios.
- `app/Http/Middleware` controla estado y permisos.
- `app/Services/ServicioUsuarios.php` contiene las reglas de actualización.
- `database/seeders/RolesPermisosSeeder.php` fija la matriz de permisos. Se muestran en pantalla, pero no se editan libremente en esta fase.
- `resources/views/autenticacion`, `usuarios` y `layouts` contienen la interfaz Bootstrap/AdminLTE personalizada.
- `tests/Feature/AutenticacionTest.php` comprueba acceso, privilegios y recuperación con notificaciones simuladas.

El modelo Usuario hereda temporalmente del modelo base User de la fase 1 para conservar compatibilidad con su factory. La configuración de autenticación ya utiliza Usuario.

## Verificación

```powershell
php artisan migrate --seed
php artisan test
npm.cmd run build
php artisan route:list
```

Las pruebas usan SQLite en memoria; la migración y los roles se instalan en MariaDB de desarrollo. Las notificaciones se simulan en pruebas, sin enviar mensajes.

Referencias: https://laravel.com/docs/12.x/authentication y https://laravel.com/docs/12.x/passwords.

## Resultado de la revisión

24 pruebas aprobadas en la suite completa, compilación Vite correcta y migración aplicada en MariaDB. La comprobación HTTP devuelve 200 para el acceso y 419 al enviar un formulario sin CSRF. No se pudo hacer inspección visual automatizada porque no había navegador conectado. La cuenta inicial se crea con el comando descrito arriba; el correo real requiere SMTP.
