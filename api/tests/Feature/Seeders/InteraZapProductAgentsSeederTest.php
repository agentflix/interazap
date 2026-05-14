<?php

declare(strict_types=1);

use Database\Seeders\InteraZapProductAgentsSeeder;
use Domain\Ai\Enums\AiEmbeddingStatus;
use Domain\Ai\Jobs\AiKnowledgeProcessJob;
use Domain\Ai\Models\AiKnowledgeChunk;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

uses()->group('seeders', 'ai');

test('seeder materializes knowledge file and queues processing job', function (): void {
    Bus::fake();
    Storage::fake('local');

    $tenant = PlatformTenant::factory()->create([
        'tenant_code' => 'AGENTFLX',
    ]);

    $this->seed(InteraZapProductAgentsSeeder::class);

    $document = AiKnowledgeDocument::query()
        ->where('tenant_id', $tenant->id)
        ->where('name', 'Catalogo Oficial InteraZap')
        ->first();

    expect($document)->not()->toBeNull();
    expect($document?->embedding_status)->toBe(AiEmbeddingStatus::PENDING)
        ->and($document?->chunk_count)->toBe(0)
        ->and($document?->file_path)->not()->toBeNull();

    $filePath = (string) $document?->file_path;

    expect(Storage::disk('local')->exists($filePath))->toBeTrue()
        ->and(Storage::disk('local')->get($filePath))->toContain('InteraZap')
        ->and(AiKnowledgeChunk::query()->where('document_id', $document?->id)->count())->toBe(0);

    Bus::assertDispatched(AiKnowledgeProcessJob::class, fn (AiKnowledgeProcessJob $job): bool => $job->documentId === $document?->id);
});
