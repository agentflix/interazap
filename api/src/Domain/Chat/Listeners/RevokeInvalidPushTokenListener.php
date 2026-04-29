<?php

declare(strict_types=1);

namespace Domain\Chat\Listeners;

use Domain\Auth\Models\AuthDeviceToken;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Notifications\NewMessageNotification;
use Illuminate\Notifications\Events\NotificationFailed;
use NotificationChannels\Apn\ApnChannel;
use NotificationChannels\Fcm\FcmChannel;

/**
 * Revoga token de device quando APNs/FCM reporta token invalido.
 */
final class RevokeInvalidPushTokenListener
{
    public function handle(NotificationFailed $event): void
    {
        if (! $event->notifiable instanceof AuthUser) {
            return;
        }

        if (! $event->notification instanceof NewMessageNotification) {
            return;
        }

        if (! in_array($event->channel, [ApnChannel::class, FcmChannel::class], true)) {
            return;
        }

        $token = $this->extractToken($event->data);
        if ($token === null) {
            return;
        }

        if (! $this->isInvalidTokenFailure($event->data)) {
            return;
        }

        AuthDeviceToken::query()
            ->where('tenant_id', (string) $event->notifiable->tenant_id)
            ->where('user_id', (string) $event->notifiable->id)
            ->where('token', $token)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractToken(array $data): ?string
    {
        $token = data_get($data, 'token');
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $target = data_get($data, 'target');
        if (is_object($target) && method_exists($target, 'value')) {
            $value = $target->value();
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        $report = data_get($data, 'report');
        if (is_object($report) && method_exists($report, 'target')) {
            $reportTarget = $report->target();
            if (is_object($reportTarget) && method_exists($reportTarget, 'value')) {
                $value = $reportTarget->value();
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function isInvalidTokenFailure(array $data): bool
    {
        $message = $this->extractErrorMessage($data);

        if ($message === '') {
            return false;
        }

        foreach ([
            'baddevicetoken',
            'unregistered',
            'notregistered',
            'invalidregistration',
            'registration-token-not-registered',
            'device token not for topic',
        ] as $signal) {
            if (str_contains($message, $signal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function extractErrorMessage(array $data): string
    {
        $message = data_get($data, 'message');
        if (is_string($message) && $message !== '') {
            return strtolower($message);
        }

        $reason = data_get($data, 'reason');
        if (is_string($reason) && $reason !== '') {
            return strtolower($reason);
        }

        $report = data_get($data, 'report');
        if (is_object($report) && method_exists($report, 'error')) {
            $error = $report->error();

            if (is_object($error) && method_exists($error, 'getMessage')) {
                $errorMessage = $error->getMessage();
                if (is_string($errorMessage) && $errorMessage !== '') {
                    return strtolower($errorMessage);
                }
            }

            if (is_string($error) && $error !== '') {
                return strtolower($error);
            }
        }

        return '';
    }
}
