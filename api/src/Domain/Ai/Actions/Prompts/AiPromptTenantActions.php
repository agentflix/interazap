<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\Models\AiPromptTenant;
use Domain\Ai\Services\AiPromptResolverService;
use Domain\Platform\Models\PlatformTenant;

/**
 * Actions para prompts do tenant.
 */
final class AiPromptTenantActions
{
    public function __construct(private readonly AiPromptResolverService $resolverService) {}

    /**
     * Buscar prompt do tenant.
     */
    public function findByTenant(PlatformTenant $tenant): ?AiPromptTenant
    {
        return AiPromptTenant::findByTenantId($tenant->id);
    }

    /**
     * Buscar prompt com segmento carregado.
     */
    public function findWithSegment(PlatformTenant $tenant): ?AiPromptTenant
    {
        $prompt = $this->findByTenant($tenant);

        return $prompt?->load('segment');
    }

    /**
     * Carregar segmento do prompt informado.
     */
    public function loadSegment(AiPromptTenant $prompt): AiPromptTenant
    {
        return $prompt->load('segment');
    }

    /**
     * Resolver prompt completo do tenant.
     *
     * @return array<string, mixed>
     */
    public function resolve(PlatformTenant $tenant): array
    {
        return [
            'resolved_prompt' => $this->resolverService->resolve($tenant),
            'components' => $this->resolverService->getComponents($tenant),
        ];
    }

    public function forgetBasePromptCache(string $tenantId): void
    {
        $this->resolverService->forgetBasePromptCache($tenantId);
    }
}
