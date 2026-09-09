<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerificarPermiso
{
    public function handle(Request $solicitud, Closure $siguiente, string $permiso)
    {
        abort_unless($solicitud->user()?->tienePermiso($permiso), 403);

        return $siguiente($solicitud);
    }
}
