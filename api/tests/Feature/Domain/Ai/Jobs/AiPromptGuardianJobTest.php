<?php

declare(strict_types=1);

use Domain\Ai\Contracts\AiGuardianServiceInterface;
use Domain\Ai\Contracts\AiPromptHashServiceInterface;
use Domain\Ai\Enums\AiPromptValidationStatus;
use Domain\Ai\Jobs\AiPromptGuardianJob;
use Domain\Ai\Models\AiPromptSegment;
use Domain\Ai\Models\AiPromptTenant;
use Domain\Ai\Notifications\PromptQuarantinedNotification;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Services\MetricsService;
use Illuminate\Support\Facades\Notification;

describe('AiPromptGuardianJob', function (): void {
    beforeEach(function (): void {
        Notification::fake();

        $this->segment = AiPromptSegment::factory()->general()->create();
        $this->tenant = PlatformTenant::factory()->create([
            'segment_id' => $this->segment->id,
        ]);

        $adminRole = \Domain\Auth\Models\AuthRole::query()->firstOrCreate(['id' => AuthRole::INQUILINO_ID], ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']);

        // Create admin with tenant (será notificado por ter role Inquilino)
        $this->superAdmin = AuthUser::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->superAdmin->assignRole(AuthRole::INQUILINO_ID);
    });

    it('approves prompt when guardian passes', function (): void {
        $prompt = AiPromptTenant::factory()
            ->forTenant($this->tenant)
            ->withSegment($this->segment)
            ->pending()
            ->create([
                'content' => 'A completely safe and helpful prompt',
            ]);

        $mockGuardian = Mockery::mock(AiGuardianServiceInterface::class);
        $mockGuardian->shouldReceive('validate')
            ->once()
            ->andReturn(\Domain\Ai\DTOs\GuardianValidationResult::safe());

        $mockHashService = Mockery::mock(AiPromptHashServiceInterface::class);
        $mockHashService->shouldReceive('hash')
            ->once()
            ->andReturn('test-hash-123');

        $mockMetricsService = Mockery::mock(MetricsService::class);
        $mockMetricsService->shouldNotReceive('recordAutopilotGuardrailBlock');

        $job = new AiPromptGuardianJob($prompt->id);
        $job->handle($mockGuardian, $mockHashService, $mockMetricsService);

        $prompt->refresh();
        expect($prompt->validation_status)->toBe(AiPromptValidationStatus::APPROVED);
    });

    it('quarantines prompt when guardian fails and notifies superadmins', function (): void {
        $prompt = AiPromptTenant::factory()
            ->forTenant($this->tenant)
            ->withSegment($this->segment)
            ->pending()
            ->create([
                'content' => 'A suspicious prompt',
                'guardian_analysis' => null,
            ]);

        $mockGuardian = Mockery::mock(AiGuardianServiceInterface::class);
        $mockGuardian->shouldReceive('validate')
            ->once()
            ->andReturn(\Domain\Ai\DTOs\GuardianValidationResult::unsafe(
                reason: 'Potential prompt injection detected',
                category: 'injection',
            ));

        $mockHashService = Mockery::mock(AiPromptHashServiceInterface::class);
        // Não deve ser chamado em caso de quarentena

        $mockMetricsService = Mockery::mock(MetricsService::class);
        $mockMetricsService->shouldReceive('recordAutopilotGuardrailBlock')
            ->once()
            ->with(['stage' => 'post_response']);

        $job = new AiPromptGuardianJob($prompt->id);
        $job->handle($mockGuardian, $mockHashService, $mockMetricsService);

        $prompt->refresh();
        expect($prompt->validation_status)->toBe(AiPromptValidationStatus::QUARANTINE);

        Notification::assertSentTo(
            [$this->superAdmin],
            PromptQuarantinedNotification::class
        );
    });

    it('skips already processed prompts', function (): void {
        $prompt = AiPromptTenant::factory()
            ->forTenant($this->tenant)
            ->withSegment($this->segment)
            ->approved()
            ->create();

        $mockGuardian = Mockery::mock(AiGuardianServiceInterface::class);
        $mockGuardian->shouldNotReceive('validate');

        $mockHashService = Mockery::mock(AiPromptHashServiceInterface::class);
        $mockMetricsService = Mockery::mock(MetricsService::class);
        $mockMetricsService->shouldNotReceive('recordAutopilotGuardrailBlock');

        $job = new AiPromptGuardianJob($prompt->id);
        $job->handle($mockGuardian, $mockHashService, $mockMetricsService);

        $prompt->refresh();
        expect($prompt->validation_status)->toBe(AiPromptValidationStatus::APPROVED);
    });
});
