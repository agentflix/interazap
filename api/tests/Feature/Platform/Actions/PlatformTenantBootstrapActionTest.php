<?php

declare(strict_types=1);

use Database\Seeders\AiPromptMasterSeeder;
use Database\Seeders\AiPromptSegmentSeeder;
use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAgentChannel;
use Domain\Ai\Models\AiAgentSkill;
use Domain\Ai\Models\AiPromptSegment;
use Domain\Platform\Actions\PlatformTenantBootstrapAction;
use Domain\Platform\Models\PlatformTenant;

uses()->group('platform', 'bootstrap', 'ai');

beforeEach(function (): void {
    $this->seed(AiPromptMasterSeeder::class);
    $this->seed(AiPromptSegmentSeeder::class);
});

test('bootstraps default tenant with a general agent', function (): void {
    $segment = AiPromptSegment::query()->where('code', 'GENERAL')->firstOrFail();

    $tenant = PlatformTenant::factory()->create([
        'segment_id' => $segment->id,
    ]);

    /** @var PlatformTenantBootstrapAction $action */
    $action = app(PlatformTenantBootstrapAction::class);
    $report = $action->execute($tenant);

    expect($report['segment_code'])->toBe('GENERAL')
        ->and($report['agents'])->toBeGreaterThanOrEqual(2);

    $generalAgent = AiAgent::query()
        ->where('tenant_id', $tenant->id)
        ->where('type', 'general')
        ->where('is_active', true)
        ->first();

    expect($generalAgent)->not()->toBeNull()
        ->and($generalAgent?->type)->toBe('general');
});

test('bootstraps saas tenant with active agents and essential tools', function (): void {
    $segment = AiPromptSegment::query()->where('code', 'SAAS')->firstOrFail();

    $tenant = PlatformTenant::factory()->create([
        'segment_id' => $segment->id,
    ]);

    /** @var PlatformTenantBootstrapAction $action */
    $action = app(PlatformTenantBootstrapAction::class);
    $report = $action->execute($tenant);

    expect($report['segment_code'])->toBe('SAAS')
        ->and($report['agents'])->toBeGreaterThanOrEqual(1)
        ->and($report['agent_skills'])->toBeGreaterThanOrEqual(1)
        ->and($report['agent_channels'])->toBeGreaterThanOrEqual(1);

    $activeAgents = AiAgent::query()
        ->where('tenant_id', $tenant->id)
        ->where('is_active', true)
        ->get();

    expect($activeAgents->count())->toBeGreaterThanOrEqual(1);

    $superAdmin = AiAgent::query()
        ->where('tenant_id', $tenant->id)
        ->where('name', 'Super Admin - SAAS')
        ->first();

    expect($superAdmin)->not()->toBeNull();

    $metadata = is_array($superAdmin?->metadata) ? $superAdmin->metadata : [];
    $toolNames = data_get($metadata, 'tool_names');

    expect($toolNames)->toBeArray()
        ->and($toolNames)->toContain(\Domain\Ai\Enums\AiToolEnum::SEARCH_KNOWLEDGE)
        ->and($toolNames)->toContain(\Domain\Ai\Enums\AiToolEnum::SEND_MESSAGE)
        ->and($toolNames)->toContain(\Domain\Ai\Enums\AiToolEnum::TRANSFER_TO_HUMAN);

    expect(AiAgentSkill::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
        ->and(AiAgentChannel::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1)
        ->and(\Domain\Ai\Models\AiAgentFile::query()->where('tenant_id', $tenant->id)->count())->toBeGreaterThanOrEqual(1);

    $identityFile = \Domain\Ai\Models\AiAgentFile::query()
        ->where('tenant_id', $tenant->id)
        ->where('slug', 'IDENTITY.md')
        ->first();

    expect($identityFile)->not()->toBeNull()
        ->and($identityFile?->content)->toContain('Quem sou eu');

    $skill = AiAgentSkill::query()
        ->where('tenant_id', $tenant->id)
        ->where('name', 'Autopilot Orchestration')
        ->first();

    expect($skill)->not()->toBeNull();
    $skillMetadata = is_array($skill?->metadata) ? $skill->metadata : [];
    expect($skillMetadata['prompt_append'] ?? '')->toContain('ORQUESTRAÇÃO');
});

test('bootstrap is idempotent for agent sync', function (): void {
    $segment = AiPromptSegment::query()->where('code', 'SAAS')->firstOrFail();

    $tenant = PlatformTenant::factory()->create([
        'segment_id' => $segment->id,
    ]);

    /** @var PlatformTenantBootstrapAction $action */
    $action = app(PlatformTenantBootstrapAction::class);

    $action->execute($tenant);

    $countsBefore = [
        'agents' => AiAgent::query()->where('tenant_id', $tenant->id)->count(),
        'skills' => AiAgentSkill::query()->where('tenant_id', $tenant->id)->count(),
        'channels' => AiAgentChannel::query()->where('tenant_id', $tenant->id)->count(),
        'files' => \Domain\Ai\Models\AiAgentFile::query()->where('tenant_id', $tenant->id)->count(),
    ];

    $action->execute($tenant);

    $countsAfter = [
        'agents' => AiAgent::query()->where('tenant_id', $tenant->id)->count(),
        'skills' => AiAgentSkill::query()->where('tenant_id', $tenant->id)->count(),
        'channels' => AiAgentChannel::query()->where('tenant_id', $tenant->id)->count(),
        'files' => \Domain\Ai\Models\AiAgentFile::query()->where('tenant_id', $tenant->id)->count(),
    ];

    expect($countsAfter)->toBe($countsBefore);
});
