<?php

declare(strict_types=1);

use Domain\Ai\Models\AiPromptMaster;
use Domain\Ai\Models\AiPromptSegment;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();

    $adminRole = \Domain\Auth\Models\AuthRole::query()->firstOrCreate(['id' => AuthRole::INQUILINO_ID], ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']);

    $this->superAdmin = AuthUser::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $this->superAdmin->assignRole(AuthRole::INQUILINO_ID);

    $this->master = AiPromptMaster::factory()->create();
});

describe('GET /api/platform/ai/prompts/segments', function (): void {
    it('lists all segment prompts', function (): void {
        AiPromptSegment::factory()->count(3)->create([
            'master_id' => $this->master->id,
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/platform/ai/prompts/segments');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });
});

describe('POST /api/platform/ai/prompts/segments', function (): void {
    it('creates segment prompt', function (): void {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/platform/ai/prompts/segments', [
                'name' => 'Healthcare Segment',
                'code' => 'HEALTHCARE',
                'master_id' => $this->master->id,
                'content' => 'Healthcare specific rules',
                'is_active' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Healthcare Segment')
            ->assertJsonPath('data.code', 'HEALTHCARE');
    });

    it('validates code uniqueness', function (): void {
        AiPromptSegment::factory()->create([
            'master_id' => $this->master->id,
            'code' => 'EXISTING_CODE',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/platform/ai/prompts/segments', [
                'name' => 'New Segment',
                'code' => 'EXISTING_CODE',
                'master_id' => $this->master->id,
                'content' => 'Content',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });
});

describe('PUT /api/platform/ai/prompts/segments/{id}', function (): void {
    it('updates segment prompt', function (): void {
        $segment = AiPromptSegment::factory()->create([
            'master_id' => $this->master->id,
            'code' => 'TEST_SEGMENT',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/platform/ai/prompts/segments/{$segment->id}", [
                'name' => 'Updated Segment',
                'code' => 'TEST_SEGMENT', // Mantém o mesmo code
                'content' => 'Updated content',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Segment');
    });
});

describe('DELETE /api/platform/ai/prompts/segments/{id}', function (): void {
    it('deletes segment prompt', function (): void {
        $segment = AiPromptSegment::factory()->create([
            'master_id' => $this->master->id,
            'is_active' => true,
        ]);

        $segmentId = $segment->id;

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/platform/ai/prompts/segments/{$segmentId}");

        $response->assertOk()
            ->assertJsonPath('message', 'Segment prompt deleted successfully.');

        $this->assertSoftDeleted('ai_prompt_segments', [
            'id' => $segmentId,
        ]);
    });

    it('prevents deletion of GENERAL segment', function (): void {
        $generalSegment = AiPromptSegment::factory()->general()->create();

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/platform/ai/prompts/segments/{$generalSegment->id}");

        $response->assertForbidden()
            ->assertJsonPath('message', 'The GENERAL segment cannot be deleted. It is a mandatory fallback segment.');
    });
});
