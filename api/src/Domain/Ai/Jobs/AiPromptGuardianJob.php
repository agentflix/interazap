<?php

declare(strict_types=1);

namespace Domain\Ai\Jobs;

use Domain\Ai\Contracts\AiGuardianServiceInterface;
use Domain\Ai\Contracts\AiPromptHashServiceInterface;
use Domain\Ai\Enums\AiPromptValidationStatus;
use Domain\Ai\Models\AiPromptTenant;
use Domain\Ai\Notifications\PromptQuarantinedNotification;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Shared\Concerns\HasJobDefaults;
use Domain\Shared\Services\MetricsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Job para validação de prompt via LLM Guardian.
 *
 * Executa a validação assíncrona do prompt do tenant utilizando
 * o serviço Guardian (GPT-4o-mini) e atualiza o status conforme resultado.
 */
class AiPromptGuardianJob implements ShouldQueue
{
    use Dispatchable;
    use HasJobDefaults;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantPromptId,
    ) {}

    public function handle(
        AiGuardianServiceInterface $guardian,
        AiPromptHashServiceInterface $hashService,
        MetricsService $metricsService,
    ): void {
        $tenantPrompt = AiPromptTenant::query()->find($this->tenantPromptId);

        if (! $tenantPrompt) {
            Log::warning('AiPromptGuardianJob: Tenant prompt not found', [
                'tenant_prompt_id' => $this->tenantPromptId,
            ]);

            return;
        }

        // Só processa se ainda estiver pendente
        if (! $tenantPrompt->isPending()) {
            Log::info('AiPromptGuardianJob: Prompt already processed', [
                'tenant_prompt_id' => $this->tenantPromptId,
                'status' => $tenantPrompt->validation_status->value,
            ]);

            return;
        }

        $result = $guardian->validate($tenantPrompt->content);

        if ($result->isSafe()) {
            // Aprovar e calcular hash
            $tenantPrompt->forceFill([
                'validation_status' => AiPromptValidationStatus::APPROVED,
                'validated_hash' => $hashService->hash($tenantPrompt->content),
                'validated_at' => now(),
            ])->save();

            Log::info('AiPromptGuardianJob: Prompt approved', [
                'tenant_prompt_id' => $this->tenantPromptId,
                'tenant_id' => $tenantPrompt->tenant_id,
            ]);
        } else {
            // Colocar em quarentena
            $tenantPrompt->forceFill([
                'validation_status' => AiPromptValidationStatus::QUARANTINE,
            ])->save();

            Log::warning('AiPromptGuardianJob: Prompt quarantined', [
                'tenant_prompt_id' => $this->tenantPromptId,
                'tenant_id' => $tenantPrompt->tenant_id,
                'reason' => $result->reason,
                'category' => $result->category,
            ]);

            $metricsService->recordAutopilotGuardrailBlock([
                'stage' => 'post_response',
            ]);

            // Notificar SuperAdmins
            $this->notifySuperAdmins($tenantPrompt, $result->reason ?? 'Unknown security concern');
        }
    }

    /**
     * Notifica os SuperAdmins sobre o prompt em quarentena.
     */
    private function notifySuperAdmins(AiPromptTenant $tenantPrompt, string $reason): void
    {
        $admins = AuthUser::query()
            ->whereHas('roles', static function ($query): void {
                $query->where('guard_name', 'sanctum')
                    ->whereIn('id', [AuthRole::ADMINISTRADOR_ID]);
            })
            ->get();

        if ($admins->isEmpty()) {
            Log::warning('AiPromptGuardianJob: No admins found to notify');

            return;
        }

        Notification::send(
            $admins,
            new PromptQuarantinedNotification($tenantPrompt, $reason)
        );
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('AiPromptGuardianJob: Job failed', [
            'tenant_prompt_id' => $this->tenantPromptId,
            'error' => $exception->getMessage(),
        ]);
    }
}
