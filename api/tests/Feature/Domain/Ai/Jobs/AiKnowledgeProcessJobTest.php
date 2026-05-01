<?php

declare(strict_types=1);

use Domain\Ai\Contracts\AiChunkingServiceInterface;
use Domain\Ai\Contracts\AiEmbeddingServiceInterface;
use Domain\Ai\DTOs\ChunkDTO;
use Domain\Ai\Enums\AiEmbeddingStatus;
use Domain\Ai\Jobs\AiKnowledgeProcessJob;
use Domain\Ai\Models\AiKnowledgeChunk;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

describe('AiKnowledgeProcessJob', function (): void {
    beforeEach(function (): void {
        Storage::fake();
    });

    it('processes a txt document and stores chunks', function (): void {
        $dimensions = (int) config('ai.embedding.dimensions', 512);

        $tenant = PlatformTenant::factory()->create();

        $document = AiKnowledgeDocument::factory()
            ->forTenant($tenant)
            ->txt()
            ->pending()
            ->create([
                'file_path' => 'knowledge/'.$tenant->id.'/doc.txt',
            ]);

        Storage::put($document->file_path, 'Hello world');

        $chunkingService = \Mockery::mock(AiChunkingServiceInterface::class);
        $chunkingService->shouldReceive('chunk')
            ->once()
            ->andReturn([
                new ChunkDTO(0, 'Hello', 1),
                new ChunkDTO(1, 'world', 1),
            ]);

        $embeddingService = \Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingService->shouldReceive('embedBatch')
            ->once()
            ->andReturn([
                array_fill(0, $dimensions, 0.1),
                array_fill(0, $dimensions, 0.2),
            ]);

        $job = new AiKnowledgeProcessJob($document->id);
        $job->handle($chunkingService, $embeddingService);

        $document->refresh();

        expect($document->embedding_status)->toBe(AiEmbeddingStatus::READY)
            ->and($document->chunk_count)->toBe(2)
            ->and($document->error_message)->toBeNull()
            ->and(AiKnowledgeChunk::query()->where('document_id', $document->id)->count())->toBe(2);
    });

    it('extracts csv content before chunking', function (): void {
        $dimensions = (int) config('ai.embedding.dimensions', 512);

        $tenant = PlatformTenant::factory()->create();

        $document = AiKnowledgeDocument::factory()
            ->forTenant($tenant)
            ->csv()
            ->pending()
            ->create([
                'file_path' => 'knowledge/'.$tenant->id.'/contacts.csv',
            ]);

        Storage::put($document->file_path, "name,email\nJohn Doe,john@example.com");

        $chunkingService = \Mockery::mock(AiChunkingServiceInterface::class);
        $chunkingService->shouldReceive('chunk')
            ->once()
            ->with('name: John Doe, email: john@example.com', \Mockery::any())
            ->andReturn([
                new ChunkDTO(0, 'row', 2),
            ]);

        $embeddingService = \Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingService->shouldReceive('embedBatch')
            ->once()
            ->andReturn([array_fill(0, $dimensions, 0.2)]);

        $job = new AiKnowledgeProcessJob($document->id);
        $job->handle($chunkingService, $embeddingService);

        $document->refresh();

        expect($document->embedding_status)->toBe(AiEmbeddingStatus::READY)
            ->and($document->chunk_count)->toBe(1);
    });

    it('keeps legacy csv extraction for files without headers', function (): void {
        $dimensions = (int) config('ai.embedding.dimensions', 512);

        $tenant = PlatformTenant::factory()->create();

        $document = AiKnowledgeDocument::factory()
            ->forTenant($tenant)
            ->csv()
            ->pending()
            ->create([
                'file_path' => 'knowledge/'.$tenant->id.'/numeric.csv',
            ]);

        Storage::put($document->file_path, "1,2\n3,4");

        $chunkingService = \Mockery::mock(AiChunkingServiceInterface::class);
        $chunkingService->shouldReceive('chunk')
            ->once()
            ->with("1 2\n3 4", \Mockery::any())
            ->andReturn([
                new ChunkDTO(0, 'row', 2),
            ]);

        $embeddingService = \Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingService->shouldReceive('embedBatch')
            ->once()
            ->andReturn([array_fill(0, $dimensions, 0.2)]);

        $job = new AiKnowledgeProcessJob($document->id);
        $job->handle($chunkingService, $embeddingService);

        $document->refresh();

        expect($document->embedding_status)->toBe(AiEmbeddingStatus::READY);
    });

    it('declares retry configuration explicitly', function (): void {
        $job = new AiKnowledgeProcessJob('doc-id');

        expect($job->maxTries)->toBe(3)
            ->and($job->backoff)->toBe([60, 300, 600]);
    });

    it('extracts json content before chunking', function (): void {
        $dimensions = (int) config('ai.embedding.dimensions', 512);

        $tenant = PlatformTenant::factory()->create();

        $document = AiKnowledgeDocument::factory()
            ->forTenant($tenant)
            ->json()
            ->pending()
            ->create([
                'file_path' => 'knowledge/'.$tenant->id.'/payload.json',
            ]);

        Storage::put($document->file_path, json_encode([
            'user' => ['name' => 'Jane', 'age' => 32],
            'active' => true,
        ]));

        $chunkingService = \Mockery::mock(AiChunkingServiceInterface::class);
        $chunkingService->shouldReceive('chunk')
            ->once()
            ->with(\Mockery::on(fn (string $content): bool => str_contains($content, 'user.name: Jane')
                && str_contains($content, 'user.age: 32')), \Mockery::any())
            ->andReturn([
                new ChunkDTO(0, 'json', 3),
            ]);

        $embeddingService = \Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingService->shouldReceive('embedBatch')
            ->once()
            ->andReturn([array_fill(0, $dimensions, 0.9)]);

        $job = new AiKnowledgeProcessJob($document->id);
        $job->handle($chunkingService, $embeddingService);

        $document->refresh();

        expect($document->embedding_status)->toBe(AiEmbeddingStatus::READY)
            ->and($document->chunk_count)->toBe(1);
    });

    it('extracts pdf content before chunking', function (): void {
        $dimensions = (int) config('ai.embedding.dimensions', 512);

        $tenant = PlatformTenant::factory()->create();

        $document = AiKnowledgeDocument::factory()
            ->forTenant($tenant)
            ->pdf()
            ->pending()
            ->create([
                'file_path' => 'knowledge/'.$tenant->id.'/manual.pdf',
            ]);

        Storage::put($document->file_path, '%PDF-1.4 binary');

        $parser = \Mockery::mock('overload:Smalot\\PdfParser\\Parser');
        $pdf = \Mockery::mock();

        $parser->shouldReceive('parseContent')
            ->once()
            ->andReturn($pdf);

        $pdf->shouldReceive('getText')
            ->once()
            ->andReturn('Conteúdo PDF extraído');

        $chunkingService = \Mockery::mock(AiChunkingServiceInterface::class);
        $chunkingService->shouldReceive('chunk')
            ->once()
            ->with('Conteúdo PDF extraído', \Mockery::any())
            ->andReturn([
                new ChunkDTO(0, 'pdf', 2),
            ]);

        $embeddingService = \Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingService->shouldReceive('embedBatch')
            ->once()
            ->andReturn([array_fill(0, $dimensions, 0.3)]);

        $job = new AiKnowledgeProcessJob($document->id);
        $job->handle($chunkingService, $embeddingService);

        $document->refresh();

        expect($document->embedding_status)->toBe(AiEmbeddingStatus::READY)
            ->and($document->chunk_count)->toBe(1);
    });

    it('normalizes oversized embeddings to configured dimensions', function (): void {
        config()->set('ai.embedding.dimensions', 512);

        $tenant = PlatformTenant::factory()->create();

        $document = AiKnowledgeDocument::factory()
            ->forTenant($tenant)
            ->txt()
            ->pending()
            ->create([
                'file_path' => 'knowledge/'.$tenant->id.'/oversized-embedding.txt',
            ]);

        Storage::put($document->file_path, 'Embedding payload mismatch');

        $chunkingService = \Mockery::mock(AiChunkingServiceInterface::class);
        $chunkingService->shouldReceive('chunk')
            ->once()
            ->andReturn([
                new ChunkDTO(0, 'Embedding payload mismatch', 3),
            ]);

        $embeddingService = \Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingService->shouldReceive('embedBatch')
            ->once()
            ->andReturn([
                array_fill(0, 1536, 0.1),
            ]);

        $job = new AiKnowledgeProcessJob($document->id);
        $job->handle($chunkingService, $embeddingService);

        $document->refresh();

        expect($document->embedding_status)->toBe(AiEmbeddingStatus::READY)
            ->and($document->chunk_count)->toBe(1)
            ->and($document->error_message)->toBeNull();
    });

    it('marks document as failed when file is missing', function (): void {
        $tenant = PlatformTenant::factory()->create();

        $document = AiKnowledgeDocument::factory()
            ->forTenant($tenant)
            ->txt()
            ->pending()
            ->create([
                'file_path' => 'knowledge/'.$tenant->id.'/missing.txt',
            ]);

        $chunkingService = \Mockery::mock(AiChunkingServiceInterface::class);
        $chunkingService->shouldNotReceive('chunk');

        $embeddingService = \Mockery::mock(AiEmbeddingServiceInterface::class);

        $job = new AiKnowledgeProcessJob($document->id);

        expect(fn () => $job->handle($chunkingService, $embeddingService))
            ->toThrow(RuntimeException::class);

        $document->refresh();

        expect($document->embedding_status)->toBe(AiEmbeddingStatus::FAILED)
            ->and($document->error_message)->not()->toBeNull();
    });

    it('marks secured pdf as failed without retrying', function (): void {
        $tenant = PlatformTenant::factory()->create();

        $document = AiKnowledgeDocument::factory()
            ->forTenant($tenant)
            ->pdf()
            ->pending()
            ->create([
                'file_path' => 'knowledge/'.$tenant->id.'/secured.pdf',
            ]);

        Storage::put($document->file_path, '%PDF-1.4 secured');

        $parser = \Mockery::mock('overload:Smalot\\PdfParser\\Parser');
        $parser->shouldReceive('parseContent')
            ->once()
            ->andThrow(new Exception('Secured pdf file are currently not supported.'));

        $chunkingService = \Mockery::mock(AiChunkingServiceInterface::class);
        $chunkingService->shouldNotReceive('chunk');

        $embeddingService = \Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingService->shouldNotReceive('embedBatch');

        $job = new AiKnowledgeProcessJob($document->id);
        $job->handle($chunkingService, $embeddingService);

        $document->refresh();

        expect($document->embedding_status)->toBe(AiEmbeddingStatus::FAILED)
            ->and($document->error_message)->toBe(
                'PDF protegido por senha não é suportado. Remova a proteção e envie novamente.',
            );
    });

    it('skips inactive documents', function (): void {
        $tenant = PlatformTenant::factory()->create();

        $document = AiKnowledgeDocument::factory()
            ->forTenant($tenant)
            ->inactive()
            ->ready()
            ->create();

        $chunkingService = \Mockery::mock(AiChunkingServiceInterface::class);
        $chunkingService->shouldNotReceive('chunk');

        $embeddingService = \Mockery::mock(AiEmbeddingServiceInterface::class);
        $embeddingService->shouldNotReceive('embedBatch');

        $job = new AiKnowledgeProcessJob($document->id);
        $job->handle($chunkingService, $embeddingService);

        $document->refresh();

        expect($document->embedding_status)->toBe(AiEmbeddingStatus::READY);
    });

    it('updates document when job permanently fails', function (): void {
        $tenant = PlatformTenant::factory()->create();

        $document = AiKnowledgeDocument::factory()
            ->forTenant($tenant)
            ->pending()
            ->create();

        $job = new AiKnowledgeProcessJob($document->id);
        $job->failed(new RuntimeException('Failure'));

        $document->refresh();

        expect($document->embedding_status)->toBe(AiEmbeddingStatus::FAILED)
            ->and($document->error_message)->toContain('Processing failed after multiple retries');
    });
});
