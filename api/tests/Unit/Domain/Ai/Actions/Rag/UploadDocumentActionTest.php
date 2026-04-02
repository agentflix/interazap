<?php

declare(strict_types=1);

use Domain\Ai\Actions\Rag\UploadDocumentAction;
use Domain\Ai\Contracts\AiStorageLimitServiceInterface;
use Domain\Ai\Enums\AiDocumentType;
use Domain\Ai\Enums\AiEmbeddingStatus;
use Domain\Ai\Exceptions\StorageLimitExceededException;
use Domain\Ai\Jobs\AiKnowledgeProcessJob;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Queue::fake();

    $this->tenant = PlatformTenant::factory()->create();

    $this->storageLimitService = Mockery::mock(AiStorageLimitServiceInterface::class);
    $this->storageLimitService->shouldReceive('canUpload')->andReturn(true);
    $this->storageLimitService->shouldReceive('getCurrentUsage')->andReturn(0);
    $this->storageLimitService->shouldReceive('getStorageLimit')->andReturn(10 * 1024 * 1024);

    $this->action = new UploadDocumentAction($this->storageLimitService);
});

describe('execute', function (): void {
    it('uploads and creates a document', function (): void {
        $file = UploadedFile::fake()->create('test.txt', 100);

        $result = $this->action->execute($this->tenant, $file);

        expect($result)->toBeInstanceOf(AiKnowledgeDocument::class)
            ->and($result->tenant_id)->toBe($this->tenant->id)
            ->and($result->original_filename)->toBe('test.txt')
            ->and($result->file_type)->toBe(AiDocumentType::TXT)
            ->and($result->embedding_status)->toBe(AiEmbeddingStatus::PENDING)
            ->and($result->is_active)->toBeTrue()
            ->and($result->version)->toBe(1);

        Queue::assertPushed(AiKnowledgeProcessJob::class);
    });

    it('uses custom name when provided', function (): void {
        $file = UploadedFile::fake()->create('original.txt', 100);

        $result = $this->action->execute($this->tenant, $file, 'Custom Document');

        expect($result->name)->toBe('Custom Document');
    });

    it('uses filename without extension as name', function (): void {
        $file = UploadedFile::fake()->create('my-document.txt', 100);

        $result = $this->action->execute($this->tenant, $file);

        expect($result->name)->toBe('my-document');
    });

    it('stores file in tenant-specific path', function (): void {
        $file = UploadedFile::fake()->create('test.txt', 100);

        $result = $this->action->execute($this->tenant, $file);

        expect($result->file_path)->toContain("knowledge/{$this->tenant->id}/");
        Storage::assertExists($result->file_path);
    });

    it('creates versioned document when same name exists', function (): void {
        $file1 = UploadedFile::fake()->create('document.txt', 100);
        $file2 = UploadedFile::fake()->create('document.txt', 100);

        $result1 = $this->action->execute($this->tenant, $file1, 'Document');
        $result2 = $this->action->execute($this->tenant, $file2, 'Document');

        expect($result1->version)->toBe(1)
            ->and($result2->version)->toBe(2)
            ->and($result1->fresh()->is_active)->toBeFalse()
            ->and($result1->fresh()->replaced_by)->toBe($result2->id);
    });

    it('throws exception when storage limit exceeded', function (): void {
        $storageLimitService = Mockery::mock(AiStorageLimitServiceInterface::class);
        $storageLimitService->shouldReceive('canUpload')->andReturn(false);
        $storageLimitService->shouldReceive('getCurrentUsage')->andReturn(10 * 1024 * 1024);
        $storageLimitService->shouldReceive('getStorageLimit')->andReturn(10 * 1024 * 1024);

        $action = new UploadDocumentAction($storageLimitService);
        $file = UploadedFile::fake()->create('large.txt', 100);

        expect(fn (): \Domain\Ai\Models\AiKnowledgeDocument => $action->execute($this->tenant, $file))
            ->toThrow(StorageLimitExceededException::class);
    });

    it('throws exception for unsupported file type', function (): void {
        $file = UploadedFile::fake()->create('test.exe', 100);

        expect(fn () => $this->action->execute($this->tenant, $file))
            ->toThrow(InvalidArgumentException::class, 'Unsupported file type');
    });

    it('handles csv file type', function (): void {
        $file = UploadedFile::fake()->create('data.csv', 100);

        $result = $this->action->execute($this->tenant, $file);

        expect($result->file_type)->toBe(AiDocumentType::CSV);
    });

    it('handles json file type', function (): void {
        $file = UploadedFile::fake()->create('config.json', 100);

        $result = $this->action->execute($this->tenant, $file);

        expect($result->file_type)->toBe(AiDocumentType::JSON);
    });

    it('handles md file type', function (): void {
        $file = UploadedFile::fake()->create('readme.md', 100);

        $result = $this->action->execute($this->tenant, $file);

        expect($result->file_type)->toBe(AiDocumentType::MARKDOWN);
    });

    it('dispatches processing job', function (): void {
        $file = UploadedFile::fake()->create('test.txt', 100);

        $result = $this->action->execute($this->tenant, $file);

        Queue::assertPushed(AiKnowledgeProcessJob::class, function ($job): true {
            return true; // Job was dispatched
        });
    });
});
