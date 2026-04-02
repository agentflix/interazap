<?php

declare(strict_types=1);

use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAgentSkill;
use Domain\Ai\Services\AiSkillExecutorService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

it('applies pre-run skills in priority order', function (): void {
    $tenant = PlatformTenant::factory()->create();
    $tenantId = (string) $tenant->id;

    $agent = AiAgent::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'Agent Skills',
        'role' => 'general',
        'type' => 'general',
        'model_id' => 'gpt-4o-mini',
    ]);

    AiAgentSkill::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'agent_id' => (string) $agent->id,
        'name' => 'routing-a',
        'is_active' => true,
        'metadata' => [
            'type' => 'routing',
            'priority' => 1,
            'route_to_agent_id' => 'agent-x',
        ],
    ]);

    AiAgentSkill::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'agent_id' => (string) $agent->id,
        'name' => 'custom-b',
        'is_active' => true,
        'metadata' => [
            'type' => 'custom',
            'priority' => 2,
            'prompt_append' => 'Use concise tone',
        ],
    ]);

    $result = app(AiSkillExecutorService::class)->executePreRun($tenantId, (string) $agent->id, [
        'current_input' => 'hello',
    ]);

    expect(data_get($result, 'context.routed_agent_id'))->toBe('agent-x')
        ->and(data_get($result, 'context.skill_prompt_append.0'))->toBe('Use concise tone')
        ->and(count((array) data_get($result, 'trace', [])))->toBe(2);
});

it('applies validation and summarization in post-run phase', function (): void {
    $tenant = PlatformTenant::factory()->create();
    $tenantId = (string) $tenant->id;

    $agent = AiAgent::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'Agent Skills',
        'role' => 'general',
        'type' => 'general',
        'model_id' => 'gpt-4o-mini',
    ]);

    AiAgentSkill::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'agent_id' => (string) $agent->id,
        'name' => 'validator',
        'is_active' => true,
        'metadata' => [
            'type' => 'validation',
            'required_keywords' => ['budget', 'timeline'],
        ],
    ]);

    AiAgentSkill::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'agent_id' => (string) $agent->id,
        'name' => 'summary',
        'is_active' => true,
        'metadata' => [
            'type' => 'summarization',
        ],
    ]);

    $result = app(AiSkillExecutorService::class)->executePostRun($tenantId, (string) $agent->id, [
        'response' => 'We discussed budget and next steps for implementation timeline.',
    ]);

    expect(data_get($result, 'output.skill_validation.valid', true))->toBeTrue()
        ->and((string) data_get($result, 'output.skill_summary'))->not->toBe('')
        ->and(count((array) data_get($result, 'trace', [])))->toBe(2);
});

it('applies extraction skill in post-run phase', function (): void {
    $tenant = PlatformTenant::factory()->create();
    $tenantId = (string) $tenant->id;

    $agent = AiAgent::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'Agent Extraction',
        'role' => 'general',
        'type' => 'general',
        'model_id' => 'gpt-4o-mini',
    ]);

    AiAgentSkill::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'agent_id' => (string) $agent->id,
        'name' => 'extractor',
        'is_active' => true,
        'metadata' => [
            'type' => 'extraction',
            'extract_fields' => ['company_name', 'budget'],
        ],
    ]);

    $result = app(AiSkillExecutorService::class)->executePostRun($tenantId, (string) $agent->id, [
        'response' => 'The company is ACME and the budget is 20k.',
    ]);

    expect(data_get($result, 'output.skill_extraction.fields'))->toBe(['company_name', 'budget'])
        ->and((string) data_get($result, 'trace.0.type'))->toBe('extraction');
});

it('applies classification skill and defaults unknown type to custom', function (): void {
    $tenant = PlatformTenant::factory()->create();
    $tenantId = (string) $tenant->id;

    $agent = AiAgent::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'name' => 'Agent Classification',
        'role' => 'general',
        'type' => 'general',
        'model_id' => 'gpt-4o-mini',
    ]);

    AiAgentSkill::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'agent_id' => (string) $agent->id,
        'name' => 'classifier',
        'is_active' => true,
        'metadata' => [
            'type' => 'classification',
            'hint' => 'intent:billing',
            'priority' => 1,
        ],
    ]);

    AiAgentSkill::query()->create([
        'id' => (string) Str::orderedUuid(),
        'tenant_id' => $tenantId,
        'agent_id' => (string) $agent->id,
        'name' => 'unknown-skill',
        'is_active' => true,
        'metadata' => [
            'type' => 'something_new',
            'prompt_append' => 'Fallback custom behavior',
            'priority' => 2,
        ],
    ]);

    $result = app(AiSkillExecutorService::class)->executePreRun($tenantId, (string) $agent->id, [
        'current_input' => 'Need invoice',
    ]);

    expect(data_get($result, 'context.skill_classification.classifier.hint'))->toBe('intent:billing')
        ->and(data_get($result, 'context.skill_prompt_append.0'))->toBe('Fallback custom behavior')
        ->and((string) data_get($result, 'trace.1.type'))->toBe('custom');
});
