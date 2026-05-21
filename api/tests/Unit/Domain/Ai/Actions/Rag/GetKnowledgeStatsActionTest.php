<?php

declare(strict_types=1);

use Domain\Ai\Actions\Rag\GetKnowledgeStatsAction;
use Domain\Ai\Contracts\AiStorageLimitServiceInterface;
use Domain\Ai\Enums\AiEmbeddingStatus;
use Domain\Ai\Models\AiKnowledgeChunk;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Platform\Models\PlatformTenant;

uses(\Illuminate\Foundation\Testing\LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();

    $this->storageLimitService = Mockery::mock(AiStorageLimitServiceInterface::class);
    $this->storageLimitService->shouldReceive('getCurrentUsage')->andReturn(1024 * 1024); // 1 MB
    $this->storageLimitService->shouldReceive('getStorageLimit')->andReturn(10 * 1024 * 1024); // 10 MB

    $this->action = new GetKnowledgeStatsAction($this->storageLimitService);
});

describe('execute', function (): void {
    it('returns stats for empty knowledge base', function (): void {
        $result = $this->action->execute($this->tenant);

        expect($result->documentCount)->toBe(0)
            ->and($result->documentsReady)->toBe(0)
            ->and($result->documentsProcessing)->toBe(0)
            ->and($result->documentsPending)->toBe(0)
            ->and($result->documentsFailed)->toBe(0)
            ->and($result->totalChunks)->toBe(0)
            ->and($result->totalStorageBytes)->toBe(1024 * 1024)
            ->and($result->storageLimitBytes)->toBe(10 * 1024 * 1024)
            ->and($result->storageUsedPercent)->toBe(10.0);
    });

    it('counts documents by status', function (): void {
        // Create documents with different statuses
        AiKnowledgeDocument::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'embedding_status' => AiEmbeddingStatus::READY,
        ]);

        AiKnowledgeDocument::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'embedding_status' => AiEmbeddingStatus::PROCESSING,
        ]);

        AiKnowledgeDocument::factory()->count(1)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'embedding_status' => AiEmbeddingStatus::PENDING,
        ]);

        AiKnowledgeDocument::factory()->count(1)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'embedding_status' => AiEmbeddingStatus::FAILED,
        ]);

        $result = $this->action->execute($this->tenant);

        expect($result->documentCount)->toBe(7)
            ->and($result->documentsReady)->toBe(3)
            ->and($result->documentsProcessing)->toBe(2)
            ->and($result->documentsPending)->toBe(1)
            ->and($result->documentsFailed)->toBe(1);
    });

    it('excludes inactive documents from count', function (): void {
        AiKnowledgeDocument::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'embedding_status' => AiEmbeddingStatus::READY,
        ]);

        AiKnowledgeDocument::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
            'embedding_status' => AiEmbeddingStatus::READY,
        ]);

        $result = $this->action->execute($this->tenant);

        expect($result->documentCount)->toBe(2);
    });

    it('counts chunks for tenant', function (): void {
        $document = AiKnowledgeDocument::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'embedding_status' => AiEmbeddingStatus::READY,
        ]);

        AiKnowledgeChunk::factory()->count(10)->create([
            'document_id' => $document->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->action->execute($this->tenant);

        expect($result->totalChunks)->toBe(10);
    });

    it('calculates storage used percent correctly', function (): void {
        $storageLimitService = Mockery::mock(AiStorageLimitServiceInterface::class);
        $storageLimitService->shouldReceive('getCurrentUsage')->andReturn(5 * 1024 * 1024);
        $storageLimitService->shouldReceive('getStorageLimit')->andReturn(10 * 1024 * 1024);

        $action = new GetKnowledgeStatsAction($storageLimitService);
        $result = $action->execute($this->tenant);

        expect($result->storageUsedPercent)->toBe(50.0);
    });

    it('handles zero storage limit', function (): void {
        $storageLimitService = Mockery::mock(AiStorageLimitServiceInterface::class);
        $storageLimitService->shouldReceive('getCurrentUsage')->andReturn(1024);
        $storageLimitService->shouldReceive('getStorageLimit')->andReturn(0);

        $action = new GetKnowledgeStatsAction($storageLimitService);
        $result = $action->execute($this->tenant);

        expect($result->storageUsedPercent)->toBe(0.0);
    });

    it('excludes other tenant documents', function (): void {
        $otherTenant = PlatformTenant::factory()->create();

        AiKnowledgeDocument::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'embedding_status' => AiEmbeddingStatus::READY,
        ]);

        AiKnowledgeDocument::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
            'is_active' => true,
            'embedding_status' => AiEmbeddingStatus::READY,
        ]);

        $result = $this->action->execute($this->tenant);

        expect($result->documentCount)->toBe(2);
    });
});
