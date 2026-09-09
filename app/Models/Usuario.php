<?php

namespace App\Models;

use App\Notifications\RestablecerContrasena;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Usuario extends User
{
    protected $fillable = ['nombre', 'email', 'password', 'estado'];

    protected function casts(): array
    {
        return array_merge(parent::casts(), ['estado' => 'boolean']);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_usuario', 'usuario_id', 'rol_id')->withTimestamps();
    }

    public function tienePermiso(string $permiso): bool
    {
        return $this->estado && $this->roles()->whereHas('permisos', fn ($consulta) => $consulta->where('nombre', $permiso))->exists();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new RestablecerContrasena($token));
    }
}
