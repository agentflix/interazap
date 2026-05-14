<?php

declare(strict_types=1);

use Domain\Ai\Models\AiPromptMaster;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    // Criar roles necessárias
    $adminRole = \Domain\Auth\Models\AuthRole::query()->firstOrCreate(['id' => AuthRole::INQUILINO_ID], ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']);
    $userRole = \Domain\Auth\Models\AuthRole::query()->firstOrCreate(['id' => AuthRole::ATENDENTE_ID], ['name' => AuthRole::ATENDENTE_NAME, 'guard_name' => 'sanctum']);

    $this->tenant = PlatformTenant::factory()->create();

    $this->superAdmin = AuthUser::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $this->superAdmin->assignRole(AuthRole::INQUILINO_ID);

    $this->regularUser = AuthUser::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $this->regularUser->assignRole(AuthRole::ATENDENTE_ID);
});

describe('GET /api/platform/ai/prompts/masters', function (): void {
    it('lists all master prompts for super admin', function (): void {
        AiPromptMaster::factory()->count(3)->create();

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/platform/ai/prompts/masters');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('denies access to non-admin', function (): void {
        $this->actingAs($this->regularUser)
            ->getJson('/api/platform/ai/prompts/masters')
            ->assertForbidden();
    });
});

describe('POST /api/platform/ai/prompts/masters', function (): void {
    it('creates master prompt', function (): void {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/platform/ai/prompts/masters', [
                'name' => 'Compliance Master',
                'content' => 'You must follow all compliance rules',
                'is_active' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Compliance Master')
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('ai_prompt_masters', [
            'name' => 'Compliance Master',
        ]);
    });

    it('validates required fields', function (): void {
        $response = $this->actingAs($this->superAdmin)
            ->postJson('/api/platform/ai/prompts/masters', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'content']);
    });
});

describe('GET /api/platform/ai/prompts/masters/{id}', function (): void {
    it('shows master prompt details', function (): void {
        $master = AiPromptMaster::factory()->create([
            'name' => 'Test Master',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson("/api/platform/ai/prompts/masters/{$master->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Test Master');
    });
});

describe('PUT /api/platform/ai/prompts/masters/{id}', function (): void {
    it('updates master prompt', function (): void {
        $master = AiPromptMaster::factory()->create([
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/platform/ai/prompts/masters/{$master->id}", [
                'name' => 'New Name',
                'content' => 'Updated content',
                'is_active' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('ai_prompt_masters', [
            'id' => $master->id,
            'name' => 'New Name',
        ]);
    });
});

describe('DELETE /api/platform/ai/prompts/masters/{id}', function (): void {
    it('deactivates master prompt', function (): void {
        $master = AiPromptMaster::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson("/api/platform/ai/prompts/masters/{$master->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Master prompt deactivated successfully.');

        $this->assertDatabaseHas('ai_prompt_masters', [
            'id' => $master->id,
            'is_active' => false,
        ]);
    });
});
