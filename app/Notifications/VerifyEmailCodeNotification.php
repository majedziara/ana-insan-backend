<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailCodeNotification extends Notification
{
    public function __construct(public readonly string $code) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('api.mail.verify_subject'))
            ->greeting(__('api.mail.greeting', ['name' => $notifiable->name]))
            ->line(__('api.mail.verify_code', ['code' => $this->code]))
            ->line(__('api.mail.verify_expiry', ['minutes' => config('anainsan.auth.verification_minutes')]))
            ->line(__('api.mail.ignore'));
    }
}
