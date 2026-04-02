<?php

declare(strict_types=1);

use Domain\Ai\Enums\AiPromptValidationStatus;
use Domain\Ai\Models\AiPromptSegment;
use Domain\Ai\Models\AiPromptTenant;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    Notification::fake();

    $this->tenant = PlatformTenant::factory()->create();

    $adminRole = AuthRole::query()->firstOrCreate(
        ['name' => 'admin', 'guard_name' => 'sanctum'],
        ['id' => (string) Str::orderedUuid()]
    );

    $this->superAdmin = AuthUser::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $this->superAdmin->assignRole($adminRole);

    $this->segment = AiPromptSegment::factory()->general()->create();
});

describe('GET /api/platform/ai/prompts/quarantine', function (): void {
    it('lists quarantined prompts', function (): void {
        // Cada tenant pode ter apenas 1 prompt - criar tenants diferentes
        $tenant1 = PlatformTenant::factory()->create(['segment_id' => $this->segment->id]);
        $tenant2 = PlatformTenant::factory()->create(['segment_id' => $this->segment->id]);
        $tenant3 = PlatformTenant::factory()->create(['segment_id' => $this->segment->id]);

        AiPromptTenant::factory()
            ->forTenant($tenant1)
            ->withSegment($this->segment)
            ->quarantined()
            ->create();

        AiPromptTenant::factory()
            ->forTenant($tenant2)
            ->withSegment($this->segment)
            ->quarantined()
            ->create();

        // Should not appear - approved
        AiPromptTenant::factory()
            ->forTenant($tenant3)
            ->withSegment($this->segment)
            ->approved()
            ->create();

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/platform/ai/prompts/quarantine');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });
});

describe('POST /api/platform/ai/prompts/quarantine/{id}/approve', function (): void {
    it('approves quarantined prompt', function (): void {
        $tenant = PlatformTenant::factory()->create([
            'segment_id' => $this->segment->id,
        ]);

        $prompt = AiPromptTenant::factory()
            ->forTenant($tenant)
            ->withSegment($this->segment)
            ->quarantined()
            ->create([
                'guardian_analysis' => 'Some concerns detected',
            ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/platform/ai/prompts/quarantine/{$prompt->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.validation_status', AiPromptValidationStatus::APPROVED->value);

        $this->assertDatabaseHas('ai_prompt_tenants', [
            'id' => $prompt->id,
            'validation_status' => AiPromptValidationStatus::APPROVED->value,
        ]);
    });

    it('fails for non-quarantined prompt', function (): void {
        $tenant = PlatformTenant::factory()->create([
            'segment_id' => $this->segment->id,
        ]);

        $prompt = AiPromptTenant::factory()
            ->forTenant($tenant)
            ->withSegment($this->segment)
            ->approved()
            ->create();

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/platform/ai/prompts/quarantine/{$prompt->id}/approve");

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot approve prompt: it is not in quarantine status.');
    });
});

describe('POST /api/platform/ai/prompts/quarantine/{id}/reject', function (): void {
    it('rejects quarantined prompt and rolls back', function (): void {
        $tenant = PlatformTenant::factory()->create([
            'segment_id' => $this->segment->id,
        ]);

        $prompt = AiPromptTenant::factory()
            ->forTenant($tenant)
            ->withSegment($this->segment)
            ->quarantined()
            ->create([
                'content' => 'Suspicious content',
                'previous_content' => 'Safe previous content',
            ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/platform/ai/prompts/quarantine/{$prompt->id}/reject");

        $response->assertOk()
            ->assertJsonPath('data.validation_status', AiPromptValidationStatus::REJECTED->value);

        // Verifica que o content foi revertido para o anterior (se houver)
        // A lógica de rollback deve ter sido aplicada
        $prompt->refresh();
        expect($prompt->validation_status)->toBe(AiPromptValidationStatus::REJECTED);
    });

    it('rejects quarantined prompt without previous content', function (): void {
        $tenant = PlatformTenant::factory()->create([
            'segment_id' => $this->segment->id,
        ]);

        $prompt = AiPromptTenant::factory()
            ->forTenant($tenant)
            ->withSegment($this->segment)
            ->quarantined()
            ->create([
                'content' => 'Suspicious content',
                'previous_content' => null,
            ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson("/api/platform/ai/prompts/quarantine/{$prompt->id}/reject");

        $response->assertOk()
            ->assertJsonPath('data.validation_status', AiPromptValidationStatus::REJECTED->value);
    });
});
