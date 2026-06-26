<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Reset Your Newbook Password')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('We received a request to reset your Newbook password.')
            ->action('Reset Password', $url)
            ->line('This link expires in '.config('auth.passwords.users.expire').' minutes.')
            ->line('If you did not request a password reset, you can safely ignore this email.');
    }
}
