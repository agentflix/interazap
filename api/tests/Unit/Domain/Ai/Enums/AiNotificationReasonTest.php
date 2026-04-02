<?php

declare(strict_types=1);

use Domain\Ai\Enums\AiNotificationReason;

describe('AiNotificationReason', function (): void {
    it('has all 6 required values', function (): void {
        $values = AiNotificationReason::cases();

        expect($values)->toHaveCount(6)
            ->and(AiNotificationReason::HOT_LEAD->value)->toBe('hot_lead')
            ->and(AiNotificationReason::URGENT_QUESTION->value)->toBe('urgent_question')
            ->and(AiNotificationReason::NEGATIVE_SENTIMENT->value)->toBe('negative_sentiment')
            ->and(AiNotificationReason::ESCALATION->value)->toBe('escalation')
            ->and(AiNotificationReason::CUSTOM->value)->toBe('custom')
            ->and(AiNotificationReason::VIP_CLIENT->value)->toBe('vip_client');
    });

    it('returns correct labels', function (): void {
        expect(AiNotificationReason::HOT_LEAD->label())->toBe('Hot Lead')
            ->and(AiNotificationReason::URGENT_QUESTION->label())->toBe('Urgent Question')
            ->and(AiNotificationReason::NEGATIVE_SENTIMENT->label())->toBe('Negative Sentiment')
            ->and(AiNotificationReason::ESCALATION->label())->toBe('Escalation')
            ->and(AiNotificationReason::CUSTOM->label())->toBe('Custom')
            ->and(AiNotificationReason::VIP_CLIENT->label())->toBe('VIP Client');
    });

    it('returns correct priority levels', function (): void {
        expect(AiNotificationReason::HOT_LEAD->priority())->toBe('high')
            ->and(AiNotificationReason::URGENT_QUESTION->priority())->toBe('high')
            ->and(AiNotificationReason::NEGATIVE_SENTIMENT->priority())->toBe('high')
            ->and(AiNotificationReason::ESCALATION->priority())->toBe('critical')
            ->and(AiNotificationReason::CUSTOM->priority())->toBe('normal')
            ->and(AiNotificationReason::VIP_CLIENT->priority())->toBe('high');
    });
});
