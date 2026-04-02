<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Ai\Models;

use Domain\Ai\Enums\AiDocumentType;
use Domain\Ai\Enums\AiEmbeddingStatus;
use Domain\Ai\Models\AiKnowledgeChunk;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('AiKnowledgeDocument', function (): void {
    beforeEach(function (): void {
        $this->tenant = PlatformTenant::factory()->create();
    });

    describe('factory', function (): void {
        it('creates document with default values', function (): void {
            $document = AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->create();

            expect($document->id)->not->toBeNull();
            expect($document->tenant_id)->toBe($this->tenant->id);
            expect($document->version)->toBe(1);
            expect($document->is_active)->toBeTrue();
            expect($document->embedding_status)->toBe(AiEmbeddingStatus::PENDING);
        });

        it('creates ready document with chunks', function (): void {
            $document = AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->ready(10)
                ->create();

            expect($document->embedding_status)->toBe(AiEmbeddingStatus::READY);
            expect($document->chunk_count)->toBe(10);
        });

        it('creates failed document with error message', function (): void {
            $document = AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->failed('Custom error message')
                ->create();

            expect($document->embedding_status)->toBe(AiEmbeddingStatus::FAILED);
            expect($document->error_message)->toBe('Custom error message');
        });

        it('creates specific file type documents', function (): void {
            $txt = AiKnowledgeDocument::factory()->txt()->create();
            $csv = AiKnowledgeDocument::factory()->csv()->create();
            $md = AiKnowledgeDocument::factory()->markdown()->create();
            $json = AiKnowledgeDocument::factory()->json()->create();

            expect($txt->file_type)->toBe(AiDocumentType::TXT);
            expect($csv->file_type)->toBe(AiDocumentType::CSV);
            expect($md->file_type)->toBe(AiDocumentType::MARKDOWN);
            expect($json->file_type)->toBe(AiDocumentType::JSON);
        });
    });

    describe('relationships', function (): void {
        it('belongs to tenant', function (): void {
            $document = AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->create();

            expect($document->tenant->id)->toBe($this->tenant->id);
        });

        it('has many chunks', function (): void {
            $document = AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->create();

            AiKnowledgeChunk::factory()
                ->forDocument($document)
                ->count(3)
                ->create();

            expect($document->chunks)->toHaveCount(3);
        });

        it('has replaced by relationship', function (): void {
            $oldDocument = AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->create();

            $newDocument = AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->create();

            $oldDocument->update(['replaced_by' => $newDocument->id]);

            expect($oldDocument->replacedByDocument->id)->toBe($newDocument->id);
        });
    });

    describe('scopes', function (): void {
        it('active scope filters inactive documents', function (): void {
            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->count(2)
                ->create();

            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->inactive()
                ->count(3)
                ->create();

            $active = AiKnowledgeDocument::active()->count();
            expect($active)->toBe(2);
        });

        it('ready scope filters non-ready documents', function (): void {
            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->ready()
                ->count(2)
                ->create();

            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->pending()
                ->count(3)
                ->create();

            $ready = AiKnowledgeDocument::ready()->count();
            expect($ready)->toBe(2);
        });

        it('forTenant scope filters by tenant', function (): void {
            $otherTenant = PlatformTenant::factory()->create();

            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->count(2)
                ->create();

            AiKnowledgeDocument::factory()
                ->forTenant($otherTenant)
                ->count(5)
                ->create();

            $tenantDocs = AiKnowledgeDocument::forTenant($this->tenant->id)->count();
            expect($tenantDocs)->toBe(2);
        });

        it('searchable scope combines active and ready', function (): void {
            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->ready()
                ->count(2)
                ->create();

            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->ready()
                ->inactive()
                ->create();

            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->pending()
                ->create();

            $searchable = AiKnowledgeDocument::searchable()->count();
            expect($searchable)->toBe(2);
        });
    });

    describe('accessors', function (): void {
        it('isReady returns correct value', function (): void {
            $ready = AiKnowledgeDocument::factory()->ready()->create();
            $pending = AiKnowledgeDocument::factory()->pending()->create();

            expect($ready->isReady())->toBeTrue();
            expect($pending->isReady())->toBeFalse();
        });

        it('isProcessing returns correct value', function (): void {
            $processing = AiKnowledgeDocument::factory()->processing()->create();
            $ready = AiKnowledgeDocument::factory()->ready()->create();

            expect($processing->isProcessing())->toBeTrue();
            expect($ready->isProcessing())->toBeFalse();
        });

        it('isFailed returns correct value', function (): void {
            $failed = AiKnowledgeDocument::factory()->failed()->create();
            $ready = AiKnowledgeDocument::factory()->ready()->create();

            expect($failed->isFailed())->toBeTrue();
            expect($ready->isFailed())->toBeFalse();
        });

        it('canReprocess returns correct value', function (): void {
            $ready = AiKnowledgeDocument::factory()->ready()->create();
            $failed = AiKnowledgeDocument::factory()->failed()->create();
            $processing = AiKnowledgeDocument::factory()->processing()->create();
            $pending = AiKnowledgeDocument::factory()->pending()->create();

            expect($ready->canReprocess())->toBeTrue();
            expect($failed->canReprocess())->toBeTrue();
            expect($processing->canReprocess())->toBeFalse();
            expect($pending->canReprocess())->toBeFalse();
        });

        it('getFormattedFileSize returns human readable size', function (): void {
            $doc1 = AiKnowledgeDocument::factory()->withFileSize(500)->create();
            $doc2 = AiKnowledgeDocument::factory()->withFileSize(2048)->create();
            $doc3 = AiKnowledgeDocument::factory()->withFileSize(1048576)->create();

            expect($doc1->getFormattedFileSize())->toBe('500 B');
            expect($doc2->getFormattedFileSize())->toBe('2 KB');
            expect($doc3->getFormattedFileSize())->toBe('1 MB');
        });
    });

    describe('deleting event', function (): void {
        it('deletes associated chunks on delete', function (): void {
            $document = AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->create();

            AiKnowledgeChunk::factory()
                ->forDocument($document)
                ->count(5)
                ->create();

            expect(\Domain\Ai\Models\AiKnowledgeChunk::query()->where('document_id', $document->id)->count())->toBe(5);

            $document->delete();

            expect(\Domain\Ai\Models\AiKnowledgeChunk::query()->where('document_id', $document->id)->count())->toBe(0);
        });
    });
});
