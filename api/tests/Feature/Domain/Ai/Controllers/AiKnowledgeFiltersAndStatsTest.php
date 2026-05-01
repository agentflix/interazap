<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Ai\Controllers;

use Domain\Ai\Contracts\AiRagServiceInterface;
use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Ai\Models\AiRagQueryLog;
use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

uses(LazilyRefreshDatabase::class);

describe('AiKnowledgeController search filters', function (): void {
    beforeEach(function (): void {
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

        // Mock RAG service to avoid real HTTP calls
        $ragMock = \Mockery::mock(AiRagServiceInterface::class);
        $ragMock->shouldReceive('search')->andReturn([]);
        app()->instance(AiRagServiceInterface::class, $ragMock);
    });

    it('validates document_ids must be uuids', function (): void {
        $response = $this->actingAs($this->user)
            ->postJson('/api/ai/knowledge/search', [
                'query' => 'test query',
                'document_ids' => ['not-a-uuid'],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_ids.0']);
    });

    it('validates document_ids must exist in tenant', function (): void {
        $otherTenant = PlatformTenant::factory()->create();
        $otherDoc = AiKnowledgeDocument::factory()->forTenant($otherTenant)->create();

        $response = $this->actingAs($this->user)
            ->postJson('/api/ai/knowledge/search', [
                'query' => 'test query',
                'document_ids' => [$otherDoc->id],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_ids.0']);
    });

    it('validates file_types must be valid enum values', function (): void {
        $response = $this->actingAs($this->user)
            ->postJson('/api/ai/knowledge/search', [
                'query' => 'test query',
                'file_types' => ['invalid'],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['file_types.0']);
    });

    it('accepts valid file_types', function (): void {
        $response = $this->actingAs($this->user)
            ->postJson('/api/ai/knowledge/search', [
                'query' => 'test query',
                'file_types' => ['txt', 'markdown'],
            ]);

        $response->assertOk();
    });

    it('validates created_after date format', function (): void {
        $response = $this->actingAs($this->user)
            ->postJson('/api/ai/knowledge/search', [
                'query' => 'test query',
                'created_after' => 'invalid-date',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['created_after']);
    });

    it('validates created_before date format', function (): void {
        $response = $this->actingAs($this->user)
            ->postJson('/api/ai/knowledge/search', [
                'query' => 'test query',
                'created_before' => '2023-01-01',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['created_before']);
    });

    it('accepts valid iso8601 dates', function (): void {
        $response = $this->actingAs($this->user)
            ->postJson('/api/ai/knowledge/search', [
                'query' => 'test query',
                'created_after' => '2023-01-01T00:00:00+00:00',
                'created_before' => '2023-12-31T23:59:59+00:00',
            ]);

        $response->assertOk();
    });
});

describe('AiKnowledgeController rag-stats', function (): void {
    beforeEach(function (): void {
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

    it('returns rag statistics', function (): void {
        AiRagQueryLog::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now()->subDay(),
            'has_results' => true,
            'mode' => 'vector',
            'latency_ms' => 100,
            'top_score' => 0.85,
            'results_count' => 5,
        ]);

        AiRagQueryLog::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now()->subDay(),
            'has_results' => false,
            'mode' => 'hybrid',
            'latency_ms' => 200,
            'top_score' => null,
            'results_count' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/ai/knowledge/rag-stats?days=7');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_queries', 7)
            ->assertJsonPath('data.mode_distribution.vector', 5)
            ->assertJsonPath('data.mode_distribution.hybrid', 2);
    });

    it('returns zero stats when no queries', function (): void {
        $response = $this->actingAs($this->user)
            ->getJson('/api/ai/knowledge/rag-stats');

        $response->assertOk()
            ->assertJsonPath('data.total_queries', 0)
            ->assertJsonPath('data.zero_results_rate', 0);
    });

    it('respects days parameter', function (): void {
        AiRagQueryLog::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now()->subDays(10),
        ]);

        AiRagQueryLog::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/ai/knowledge/rag-stats?days=5');

        $response->assertOk()
            ->assertJsonPath('data.total_queries', 2);
    });

    it('requires authentication', function (): void {
        $response = $this->getJson('/api/ai/knowledge/rag-stats');
        $response->assertUnauthorized();
    });
});
