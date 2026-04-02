<?php

declare(strict_types=1);

use Domain\Ai\Enums\AiPostSaleScheduleType;

describe('AiPostSaleScheduleType', function (): void {
    it('has 3 schedule types', function (): void {
        $types = AiPostSaleScheduleType::cases();

        expect($types)->toHaveCount(3)
            ->and(AiPostSaleScheduleType::D1->value)->toBe('d1')
            ->and(AiPostSaleScheduleType::D7->value)->toBe('d7')
            ->and(AiPostSaleScheduleType::D30->value)->toBe('d30');
    });

    it('returns correct days offset', function (): void {
        expect(AiPostSaleScheduleType::D1->daysOffset())->toBe(1)
            ->and(AiPostSaleScheduleType::D7->daysOffset())->toBe(7)
            ->and(AiPostSaleScheduleType::D30->daysOffset())->toBe(30);
    });

    it('returns correct labels', function (): void {
        expect(AiPostSaleScheduleType::D1->label())->toBe('Day +1')
            ->and(AiPostSaleScheduleType::D7->label())->toBe('Day +7')
            ->and(AiPostSaleScheduleType::D30->label())->toBe('Day +30');
    });
});
