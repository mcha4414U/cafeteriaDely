<?php

namespace App\Http\Controllers;

use App\Http\Requests\UsuarioRequest;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ServicioUsuarios;

class UsuarioController extends Controller
{
    public function index()
    {
        return view('usuarios.indice', ['usuarios' => Usuario::with('roles')->orderBy('nombre')->get(), 'roles' => Rol::with('permisos')->get()]);
    }

    public function crear()
    {
        return view('usuarios.formulario', ['usuario' => new Usuario, 'roles' => Rol::all()]);
    }

    public function editar(Usuario $usuario)
    {
        return view('usuarios.formulario', compact('usuario') + ['roles' => Rol::all()]);
    }

    public function guardar(UsuarioRequest $solicitud, ServicioUsuarios $servicio)
    {
        $servicio->guardar($solicitud->validated());

        return redirect()->route('usuarios.index')->with('estado', 'Usuario creado correctamente.');
    }

    public function actualizar(UsuarioRequest $solicitud, Usuario $usuario, ServicioUsuarios $servicio)
    {
        $servicio->guardar($solicitud->validated(), $usuario);

        return redirect()->route('usuarios.index')->with('estado', 'Usuario actualizado correctamente.');
    }
}
