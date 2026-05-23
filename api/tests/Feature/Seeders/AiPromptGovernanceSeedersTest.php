<?php

declare(strict_types=1);

use Database\Seeders\AiPromptMasterSeeder;
use Database\Seeders\AiPromptPlanSeeder;
use Database\Seeders\AiPromptSegmentSeeder;
use Database\Seeders\PlatformPlanSeeder;
use Domain\Ai\Models\AiPromptMaster;
use Domain\Ai\Models\AiPromptPlan;
use Domain\Ai\Models\AiPromptSegment;
use Domain\Platform\Models\PlatformPlan;

uses()->group('seeders', 'ai');

beforeEach(function (): void {
    $this->seed(PlatformPlanSeeder::class);
    $this->seed(AiPromptMasterSeeder::class);
    $this->seed(AiPromptSegmentSeeder::class);
    $this->seed(AiPromptPlanSeeder::class);
});

test('creates expected platform plans for prompt governance', function (): void {
    expect(PlatformPlan::query()->where('slug', 'starter')->exists())->toBeTrue()
        ->and(PlatformPlan::query()->where('slug', 'professional')->exists())->toBeTrue()
        ->and(PlatformPlan::query()->where('slug', 'business')->exists())->toBeTrue();
});

test('creates production prompt masters and keeps expected keywords', function (): void {
    $securityMaster = AiPromptMaster::query()->where('name', 'Segurança e Injeção de Prompt')->first();
    $lgpdMaster = AiPromptMaster::query()->where('name', 'Conformidade LGPD')->first();
    $toneMaster = AiPromptMaster::query()->where('name', 'Tom e Linguagem')->first();

    expect($securityMaster)->not()->toBeNull()
        ->and($lgpdMaster)->not()->toBeNull()
        ->and($toneMaster)->not()->toBeNull();

    expect($securityMaster?->content)->toContain('PROMPT INJECTION')
        ->and($securityMaster?->content)->toContain('LGPD')
        ->and($securityMaster?->content)->toContain('SEGURANÇA GERAL');

    expect(str_word_count($securityMaster?->content ?? ''))->toBeGreaterThanOrEqual(300);
});

test('creates expected segments including new business verticals', function (): void {
    $expectedCodes = [
        'GENERAL',
        'ECOMMERCE',
        'SAAS',
        'HEALTHCARE',
        'REAL_ESTATE',
        'FOOD_SERVICE',
        'TELEMEDICINE',
        'COMMERCE',
        'RETAIL',
        'TECH_SUPPORT',
        'ASAAS_BILLING',
    ];

    foreach ($expectedCodes as $code) {
        expect(AiPromptSegment::query()->where('code', $code)->exists())->toBeTrue();
    }

    $newSegments = AiPromptSegment::query()
        ->whereIn('code', ['FOOD_SERVICE', 'TELEMEDICINE', 'COMMERCE', 'RETAIL', 'TECH_SUPPORT'])
        ->get();

    foreach ($newSegments as $segment) {
        expect(str_word_count((string) $segment->content))->toBeGreaterThanOrEqual(100);
    }
});

test('creates differentiated plan prompts for professional and business plans', function (): void {
    $professionalPlan = PlatformPlan::query()->where('slug', 'professional')->firstOrFail();
    $businessPlan = PlatformPlan::query()->where('slug', 'business')->firstOrFail();

    $professionalPrompt = AiPromptPlan::query()->where('plan_id', $professionalPlan->id)->first();
    $businessPrompt = AiPromptPlan::query()->where('plan_id', $businessPlan->id)->first();

    expect($professionalPrompt)->not()->toBeNull()
        ->and($businessPrompt)->not()->toBeNull();

    expect($professionalPrompt?->content)->toContain('300 palavras')
        ->and($businessPrompt?->content)->toContain('sem limite rígido');
});

test('seeders are idempotent for prompt governance records', function (): void {
    $countsBefore = [
        'plans' => PlatformPlan::query()->count(),
        'masters' => AiPromptMaster::query()->count(),
        'segments' => AiPromptSegment::query()->count(),
        'plan_prompts' => AiPromptPlan::query()->count(),
    ];

    $this->seed(PlatformPlanSeeder::class);
    $this->seed(AiPromptMasterSeeder::class);
    $this->seed(AiPromptSegmentSeeder::class);
    $this->seed(AiPromptPlanSeeder::class);

    $countsAfter = [
        'plans' => PlatformPlan::query()->count(),
        'masters' => AiPromptMaster::query()->count(),
        'segments' => AiPromptSegment::query()->count(),
        'plan_prompts' => AiPromptPlan::query()->count(),
    ];

    expect($countsAfter)->toBe($countsBefore)
        ->and($countsAfter['plan_prompts'])->toBeGreaterThanOrEqual(3);
});
