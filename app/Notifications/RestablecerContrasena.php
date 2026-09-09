<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class RestablecerContrasena extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject('Restablecer contraseña — Cafetería Dely')->greeting('Hola, '.$notifiable->nombre)
            ->line('Recibimos una solicitud para restablecer tu contraseña.')
            ->action('Restablecer contraseña', url(route('password.reset', ['token' => $this->token, 'email' => $notifiable->email], false)))
            ->line('El enlace caduca en 60 minutos. Si no solicitaste este cambio, puedes ignorar el mensaje.')
            ->salutation('Cafetería Dely');
    }
}
