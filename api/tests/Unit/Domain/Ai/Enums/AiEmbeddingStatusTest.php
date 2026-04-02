<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Enums;

use Domain\Ai\Enums\AiEmbeddingStatus;

describe('AiEmbeddingStatus', function (): void {
    describe('isSearchable()', function (): void {
        it('returns true only for READY status', function (): void {
            expect(AiEmbeddingStatus::READY->isSearchable())->toBeTrue();
            expect(AiEmbeddingStatus::PENDING->isSearchable())->toBeFalse();
            expect(AiEmbeddingStatus::PROCESSING->isSearchable())->toBeFalse();
            expect(AiEmbeddingStatus::FAILED->isSearchable())->toBeFalse();
        });
    });

    describe('isProcessing()', function (): void {
        it('returns true only for PROCESSING status', function (): void {
            expect(AiEmbeddingStatus::PROCESSING->isProcessing())->toBeTrue();
            expect(AiEmbeddingStatus::PENDING->isProcessing())->toBeFalse();
            expect(AiEmbeddingStatus::READY->isProcessing())->toBeFalse();
            expect(AiEmbeddingStatus::FAILED->isProcessing())->toBeFalse();
        });
    });

    describe('canReprocess()', function (): void {
        it('returns true for READY and FAILED', function (): void {
            expect(AiEmbeddingStatus::READY->canReprocess())->toBeTrue();
            expect(AiEmbeddingStatus::FAILED->canReprocess())->toBeTrue();
        });

        it('returns false for PENDING and PROCESSING', function (): void {
            expect(AiEmbeddingStatus::PENDING->canReprocess())->toBeFalse();
            expect(AiEmbeddingStatus::PROCESSING->canReprocess())->toBeFalse();
        });
    });

    describe('badgeColor()', function (): void {
        it('returns correct colors for each status', function (): void {
            expect(AiEmbeddingStatus::PENDING->badgeColor())->toBe('yellow');
            expect(AiEmbeddingStatus::PROCESSING->badgeColor())->toBe('blue');
            expect(AiEmbeddingStatus::READY->badgeColor())->toBe('green');
            expect(AiEmbeddingStatus::FAILED->badgeColor())->toBe('red');
        });
    });

    describe('label()', function (): void {
        it('returns human readable labels', function (): void {
            expect(AiEmbeddingStatus::PENDING->label())->toBe('Pending');
            expect(AiEmbeddingStatus::PROCESSING->label())->toBe('Processing');
            expect(AiEmbeddingStatus::READY->label())->toBe('Ready');
            expect(AiEmbeddingStatus::FAILED->label())->toBe('Failed');
        });
    });
});
