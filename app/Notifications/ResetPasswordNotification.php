<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Сброс пароля — Global Parts')
            ->greeting('Здравствуйте!')
            ->line('Вы получили это письмо, потому что мы получили запрос на сброс пароля для вашего аккаунта.')
            ->action('Сбросить пароль', $url)
            ->line('Ссылка действительна в течение 60 минут.')
            ->line('Если вы не запрашивали сброс пароля, просто проигнорируйте это письмо.')
            ->salutation('С уважением, команда Global Parts');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}