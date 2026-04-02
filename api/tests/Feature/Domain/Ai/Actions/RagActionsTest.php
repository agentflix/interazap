<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Ai\Actions;

use Domain\Ai\Actions\Rag\DeleteDocumentAction;
use Domain\Ai\Actions\Rag\GetKnowledgeStatsAction;
use Domain\Ai\Actions\Rag\ReindexDocumentAction;
use Domain\Ai\Actions\Rag\UploadDocumentAction;
use Domain\Ai\Contracts\AiStorageLimitServiceInterface;
use Domain\Ai\Enums\AiEmbeddingStatus;
use Domain\Ai\Exceptions\StorageLimitExceededException;
use Domain\Ai\Models\AiKnowledgeChunk;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

describe('UploadDocumentAction', function (): void {
    beforeEach(function (): void {
        Storage::fake('local');
        Queue::fake();
        $this->tenant = PlatformTenant::factory()->create();
        $this->action = app(UploadDocumentAction::class);
    });

    it('creates document from uploaded file', function (): void {
        $file = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

        $document = $this->action->execute($this->tenant, $file, 'Test Document');

        expect($document)->toBeInstanceOf(AiKnowledgeDocument::class);
        expect($document->name)->toBe('Test Document');
        expect($document->tenant_id)->toBe($this->tenant->id);
        expect($document->embedding_status)->toBe(AiEmbeddingStatus::PENDING);
    });

    it('uses filename as name if not provided', function (): void {
        $file = UploadedFile::fake()->create('my-document.txt', 100, 'text/plain');

        $document = $this->action->execute($this->tenant, $file);

        expect($document->name)->toBe('my-document');
    });

    it('stores file in tenant-specific path', function (): void {
        $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');

        $document = $this->action->execute($this->tenant, $file);

        expect($document->file_path)->toContain("knowledge/{$this->tenant->id}");
        Storage::assertExists($document->file_path);
    });

    it('creates new version for duplicate name', function (): void {
        $existingDoc = AiKnowledgeDocument::factory()
            ->forTenant($this->tenant)
            ->create(['name' => 'My Doc', 'version' => 1]);

        $file = UploadedFile::fake()->create('doc.txt', 100, 'text/plain');
        $newDoc = $this->action->execute($this->tenant, $file, 'My Doc');

        expect($newDoc->version)->toBe(2);

        $existingDoc->refresh();
        expect($existingDoc->is_active)->toBeFalse();
        expect($existingDoc->replaced_by)->toBe($newDoc->id);
    });

    it('throws exception when storage limit exceeded', function (): void {
        $mockService = \Mockery::mock(AiStorageLimitServiceInterface::class);
        $mockService->shouldReceive('canUpload')->andReturn(false);
        $mockService->shouldReceive('getCurrentUsage')->andReturn(100000000);
        $mockService->shouldReceive('getStorageLimit')->andReturn(100000000);

        $action = new UploadDocumentAction($mockService);
        $file = UploadedFile::fake()->create('test.txt', 100, 'text/plain');

        $action->execute($this->tenant, $file);
    })->throws(StorageLimitExceededException::class);
});

describe('DeleteDocumentAction', function (): void {
    beforeEach(function (): void {
        Storage::fake('local');
        $this->tenant = PlatformTenant::factory()->create();
        $this->action = new DeleteDocumentAction;
    });

    it('soft deletes document', function (): void {
        $document = AiKnowledgeDocument::factory()
            ->forTenant($this->tenant)
            ->create();

        $this->action->execute($document);

        $document->refresh();
        expect($document->is_active)->toBeFalse();
    });

    it('optionally deletes file from storage', function (): void {
        Storage::put('knowledge/test/file.txt', 'content');

        $document = AiKnowledgeDocument::factory()
            ->forTenant($this->tenant)
            ->create(['file_path' => 'knowledge/test/file.txt']);

        $this->action->execute($document, deleteFile: true);

        Storage::assertMissing('knowledge/test/file.txt');
    });
});

describe('ReindexDocumentAction', function (): void {
    beforeEach(function (): void {
        Queue::fake();
        $this->tenant = PlatformTenant::factory()->create();
        $this->action = new ReindexDocumentAction;
    });

    it('resets document to pending', function (): void {
        $document = AiKnowledgeDocument::factory()
            ->forTenant($this->tenant)
            ->ready(10)
            ->create();

        $result = $this->action->execute($document);

        expect($result->embedding_status)->toBe(AiEmbeddingStatus::PENDING);
        expect($result->chunk_count)->toBe(0);
    });

    it('deletes existing chunks', function (): void {
        $document = AiKnowledgeDocument::factory()
            ->forTenant($this->tenant)
            ->ready()
            ->create();

        AiKnowledgeChunk::factory()
            ->forDocument($document)
            ->count(5)
            ->create();

        $this->action->execute($document);

        expect(\Domain\Ai\Models\AiKnowledgeChunk::query()->where('document_id', $document->id)->count())->toBe(0);
    });

    it('throws exception for inactive document', function (): void {
        $document = AiKnowledgeDocument::factory()
            ->forTenant($this->tenant)
            ->inactive()
            ->create();

        $this->action->execute($document);
    })->throws(\InvalidArgumentException::class);

    it('throws exception for processing document', function (): void {
        $document = AiKnowledgeDocument::factory()
            ->forTenant($this->tenant)
            ->processing()
            ->create();

        $this->action->execute($document);
    })->throws(\InvalidArgumentException::class);
});

describe('GetKnowledgeStatsAction', function (): void {
    beforeEach(function (): void {
        $this->tenant = PlatformTenant::factory()->create();
        $this->action = app(GetKnowledgeStatsAction::class);
    });

    it('returns accurate document counts by status', function (): void {
        AiKnowledgeDocument::factory()
            ->forTenant($this->tenant)
            ->ready()
            ->count(3)
            ->create();

        AiKnowledgeDocument::factory()
            ->forTenant($this->tenant)
            ->processing()
            ->count(2)
            ->create();

        AiKnowledgeDocument::factory()
            ->forTenant($this->tenant)
            ->failed()
            ->create();

        $stats = $this->action->execute($this->tenant);

        expect($stats->documentCount)->toBe(6);
        expect($stats->documentsReady)->toBe(3);
        expect($stats->documentsProcessing)->toBe(2);
        expect($stats->documentsFailed)->toBe(1);
    });

    it('calculates storage correctly', function (): void {
        AiKnowledgeDocument::factory()
            ->forTenant($this->tenant)
            ->withFileSize(1000)
            ->create();

        AiKnowledgeDocument::factory()
            ->forTenant($this->tenant)
            ->withFileSize(2000)
            ->create();

        $stats = $this->action->execute($this->tenant);

        expect($stats->totalStorageBytes)->toBe(3000);
    });

    it('counts chunks correctly', function (): void {
        $document = AiKnowledgeDocument::factory()
            ->forTenant($this->tenant)
            ->create();

        AiKnowledgeChunk::factory()
            ->forDocument($document)
            ->count(10)
            ->create();

        $stats = $this->action->execute($this->tenant);

        expect($stats->totalChunks)->toBe(10);
    });

    it('excludes inactive documents', function (): void {
        AiKnowledgeDocument::factory()
            ->forTenant($this->tenant)
            ->withFileSize(1000)
            ->create();

        AiKnowledgeDocument::factory()
            ->forTenant($this->tenant)
            ->withFileSize(5000)
            ->inactive()
            ->create();

        $stats = $this->action->execute($this->tenant);

        expect($stats->documentCount)->toBe(1);
    });
});
