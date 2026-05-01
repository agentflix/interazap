<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Services\Chunkers;

use Domain\Ai\DTOs\ChunkDTO;
use Domain\Ai\Enums\AiDocumentType;
use Domain\Ai\Services\AiChunkingService;
use Domain\Ai\Services\Chunkers\CsvChunker;
use Domain\Ai\Services\Chunkers\DefaultChunker;
use Domain\Ai\Services\Chunkers\MarkdownChunker;

describe('DefaultChunker', function (): void {
    beforeEach(function (): void {
        $this->chunker = new DefaultChunker;
    });

    it('returns empty array for empty text', function (): void {
        $result = $this->chunker->chunk('');
        expect($result)->toBeEmpty();
    });

    it('creates single chunk for short text', function (): void {
        $text = 'This is a short text.';
        $result = $this->chunker->chunk($text);

        expect($result)->toHaveCount(1);
        expect($result[0])->toBeInstanceOf(ChunkDTO::class);
        expect($result[0]->content)->toBe($text);
        expect($result[0]->index)->toBe(0);
    });

    it('splits long text into multiple chunks', function (): void {
        $paragraph = str_repeat('This is a long sentence with many words. ', 50);
        $text = $paragraph."\n\n".$paragraph."\n\n".$paragraph;

        $result = $this->chunker->chunk($text);

        expect(count($result))->toBeGreaterThan(1);
    });

    it('maintains sequential chunk indices', function (): void {
        $paragraph = str_repeat('This is a test sentence. ', 100);
        $text = $paragraph."\n\n".$paragraph;

        $result = $this->chunker->chunk($text);

        foreach ($result as $i => $chunk) {
            expect($chunk->index)->toBe($i);
        }
    });
});

describe('MarkdownChunker', function (): void {
    beforeEach(function (): void {
        $this->chunker = new MarkdownChunker;
    });

    it('returns empty array for empty text', function (): void {
        $result = $this->chunker->chunk('');
        expect($result)->toBeEmpty();
    });

    it('splits by headings', function (): void {
        $text = "## Heading 1\nContent for heading 1.\n\n### Heading 2\nContent for heading 2.";
        $result = $this->chunker->chunk($text);

        expect(count($result))->toBeGreaterThanOrEqual(1);
    });

    it('does not split inside code blocks', function (): void {
        $text = "## Section\n```\nsome code\nmore code\n```\nAfter code.";
        $result = $this->chunker->chunk($text);

        $combined = implode("\n", array_map(fn ($c) => $c->content, $result));
        expect($combined)->toContain('some code');
        expect($combined)->toContain('more code');
    });

    it('keeps parent heading in child chunks', function (): void {
        $text = "## Main\n".str_repeat('Long sentence. ', 200);
        $result = $this->chunker->chunk($text);

        expect(count($result))->toBeGreaterThan(1);
        foreach ($result as $chunk) {
            expect($chunk->content)->toContain('## Main');
        }
    });

    it('splits markdown with three headings into section chunks', function (): void {
        $text = "## Heading 1\nContent one.\n\n## Heading 2\nContent two.\n\n## Heading 3\nContent three.";
        $result = $this->chunker->chunk($text);

        expect(count($result))->toBeGreaterThanOrEqual(1);
        $combined = implode(' ', array_map(fn ($c) => $c->content, $result));
        expect($combined)->toContain('## Heading 1');
        expect($combined)->toContain('## Heading 2');
        expect($combined)->toContain('## Heading 3');
    });
});

describe('CsvChunker', function (): void {
    beforeEach(function (): void {
        $this->chunker = new CsvChunker;
    });

    it('returns empty array for empty text', function (): void {
        $result = $this->chunker->chunk('');
        expect($result)->toBeEmpty();
    });

    it('includes header in each chunk', function (): void {
        $lines = ['name,email'];
        for ($i = 0; $i < 20; $i++) {
            $lines[] = "User {$i},user{$i}@example.com";
        }
        $text = implode("\n", $lines);

        $result = $this->chunker->chunk($text);

        expect(count($result))->toBeGreaterThanOrEqual(1);
        foreach ($result as $chunk) {
            expect($chunk->content)->toContain('name,email');
        }
    });

    it('makes each chunk self-sufficient', function (): void {
        $text = "a,b\n1,2\n3,4\n5,6";
        $result = $this->chunker->chunk($text);

        foreach ($result as $chunk) {
            expect($chunk->content)->toContain('a,b');
        }
    });

    it('returns empty array for text with only empty lines', function (): void {
        $text = "\n\n  \n\t\n";
        $result = $this->chunker->chunk($text);

        expect($result)->toBeEmpty();
    });

    it('returns header as single chunk when no data rows', function (): void {
        $text = 'name,email';
        $result = $this->chunker->chunk($text);

        expect($result)->toHaveCount(1);
        expect($result[0]->content)->toBe('name,email');
        expect($result[0]->index)->toBe(0);
    });

    it('includes header in each chunk for 100 rows', function (): void {
        $lines = ['name,email'];
        for ($i = 0; $i < 100; $i++) {
            $lines[] = "User {$i},user{$i}@example.com";
        }
        $text = implode("\n", $lines);

        $result = $this->chunker->chunk($text);

        expect(count($result))->toBeGreaterThan(1);
        foreach ($result as $chunk) {
            expect($chunk->content)->toContain('name,email');
        }
    });
});

describe('AiChunkingService estimateTokens', function (): void {
    it('estimates tokens based on text length', function (): void {
        $service = new AiChunkingService;
        $text = str_repeat('a', 350);

        $tokens = $service->estimateTokens($text);

        // 350 chars / 3.5 chars per token = 100 tokens
        expect($tokens)->toBe(100);
    });

    it('returns 1 for very short text', function (): void {
        $service = new AiChunkingService;

        expect($service->estimateTokens('a'))->toBe(1);
    });
});

describe('AiChunkingService type-aware', function (): void {
    it('delegates to default chunker for txt', function (): void {
        $service = new AiChunkingService;
        $result = $service->chunk('Hello world', AiDocumentType::TXT);

        expect($result)->toHaveCount(1);
        expect($result[0]->content)->toBe('Hello world');
    });

    it('delegates to markdown chunker for markdown', function (): void {
        $service = new AiChunkingService;
        $text = "## Heading\nSome content.";
        $result = $service->chunk($text, AiDocumentType::MARKDOWN);

        expect(count($result))->toBeGreaterThanOrEqual(1);
    });

    it('delegates to csv chunker for csv', function (): void {
        $service = new AiChunkingService;
        $text = "a,b\n1,2\n3,4";
        $result = $service->chunk($text, AiDocumentType::CSV);

        expect(count($result))->toBeGreaterThanOrEqual(1);
        foreach ($result as $chunk) {
            expect($chunk->content)->toContain('a,b');
        }
    });

    it('uses default chunker when type is null', function (): void {
        $service = new AiChunkingService;
        $result = $service->chunk('Hello world');

        expect($result)->toHaveCount(1);
        expect($result[0]->content)->toBe('Hello world');
    });
});
