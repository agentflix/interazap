<?php

declare(strict_types=1);

use Domain\Ai\Models\AiPromptSegment;
use Domain\Ai\Models\AiPromptTenant;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMTag;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();

    $adminRole = AuthRole::firstOrCreate(
        ['id' => AuthRole::INQUILINO_ID],
        ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
    );

    $this->superAdmin = AuthUser::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $this->superAdmin->assignRole(AuthRole::INQUILINO_ID);
});

describe('PUT /api/platform/tenants/{tenant}/segment', function (): void {
    it('assigns segment to tenant', function (): void {
        $segment = AiPromptSegment::factory()->create([
            'name' => 'Healthcare',
        ]);

        $targetTenant = PlatformTenant::factory()->create();

        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/platform/tenants/{$targetTenant->id}/segment", [
                'segment_id' => $segment->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.segment_id', $segment->id);

        $this->assertDatabaseHas('platform_tenants', [
            'id' => $targetTenant->id,
            'segment_id' => $segment->id,
        ]);
    });

    it('validates segment exists', function (): void {
        $targetTenant = PlatformTenant::factory()->create();

        $response = $this->actingAs($this->superAdmin)
            ->putJson("/api/platform/tenants/{$targetTenant->id}/segment", [
                'segment_id' => 99999,
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['segment_id']);
    });

    it('does not auto-bootstrap defaults when segment changes', function (): void {
        $segment = AiPromptSegment::factory()->create([
            'name' => 'SaaS B2B',
            'code' => 'SAAS_'.Str::upper(Str::random(6)),
        ]);

        $targetTenant = PlatformTenant::factory()->create();

        $this->assertSame(0, AiPromptTenant::query()->where('tenant_id', $targetTenant->id)->count());
        $this->assertSame(0, CRMTag::query()->where('tenant_id', $targetTenant->id)->count());

        $this->actingAs($this->superAdmin)
            ->putJson("/api/platform/tenants/{$targetTenant->id}/segment", [
                'segment_id' => $segment->id,
            ])
            ->assertOk();

        $this->assertSame(0, AiPromptTenant::query()->where('tenant_id', $targetTenant->id)->count());
        $this->assertSame(0, CRMTag::query()->where('tenant_id', $targetTenant->id)->count());
    });
});
