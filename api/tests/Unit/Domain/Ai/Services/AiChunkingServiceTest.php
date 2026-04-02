<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Services;

use Domain\Ai\DTOs\ChunkDTO;
use Domain\Ai\Services\AiChunkingService;

describe('AiChunkingService', function (): void {
    beforeEach(function (): void {
        $this->service = new AiChunkingService;
    });

    describe('chunk()', function (): void {
        it('returns empty array for empty text', function (): void {
            $result = $this->service->chunk('');
            expect($result)->toBeEmpty();
        });

        it('returns empty array for whitespace only text', function (): void {
            $result = $this->service->chunk('   ');
            expect($result)->toBeEmpty();
        });

        it('creates single chunk for short text', function (): void {
            $text = 'This is a short text.';
            $result = $this->service->chunk($text);

            expect($result)->toHaveCount(1);
            expect($result[0])->toBeInstanceOf(ChunkDTO::class);
            expect($result[0]->content)->toBe($text);
            expect($result[0]->index)->toBe(0);
        });

        it('preserves paragraph structure', function (): void {
            $text = "First paragraph.\n\nSecond paragraph.";
            $result = $this->service->chunk($text);

            expect($result)->toHaveCount(1);
            expect($result[0]->content)->toContain('First paragraph');
            expect($result[0]->content)->toContain('Second paragraph');
        });

        it('splits long text into multiple chunks', function (): void {
            // Create a text that's definitely longer than 500 tokens
            $paragraph = str_repeat('This is a long sentence with many words. ', 50);
            $text = $paragraph."\n\n".$paragraph."\n\n".$paragraph;

            $result = $this->service->chunk($text);

            expect(count($result))->toBeGreaterThan(1);
        });

        it('maintains sequential chunk indices', function (): void {
            $paragraph = str_repeat('This is a test sentence. ', 100);
            $text = $paragraph."\n\n".$paragraph;

            $result = $this->service->chunk($text);

            foreach ($result as $i => $chunk) {
                expect($chunk->index)->toBe($i);
            }
        });

        it('chunks have token count within expected range', function (): void {
            $paragraph = str_repeat('Word ', 200);
            $text = $paragraph."\n\n".$paragraph;

            $result = $this->service->chunk($text);

            foreach ($result as $chunk) {
                // Token count should be reasonable (not exact due to estimation)
                expect($chunk->tokenCount)->toBeGreaterThan(0);
                expect($chunk->tokenCount)->toBeLessThanOrEqual(600); // Allow some margin
            }
        });

        it('handles content with special characters', function (): void {
            $text = "Special chars: @#$%^&*()!\n\nUnicode: 日本語 العربية";
            $result = $this->service->chunk($text);

            expect($result)->toHaveCount(1);
            expect($result[0]->content)->toContain('Special chars');
            expect($result[0]->content)->toContain('Unicode');
        });

        it('splits paragraphs that exceed token limit by sentences', function (): void {
            // Create a paragraph with many sentences that exceeds token limit
            $sentences = [];
            for ($i = 0; $i < 50; $i++) {
                $sentences[] = "This is sentence number {$i} which contains some text.";
            }
            $text = implode(' ', $sentences);

            $result = $this->service->chunk($text);

            expect(count($result))->toBeGreaterThanOrEqual(1);
            foreach ($result as $chunk) {
                expect($chunk->tokenCount)->toBeLessThanOrEqual(600);
            }
        });
    });

    describe('estimateTokens()', function (): void {
        it('returns 0 for empty string', function (): void {
            expect($this->service->estimateTokens(''))->toBe(0);
        });

        it('estimates tokens roughly correctly', function (): void {
            // ~4 chars per token estimate
            $text = str_repeat('word ', 100); // 500 chars = ~125 tokens
            $tokens = $this->service->estimateTokens($text);

            expect($tokens)->toBeGreaterThan(100);
            expect($tokens)->toBeLessThan(200);
        });

        it('handles unicode correctly', function (): void {
            $text = '日本語テスト'; // 6 Japanese characters
            $tokens = $this->service->estimateTokens($text);

            expect($tokens)->toBeGreaterThan(0);
        });

        it('uses configured chars per token', function (): void {
            config(['ai.chunking.chars_per_token' => 2.0]);

            $tokens = $this->service->estimateTokens('12345678');

            expect($tokens)->toBe(4);
        });
    });
});
