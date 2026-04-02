<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Ai\Models;

use Domain\Ai\Models\AiKnowledgeChunk;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('AiKnowledgeChunk', function (): void {
    beforeEach(function (): void {
        $this->tenant = PlatformTenant::factory()->create();
        $this->document = AiKnowledgeDocument::factory()
            ->forTenant($this->tenant)
            ->create();
    });

    describe('factory', function (): void {
        it('creates chunk with default values', function (): void {
            $chunk = AiKnowledgeChunk::factory()
                ->forDocument($this->document)
                ->create();

            expect($chunk->id)->not->toBeNull();
            expect($chunk->document_id)->toBe($this->document->id);
            expect($chunk->tenant_id)->toBe($this->tenant->id);
            expect($chunk->content)->not->toBeEmpty();
            expect($chunk->token_count)->toBeGreaterThan(0);
        });

        it('creates chunk with specific content', function (): void {
            $content = 'This is specific test content.';
            $chunk = AiKnowledgeChunk::factory()
                ->forDocument($this->document)
                ->withContent($content)
                ->create();

            expect($chunk->content)->toBe($content);
        });

        it('creates chunk at specific index', function (): void {
            $chunk = AiKnowledgeChunk::factory()
                ->forDocument($this->document)
                ->atIndex(5)
                ->create();

            expect($chunk->chunk_index)->toBe(5);
        });
    });

    describe('relationships', function (): void {
        it('belongs to document', function (): void {
            $chunk = AiKnowledgeChunk::factory()
                ->forDocument($this->document)
                ->create();

            expect($chunk->document->id)->toBe($this->document->id);
        });

        it('belongs to tenant', function (): void {
            $chunk = AiKnowledgeChunk::factory()
                ->forDocument($this->document)
                ->create();

            expect($chunk->tenant->id)->toBe($this->tenant->id);
        });
    });

    describe('scopes', function (): void {
        it('forDocument scope filters by document', function (): void {
            $otherDocument = AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->create();

            AiKnowledgeChunk::factory()
                ->forDocument($this->document)
                ->count(3)
                ->create();

            AiKnowledgeChunk::factory()
                ->forDocument($otherDocument)
                ->count(5)
                ->create();

            $count = AiKnowledgeChunk::forDocument($this->document->id)->count();
            expect($count)->toBe(3);
        });

        it('forTenant scope filters by tenant', function (): void {
            $otherTenant = PlatformTenant::factory()->create();
            $otherDocument = AiKnowledgeDocument::factory()
                ->forTenant($otherTenant)
                ->create();

            AiKnowledgeChunk::factory()
                ->forDocument($this->document)
                ->count(2)
                ->create();

            AiKnowledgeChunk::factory()
                ->forDocument($otherDocument)
                ->count(4)
                ->create();

            $count = AiKnowledgeChunk::forTenant($this->tenant->id)->count();
            expect($count)->toBe(2);
        });
    });

    describe('accessors', function (): void {
        it('hasEmbedding returns false when no embedding', function (): void {
            $chunk = AiKnowledgeChunk::factory()
                ->forDocument($this->document)
                ->create();

            expect($chunk->hasEmbedding())->toBeFalse();
        });

        it('getContentPreview returns full content for short text', function (): void {
            $content = 'Short text.';
            $chunk = AiKnowledgeChunk::factory()
                ->forDocument($this->document)
                ->withContent($content)
                ->create();

            expect($chunk->getContentPreview())->toBe($content);
        });

        it('getContentPreview truncates long text', function (): void {
            $content = str_repeat('a', 200);
            $chunk = AiKnowledgeChunk::factory()
                ->forDocument($this->document)
                ->withContent($content)
                ->create();

            $preview = $chunk->getContentPreview(100);
            expect(strlen($preview))->toBe(103); // 100 + '...'
            expect($preview)->toEndWith('...');
        });
    });

    describe('hidden attributes', function (): void {
        it('hides embedding from serialization', function (): void {
            $chunk = AiKnowledgeChunk::factory()
                ->forDocument($this->document)
                ->create();

            $array = $chunk->toArray();
            expect($array)->not->toHaveKey('embedding');
        });
    });
});
