<?php

declare(strict_types=1);

use Domain\Ai\Enums\AiNotificationChannel;

describe('AiNotificationChannel', function (): void {
    it('has 3 channels', function (): void {
        $channels = AiNotificationChannel::cases();

        expect($channels)->toHaveCount(3)
            ->and(AiNotificationChannel::EMAIL->value)->toBe('email')
            ->and(AiNotificationChannel::WHATSAPP->value)->toBe('whatsapp')
            ->and(AiNotificationChannel::INTERNAL->value)->toBe('internal');
    });

    it('returns correct labels', function (): void {
        expect(AiNotificationChannel::EMAIL->label())->toBe('E-mail')
            ->and(AiNotificationChannel::WHATSAPP->label())->toBe('WhatsApp')
            ->and(AiNotificationChannel::INTERNAL->label())->toBe('Internal Notification');
    });
});
