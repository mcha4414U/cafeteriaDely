<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tienePermiso('usuarios.gestionar') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['email' => mb_strtolower(trim((string) $this->email))]);
    }

    public function rules(): array
    {
        return ['nombre' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', Rule::unique('usuarios')->ignore($this->route('usuario'))],
            'password' => [$this->route('usuario') ? 'nullable' : 'required', 'confirmed', 'max:255', Password::min(12)->mixedCase()->numbers()],
            'rol_id' => ['required', 'integer', Rule::exists('roles', 'id')], 'estado' => ['required', 'boolean']];
    }
}
