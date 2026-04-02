<?php

declare(strict_types=1);

use Domain\Ai\Services\AiPromptHashService;

describe('AiPromptHashService', function (): void {
    beforeEach(function (): void {
        $this->hashService = new AiPromptHashService;
    });

    describe('hash', function (): void {
        it('returns sha256 hash', function (): void {
            $content = 'This is a test prompt';
            $hash = $this->hashService->hash($content);

            expect($hash)->toBeString()
                ->and(strlen($hash))->toBe(64); // SHA256 = 64 hex chars
        });

        it('returns same hash for same content', function (): void {
            $content = 'This is a test prompt';
            $hash1 = $this->hashService->hash($content);
            $hash2 = $this->hashService->hash($content);

            expect($hash1)->toBe($hash2);
        });

        it('returns different hash for different content', function (): void {
            $hash1 = $this->hashService->hash('Content A');
            $hash2 = $this->hashService->hash('Content B');

            expect($hash1)->not->toBe($hash2);
        });
    });

    describe('verify', function (): void {
        it('returns true for matching content', function (): void {
            $content = 'This is a test prompt';
            $hash = $this->hashService->hash($content);

            expect($this->hashService->verify($content, $hash))->toBeTrue();
        });

        it('returns false for tampered content', function (): void {
            $originalContent = 'This is a test prompt';
            $hash = $this->hashService->hash($originalContent);

            $tamperedContent = 'This is a modified prompt';

            expect($this->hashService->verify($tamperedContent, $hash))->toBeFalse();
        });

        it('returns false for invalid hash', function (): void {
            $content = 'This is a test prompt';
            $invalidHash = 'invalid_hash_value';

            expect($this->hashService->verify($content, $invalidHash))->toBeFalse();
        });
    });

    describe('getAlgorithm', function (): void {
        it('returns sha256', function (): void {
            expect($this->hashService->getAlgorithm())->toBe('sha256');
        });
    });
});
