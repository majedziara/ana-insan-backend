<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail(mixed $notifiable): MailMessage
    {
        $url = rtrim(config('anainsan.frontend_url'), '/').'/'.rawurlencode($notifiable->preferred_locale).'/reset-password?'.http_build_query(['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()], '', '&', PHP_QUERY_RFC3986);

        return (new MailMessage)->subject(__('api.mail.reset_subject'))->greeting(__('api.mail.greeting', ['name' => $notifiable->name]))->line(__('api.mail.reset_intro'))->action(__('api.mail.reset_action'), $url)->line(__('api.mail.reset_expiry', ['minutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]))->line(__('api.mail.ignore'));
    }
}
