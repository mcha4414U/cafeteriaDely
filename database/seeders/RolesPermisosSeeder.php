<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class RolesPermisosSeeder extends Seeder
{
    public function run(): void
    {
        $permisos = ['dashboard.ver', 'pos.usar', 'productos.consultar', 'productos.gestionar', 'categorias.gestionar', 'ventas.propias', 'ventas.todas', 'clientes.gestionar', 'proveedores.gestionar', 'compras.gestionar', 'inventario.gestionar', 'desperdicios.gestionar', 'reportes.ver', 'usuarios.gestionar', 'configuracion.gestionar', 'recomendaciones.ver', 'alertas.gestionar'];
        foreach ($permisos as $nombre) {
            Permiso::firstOrCreate(['nombre' => $nombre]);
        }
        $administrador = Rol::firstOrCreate(['nombre' => 'Administrador'], ['descripcion' => 'Administración de la cafetería']);
        $cajero = Rol::firstOrCreate(['nombre' => 'Cajero'], ['descripcion' => 'Ventas y atención al cliente']);
        $administrador->permisos()->sync(Permiso::whereIn('nombre', $permisos)->pluck('id'));
        $cajero->permisos()->sync(Permiso::whereIn('nombre', ['pos.usar', 'productos.consultar', 'ventas.propias', 'clientes.gestionar'])->pluck('id'));
    }
}
