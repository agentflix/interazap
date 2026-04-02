<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Ai\Controllers;

use Domain\Ai\Enums\AiEmbeddingStatus;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

uses(LazilyRefreshDatabase::class);

describe('AiKnowledgeController', function (): void {
    beforeEach(function (): void {
        Storage::fake('local');
        Queue::fake();

        $this->tenant = PlatformTenant::factory()->create();
        $this->user = AuthUser::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $permissions = [
            'ai.knowledge.manage',
            'ai.knowledge.view',
            'ai.autopilots.manage',
        ];

        foreach ($permissions as $permissionName) {
            $permission = AuthPermission::query()->firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'sanctum'],
                ['id' => (string) Str::orderedUuid()]
            );
            $this->user->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->refresh();
    });

    describe('GET /api/ai/knowledge', function (): void {
        it('returns empty list when no documents', function (): void {
            $response = $this->actingAs($this->user)
                ->getJson('/api/ai/knowledge');

            $response->assertOk()
                ->assertJsonPath('data', []);
        });

        it('returns tenant documents', function (): void {
            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->ready()
                ->count(3)
                ->create();

            $response = $this->actingAs($this->user)
                ->getJson('/api/ai/knowledge');

            $response->assertOk()
                ->assertJsonCount(3, 'data');
        });

        it('caches list responses for 5 minutes', function (): void {
            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->ready()
                ->count(2)
                ->create();

            $tenantId = $this->tenant->id;

            Cache::flush();

            $response = $this->actingAs($this->user)
                ->getJson('/api/ai/knowledge');

            $response->assertOk()
                ->assertJsonCount(2, 'data');

            expect(Cache::has("ai.knowledge.documents.{$tenantId}.page.1.per_page.20.search.none"))->toBeTrue();
        });

        it('filters documents by name using search param', function (): void {
            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->create(['name' => 'Manual de Produto']);

            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->create(['name' => 'Política de Privacidade']);

            $response = $this->actingAs($this->user)
                ->getJson('/api/ai/knowledge?search=manual');

            $response->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.name', 'Manual de Produto');
        });

        it('uses dynamic per_page for server-side pagination', function (): void {
            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->count(3)
                ->create();

            $response = $this->actingAs($this->user)
                ->getJson('/api/ai/knowledge?per_page=2');

            $response->assertOk()
                ->assertJsonCount(2, 'data')
                ->assertJsonPath('meta.per_page', 2)
                ->assertJsonPath('meta.total', 3);
        });

        it('excludes inactive documents', function (): void {
            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->create();

            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->inactive()
                ->create();

            $response = $this->actingAs($this->user)
                ->getJson('/api/ai/knowledge');

            $response->assertOk()
                ->assertJsonCount(1, 'data');
        });

        it('excludes documents from other tenants', function (): void {
            $otherTenant = PlatformTenant::factory()->create();

            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->create();

            AiKnowledgeDocument::factory()
                ->forTenant($otherTenant)
                ->count(5)
                ->create();

            $response = $this->actingAs($this->user)
                ->getJson('/api/ai/knowledge');

            $response->assertOk()
                ->assertJsonCount(1, 'data');
        });

        it('requires authentication', function (): void {
            $response = $this->getJson('/api/ai/knowledge');
            $response->assertUnauthorized();
        });
    });

    describe('POST /api/ai/knowledge/upload', function (): void {
        it('uploads txt file successfully', function (): void {
            $file = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

            $response = $this->actingAs($this->user)
                ->postJson('/api/ai/knowledge/upload', [
                    'file' => $file,
                    'name' => 'My Document',
                ]);

            $response->assertStatus(202)
                ->assertJsonPath('data.name', 'My Document')
                ->assertJsonPath('data.embedding_status', 'pending');

            $this->assertDatabaseHas('ai_knowledge_documents', [
                'tenant_id' => $this->tenant->id,
                'name' => 'My Document',
            ]);
        });

        it('uploads csv file successfully', function (): void {
            $file = UploadedFile::fake()->create('data.csv', 100, 'text/csv');

            $response = $this->actingAs($this->user)
                ->postJson('/api/ai/knowledge/upload', [
                    'file' => $file,
                ]);

            $response->assertStatus(202)
                ->assertJsonPath('data.file_type', 'csv');
        });

        it('uploads markdown file successfully', function (): void {
            $file = UploadedFile::fake()->create('readme.md', 100, 'text/markdown');

            $response = $this->actingAs($this->user)
                ->postJson('/api/ai/knowledge/upload', [
                    'file' => $file,
                ]);

            $response->assertStatus(202)
                ->assertJsonPath('data.file_type', 'markdown');
        });

        it('uploads json file successfully', function (): void {
            $file = UploadedFile::fake()->create('config.json', 100, 'application/json');

            $response = $this->actingAs($this->user)
                ->postJson('/api/ai/knowledge/upload', [
                    'file' => $file,
                ]);

            $response->assertStatus(202)
                ->assertJsonPath('data.file_type', 'json');
        });

        it('uploads pdf file successfully', function (): void {
            $file = UploadedFile::fake()->create('manual.pdf', 300, 'application/pdf');

            $response = $this->actingAs($this->user)
                ->postJson('/api/ai/knowledge/upload', [
                    'file' => $file,
                ]);

            $response->assertStatus(202)
                ->assertJsonPath('data.file_type', 'pdf');
        });

        it('rejects unsupported file types', function (): void {
            $file = UploadedFile::fake()->create('document.exe', 100, 'application/octet-stream');

            $response = $this->actingAs($this->user)
                ->postJson('/api/ai/knowledge/upload', [
                    'file' => $file,
                ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['file']);
        });

        it('rejects files exceeding size limit', function (): void {
            $file = UploadedFile::fake()->create('large.txt', 11000, 'text/plain'); // > 10MB

            $response = $this->actingAs($this->user)
                ->postJson('/api/ai/knowledge/upload', [
                    'file' => $file,
                ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['file']);
        });

        it('requires file', function (): void {
            $response = $this->actingAs($this->user)
                ->postJson('/api/ai/knowledge/upload', []);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['file']);
        });

        it('creates new version when uploading with same name', function (): void {
            $existingDoc = AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->create(['name' => 'My Document', 'version' => 1]);

            $file = UploadedFile::fake()->create('document.txt', 100, 'text/plain');

            $response = $this->actingAs($this->user)
                ->postJson('/api/ai/knowledge/upload', [
                    'file' => $file,
                    'name' => 'My Document',
                ]);

            $response->assertStatus(202)
                ->assertJsonPath('data.version', 2);

            // Old document should be inactive
            $existingDoc->refresh();
            expect($existingDoc->is_active)->toBeFalse();
            expect($existingDoc->replaced_by)->not->toBeNull();
        });
    });

    describe('GET /api/ai/knowledge/{id}', function (): void {
        it('returns document details', function (): void {
            $document = AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->ready(15)
                ->create();

            $tenantId = $this->tenant->id;

            Cache::flush();

            $response = $this->actingAs($this->user)
                ->getJson("/api/ai/knowledge/{$document->id}");

            $response->assertOk()
                ->assertJsonPath('data.id', $document->id)
                ->assertJsonPath('data.chunk_count', 15)
                ->assertJsonPath('data.is_ready', true);

            expect(Cache::has("ai.knowledge.document.{$tenantId}.{$document->id}"))->toBeTrue();
        });

        it('returns 404 for non-existent document', function (): void {
            $nonExistentUuid = Str::uuid()->toString();
            $response = $this->actingAs($this->user)
                ->getJson("/api/ai/knowledge/{$nonExistentUuid}");

            $response->assertNotFound();
        });

        it('returns 404 for document from other tenant', function (): void {
            $otherTenant = PlatformTenant::factory()->create();
            $document = AiKnowledgeDocument::factory()
                ->forTenant($otherTenant)
                ->create();

            $response = $this->actingAs($this->user)
                ->getJson("/api/ai/knowledge/{$document->id}");

            $response->assertNotFound();
        });
    });

    describe('DELETE /api/ai/knowledge/{id}', function (): void {
        it('soft deletes document', function (): void {
            $document = AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->create();

            $response = $this->actingAs($this->user)
                ->deleteJson("/api/ai/knowledge/{$document->id}");

            $response->assertOk()
                ->assertJsonPath('message', 'Document deleted successfully.');

            $document->refresh();
            expect($document->is_active)->toBeFalse();
        });

        it('returns 404 for non-existent document', function (): void {
            $nonExistentUuid = Str::uuid()->toString();
            $response = $this->actingAs($this->user)
                ->deleteJson("/api/ai/knowledge/{$nonExistentUuid}");

            $response->assertNotFound();
        });

        it('cannot delete document from other tenant', function (): void {
            $otherTenant = PlatformTenant::factory()->create();
            $document = AiKnowledgeDocument::factory()
                ->forTenant($otherTenant)
                ->create();

            $response = $this->actingAs($this->user)
                ->deleteJson("/api/ai/knowledge/{$document->id}");

            $response->assertNotFound();

            $document->refresh();
            expect($document->is_active)->toBeTrue();
        });
    });

    describe('POST /api/ai/knowledge/{id}/reindex', function (): void {
        it('reindexes ready document', function (): void {
            $document = AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->ready()
                ->create();

            $response = $this->actingAs($this->user)
                ->postJson("/api/ai/knowledge/{$document->id}/reindex");

            $response->assertStatus(202)
                ->assertJsonPath('data.embedding_status', 'pending');

            $document->refresh();
            expect($document->embedding_status)->toBe(AiEmbeddingStatus::PENDING);
        });

        it('reindexes failed document', function (): void {
            $document = AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->failed()
                ->create();

            $response = $this->actingAs($this->user)
                ->postJson("/api/ai/knowledge/{$document->id}/reindex");

            $response->assertStatus(202);

            $document->refresh();
            expect($document->embedding_status)->toBe(AiEmbeddingStatus::PENDING);
        });

        it('rejects reindex for processing document', function (): void {
            $document = AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->processing()
                ->create();

            $response = $this->actingAs($this->user)
                ->postJson("/api/ai/knowledge/{$document->id}/reindex");

            $response->assertUnprocessable();
        });

        it('rejects reindex for pending document', function (): void {
            $document = AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->pending()
                ->create();

            $response = $this->actingAs($this->user)
                ->postJson("/api/ai/knowledge/{$document->id}/reindex");

            $response->assertUnprocessable();
        });
    });

    describe('GET /api/ai/knowledge/stats', function (): void {
        it('returns statistics', function (): void {
            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->ready()
                ->withFileSize(1000)
                ->count(2)
                ->create();

            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->processing()
                ->withFileSize(500)
                ->create();

            $response = $this->actingAs($this->user)
                ->getJson('/api/ai/knowledge/stats');

            $response->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.document_count', 3)
                ->assertJsonPath('data.documents_ready', 2)
                ->assertJsonPath('data.documents_processing', 1)
                ->assertJsonPath('data.total_storage_bytes', 2500);
        });

        it('returns zero stats for empty knowledge base', function (): void {
            $response = $this->actingAs($this->user)
                ->getJson('/api/ai/knowledge/stats');

            $response->assertOk()
                ->assertJsonPath('data.document_count', 0)
                ->assertJsonPath('data.total_chunks', 0);
        });
    });

    describe('GET /api/ai/knowledge/{id}/chunks', function (): void {
        it('returns paginated chunks for the document', function (): void {
            $document = AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->ready()
                ->create();

            foreach (range(0, 2) as $index) {
                \Domain\Ai\Models\AiKnowledgeChunk::factory()->create([
                    'document_id' => $document->id,
                    'tenant_id' => $this->tenant->id,
                    'chunk_index' => $index,
                    'content' => "Chunk {$index}",
                    'token_count' => 20,
                ]);
            }

            $response = $this->actingAs($this->user)
                ->getJson("/api/ai/knowledge/{$document->id}/chunks?per_page=2");

            $response->assertOk()
                ->assertJsonCount(2, 'data')
                ->assertJsonPath('meta.per_page', 2)
                ->assertJsonPath('meta.total', 3);
        });

        it('returns 404 when document does not exist', function (): void {
            $response = $this->actingAs($this->user)
                ->getJson('/api/ai/knowledge/'.Str::orderedUuid().'/chunks');

            $response->assertNotFound();
        });
    });

    describe('POST /api/ai/knowledge/search', function (): void {
        it('validates query is required', function (): void {
            $response = $this->actingAs($this->user)
                ->postJson('/api/ai/knowledge/search', []);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['query']);
        });

        it('validates query minimum length', function (): void {
            $response = $this->actingAs($this->user)
                ->postJson('/api/ai/knowledge/search', [
                    'query' => 'ab',
                ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['query']);
        });

        it('validates limit range', function (): void {
            $response = $this->actingAs($this->user)
                ->postJson('/api/ai/knowledge/search', [
                    'query' => 'test query',
                    'limit' => 50,
                ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['limit']);
        });

        it('validates min_score range', function (): void {
            $response = $this->actingAs($this->user)
                ->postJson('/api/ai/knowledge/search', [
                    'query' => 'test query',
                    'min_score' => 1.5,
                ]);

            $response->assertUnprocessable()
                ->assertJsonValidationErrors(['min_score']);
        });
    });
});
