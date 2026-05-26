<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Ai\Models\AiPromptTenant;
use Domain\Ai\Services\AiPromptResolverService;
use Domain\Platform\Models\PlatformTenant;

/**
 * Casos de uso de leitura e resolução de prompts personalizados do tenant.
 *
 * Contexto: acesso ao AiPromptTenant combinado com AiPromptResolverService
 * para expor o prompt completo resolvido (MASTER > SEGMENT > PLAN > TENANT)
 * e invalidar o cache quando necessário.
 */
final class AiPromptTenantActions
{
    /**
     * Injeta o serviço de resolução de prompts para compor a hierarquia MASTER > SEGMENT > PLAN > TENANT.
     *
     * @param  AiPromptResolverService  $resolverService  Serviço de resolução e cache de prompts.
     */
    public function __construct(private readonly AiPromptResolverService $resolverService) {}

    /**
     * Busca o prompt personalizado do tenant.
     *
     * @param  PlatformTenant  $tenant  Tenant a consultar.
     * @return AiPromptTenant|null Prompt do tenant ou null se não configurado.
     */
    public function findByTenant(PlatformTenant $tenant): ?AiPromptTenant
    {
        return AiPromptTenant::findByTenantId($tenant->id);
    }

    /**
     * Busca o prompt do tenant com a relação segment carregada.
     *
     * @param  PlatformTenant  $tenant  Tenant a consultar.
     * @return AiPromptTenant|null Prompt com segment carregado ou null.
     */
    public function findWithSegment(PlatformTenant $tenant): ?AiPromptTenant
    {
        $prompt = $this->findByTenant($tenant);

        return $prompt?->load('segment');
    }

    /**
     * Carrega a relação segment de um prompt já obtido.
     *
     * @param  AiPromptTenant  $prompt  Prompt a hidratar.
     * @return AiPromptTenant Prompt com relação segment carregada.
     */
    public function loadSegment(AiPromptTenant $prompt): AiPromptTenant
    {
        return $prompt->load('segment');
    }

    /**
     * Retorna o prompt completo resolvido e seus componentes individuais.
     *
     * @param  PlatformTenant  $tenant  Tenant a resolver.
     * @return array<string, mixed> Array com 'resolved_prompt' (string) e 'components' (array).
     */
    public function resolve(PlatformTenant $tenant): array
    {
        return [
            'resolved_prompt' => $this->resolverService->resolve($tenant),
            'components' => $this->resolverService->getComponents($tenant),
        ];
    }

    /**
     * Invalida o cache de prompt resolvido do tenant.
     *
     * @param  string  $tenantId  UUID do tenant.
     */
    public function forgetBasePromptCache(string $tenantId): void
    {
        $this->resolverService->forgetBasePromptCache($tenantId);
    }
}
