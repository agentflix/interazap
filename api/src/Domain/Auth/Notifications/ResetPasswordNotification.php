<?php

declare(strict_types=1);

namespace Domain\Auth\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Notificação de redefinição de senha com template customizado.
 *
 * Sobrescreve o comportamento padrão do Laravel para usar a view
 * de e-mail customizada do InteraZap com suporte a expiração configurável.
 */
final class ResetPasswordNotification extends BaseResetPassword
{
    /**
     * Constrói a mensagem de e-mail com a view customizada e o tempo de expiração.
     *
     * @param  string  $url  URL de redefinição de senha com token.
     */
    protected function buildMailMessage($url): MailMessage
    {
        $expireMinutes = config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            60
        );

        return (new MailMessage)
            ->subject('Redefinição de Senha — '.config('app.name', 'InteraZap'))
            ->view('emails.auth.reset-password', [
                'url' => $url,
                'expireMinutes' => $expireMinutes,
                'appName' => config('app.name', 'InteraZap'),
            ]);
    }
}
