<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\DTOs\TenantPromptDTO;
use Domain\Ai\Enums\AiPromptValidationStatus;
use Domain\Ai\Exceptions\AiPromptInjectionDetectedException;
use Domain\Ai\Jobs\AiPromptGuardianJob;
use Domain\Ai\Models\AiPromptSegment;
use Domain\Ai\Models\AiPromptTenant;
use Domain\Ai\Validators\AiPromptRegexValidator;
use Domain\Platform\Models\PlatformTenant;

/**
 * Action para atualização de Tenant Prompts.
 *
 * Orquestra o fluxo: Regex check → Swap content → Save PENDING → Dispatch Guardian.
 */
final class UpdateTenantPromptAction
{
    public function __construct(
        private readonly AiPromptRegexValidator $regexValidator,
    ) {}

    /**
     * Atualiza o prompt personalizado do tenant com validação e versionamento.
     *
     * Fluxo: validação regex síncrona → swap content/previous_content →
     * salva com status PENDING → despacha AiPromptGuardianJob para validação LLM assíncrona.
     *
     * @param  PlatformTenant  $tenant  Tenant dono do prompt.
     * @param  TenantPromptDTO  $dto  Novo conteúdo do prompt.
     * @return AiPromptTenant Prompt atualizado com status PENDING.
     *
     * @throws \Domain\Ai\Exceptions\AiPromptInjectionDetectedException Se padrão de injeção for detectado pelo regex.
     * @throws \RuntimeException Se não houver segmento disponível para criação de novo prompt.
     */
    public function execute(PlatformTenant $tenant, TenantPromptDTO $dto): AiPromptTenant
    {
        // 1. Validação Regex síncrona
        $validationResult = $this->regexValidator->validate($dto->content);

        if ($validationResult->hasFailed()) {
            throw new AiPromptInjectionDetectedException(
                $validationResult->matchedPattern ?? 'unknown',
                $validationResult->matchedRegex ?? ''
            );
        }

        // 2. Buscar ou criar prompt do tenant
        $tenantPrompt = AiPromptTenant::findByTenantId($tenant->id);

        if ($tenantPrompt) {
            // 3. Swap content/previous_content
            $tenantPrompt->forceFill([
                'previous_content' => $tenantPrompt->content,
                'content' => $dto->content,
                'version' => $tenantPrompt->version + 1,
                'validation_status' => AiPromptValidationStatus::PENDING,
                'validated_hash' => null,
                'validated_at' => null,
            ])->save();
        } else {
            // Novo prompt
            $segmentId = $tenant->segment_id ?? AiPromptSegment::getGeneral()?->id;

            if (! $segmentId) {
                throw new \RuntimeException('No segment available for tenant prompt.');
            }

            $tenantPrompt = AiPromptTenant::query()->create([
                'tenant_id' => $tenant->id,
                'segment_id' => $segmentId,
                'content' => $dto->content,
                'previous_content' => null,
                'version' => 1,
                'validation_status' => AiPromptValidationStatus::PENDING,
                'is_active' => true,
            ]);
        }

        // 4. Dispatch Guardian Job
        AiPromptGuardianJob::dispatch($tenantPrompt->id, (string) $tenantPrompt->tenant_id);

        return $tenantPrompt->refresh();
    }
}
