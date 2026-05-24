<?php

declare(strict_types=1);

namespace Domain\Auth\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

final class ResetPasswordNotification extends BaseResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        $expireMinutes = config(
            'auth.passwords.' . config('auth.defaults.passwords') . '.expire',
            60
        );

        return (new MailMessage)
            ->subject('Redefinição de Senha — ' . config('app.name', 'InteraZap'))
            ->view('emails.auth.reset-password', [
                'url'           => $url,
                'expireMinutes' => $expireMinutes,
                'appName'       => config('app.name', 'InteraZap'),
            ]);
    }
}
