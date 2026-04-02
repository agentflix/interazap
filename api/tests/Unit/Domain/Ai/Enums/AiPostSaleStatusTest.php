<?php

declare(strict_types=1);

use Domain\Ai\Enums\AiPostSaleStatus;

describe('AiPostSaleStatus', function (): void {
    it('has 5 status values', function (): void {
        $statuses = AiPostSaleStatus::cases();

        expect($statuses)->toHaveCount(5)
            ->and(AiPostSaleStatus::PENDING->value)->toBe('pending')
            ->and(AiPostSaleStatus::SENT->value)->toBe('sent')
            ->and(AiPostSaleStatus::FAILED->value)->toBe('failed')
            ->and(AiPostSaleStatus::SKIPPED->value)->toBe('skipped')
            ->and(AiPostSaleStatus::CANCELLED->value)->toBe('cancelled');
    });

    it('returns correct labels', function (): void {
        expect(AiPostSaleStatus::PENDING->label())->toBe('Pending')
            ->and(AiPostSaleStatus::SENT->label())->toBe('Sent')
            ->and(AiPostSaleStatus::FAILED->label())->toBe('Failed')
            ->and(AiPostSaleStatus::SKIPPED->label())->toBe('Skipped')
            ->and(AiPostSaleStatus::CANCELLED->label())->toBe('Cancelled');
    });

    it('returns correct badge colors', function (): void {
        expect(AiPostSaleStatus::PENDING->badgeColor())->toBe('yellow')
            ->and(AiPostSaleStatus::SENT->badgeColor())->toBe('green')
            ->and(AiPostSaleStatus::FAILED->badgeColor())->toBe('red')
            ->and(AiPostSaleStatus::SKIPPED->badgeColor())->toBe('gray')
            ->and(AiPostSaleStatus::CANCELLED->badgeColor())->toBe('red');
    });

    it('isFinal returns true only for terminal states', function (): void {
        expect(AiPostSaleStatus::PENDING->isFinal())->toBeFalse()
            ->and(AiPostSaleStatus::SENT->isFinal())->toBeTrue()
            ->and(AiPostSaleStatus::FAILED->isFinal())->toBeTrue()
            ->and(AiPostSaleStatus::SKIPPED->isFinal())->toBeTrue()
            ->and(AiPostSaleStatus::CANCELLED->isFinal())->toBeTrue();
    });
});
