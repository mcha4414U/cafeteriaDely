<?php

namespace App\Http\Requests;

use App\Services\ServicioInstalacion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CrearAdministradorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(ServicioInstalacion::class)->disponible();
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }

    public function rules(): array
    {
        return ['nombre' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'max:255', 'unique:usuarios,email'], 'password' => ['required', 'string', 'confirmed', 'max:255', Password::min(12)->mixedCase()->numbers()]];
    }
}
