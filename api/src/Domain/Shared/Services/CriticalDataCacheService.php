<?php

declare(strict_types=1);

namespace Domain\Shared\Services;

use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatQuickAnswer;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Serviço de cache para dados críticos de acesso frequente.
 *
 * Fornece lookups em cache para assinaturas de tenant, instâncias de chat,
 * cotas de plano, funis de negociação e respostas rápidas, reduzindo a
 * carga no banco de dados em caminhos críticos (hot paths).
 */
final class CriticalDataCacheService
{
    /**
     * Cache TTL for tenant subscription data (5 minutes).
     */
    private const SUBSCRIPTION_TTL = 300;

    /**
     * Cache TTL for chat instance by token (10 minutes).
     */
    private const INSTANCE_TOKEN_TTL = 600;

    /**
     * Cache TTL for plan quotas (1 hour).
     */
    private const PLAN_QUOTAS_TTL = 3600;

    /**
     * Cache TTL for tenant config (1 hour).
     */
    private const TENANT_CONFIG_TTL = 3600;

    /**
     * Cache TTL for funnel steps (30 minutes).
     */
    private const FUNNEL_STEPS_TTL = 1800;

    /**
     * Cache TTL for quick answers (30 minutes).
     */
    private const QUICK_ANSWERS_TTL = 1800;

    /**
     * Retorna dados de assinatura do tenant em cache ou banco.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @return array{plan_id: string|null, is_active: bool, tenant_code: string|null}|null Dados da assinatura ou null se não encontrado.
     */
    public function getTenantSubscription(string $tenantId): ?array
    {
        $key = "tenant:{$tenantId}:subscription";

        /** @var array{plan_id: string|null, is_active: bool, tenant_code: string|null}|null $result */
        $result = Cache::remember($key, self::SUBSCRIPTION_TTL, function () use ($tenantId): ?array {
            $tenant = PlatformTenant::query()
                ->select(['id', 'is_active', 'tenant_code'])
                ->find($tenantId);

            if (! $tenant) {
                return null;
            }

            return [
                'plan_id' => null, // Subscription model doesn't exist - placeholder
                'is_active' => (bool) $tenant->is_active,
                'tenant_code' => $tenant->tenant_code,
            ];
        });

        return $result;
    }

    /**
     * Invalida o cache de assinatura do tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     */
    public function forgetTenantSubscription(string $tenantId): void
    {
        Cache::forget("tenant:{$tenantId}:subscription");
    }

    /**
     * Retorna a instância de chat pelo token de webhook em cache ou banco.
     *
     * @param  string  $token  Token de webhook da instância.
     * @return ChatInstance|null Instância encontrada ou null.
     */
    public function getChatInstanceByToken(string $token): ?ChatInstance
    {
        $key = "instance:token:{$token}";

        /** @var array{id: string, tenant_id: string}|null $cached */
        $cached = Cache::remember($key, self::INSTANCE_TOKEN_TTL, function () use ($token): ?array {
            $instance = ChatInstance::query()
                ->where('webhook_token', $token)
                ->where('is_active', true)
                ->first();

            if (! $instance) {
                return null;
            }

            return ['id' => $instance->id, 'tenant_id' => $instance->tenant_id];
        });

        if (! $cached) {
            return null;
        }

        /** @var ChatInstance|null */
        return ChatInstance::query()
            ->where('tenant_id', $cached['tenant_id'])
            ->find($cached['id']);
    }

    /**
     * Invalida o cache de instância de chat pelo token de webhook.
     *
     * @param  string  $token  Token de webhook da instância.
     */
    public function forgetChatInstanceToken(string $token): void
    {
        Cache::forget("instance:token:{$token}");
    }

    /**
     * Retorna as cotas do plano em cache ou banco.
     *
     * @param  string  $planId  Identificador do plano.
     * @return array{
     *     limit_users: int,
     *     storage_limit_bytes: int,
     *     ai_enabled: bool,
     *     chat_channels_limit: int,
     *     negotiations_limit: int|null
     * }|null Cotas do plano ou null se não encontrado.
     */
    public function getPlanQuotas(string $planId): ?array
    {
        $key = "plan:{$planId}:quotas";

        /** @var array{limit_users: int, storage_limit_bytes: int, ai_enabled: bool, chat_channels_limit: int, negotiations_limit: int|null}|null $result */
        $result = Cache::remember($key, self::PLAN_QUOTAS_TTL, function () use ($planId): ?array {
            $plan = PlatformPlan::query()
                ->select([
                    'id',
                    'limit_users',
                    'storage_limit_bytes',
                    'ai_enabled',
                    'chat_channels_limit',
                    'negotiations_limit',
                ])
                ->find($planId);

            if (! $plan) {
                return null;
            }

            return [
                'limit_users' => (int) $plan->limit_users,
                'storage_limit_bytes' => (int) $plan->storage_limit_bytes,
                'ai_enabled' => (bool) $plan->ai_enabled,
                'chat_channels_limit' => (int) $plan->chat_channels_limit,
                'negotiations_limit' => $plan->negotiations_limit,
            ];
        });

        return $result;
    }

    /**
     * Invalida o cache de cotas do plano.
     *
     * @param  string  $planId  Identificador do plano.
     */
    public function forgetPlanQuotas(string $planId): void
    {
        Cache::forget("plan:{$planId}:quotas");
    }

    /**
     * Retorna a configuração do tenant em cache ou banco.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @return array{
     *     name: string,
     *     segment_id: string|null,
     *     is_active: bool,
     *     tenant_code: string|null
     * }|null Configuração do tenant ou null se não encontrado.
     */
    public function getTenantConfig(string $tenantId): ?array
    {
        $key = "tenant:{$tenantId}:config";

        /** @var array{name: string, segment_id: string|null, is_active: bool, tenant_code: string|null}|null $result */
        $result = Cache::remember($key, self::TENANT_CONFIG_TTL, function () use ($tenantId): ?array {
            $tenant = PlatformTenant::query()
                ->select(['id', 'name', 'segment_id', 'is_active', 'tenant_code'])
                ->find($tenantId);

            if (! $tenant) {
                return null;
            }

            return [
                'name' => $tenant->name,
                'segment_id' => $tenant->segment_id,
                'is_active' => (bool) $tenant->is_active,
                'tenant_code' => $tenant->tenant_code,
            ];
        });

        return $result;
    }

    /**
     * Invalida o cache de configuração do tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     */
    public function forgetTenantConfig(string $tenantId): void
    {
        Cache::forget("tenant:{$tenantId}:config");
    }

    /**
     * Retorna o funil com suas etapas em cache ou banco.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $funnelId  Identificador do funil.
     * @return array{
     *     id: string,
     *     name: string,
     *     is_active: bool,
     *     steps: array<int, array{id: string, name: string, color: string, order: int, is_active: bool}>
     * }|null Funil com etapas ou null se não encontrado.
     */
    public function getFunnelWithSteps(string $tenantId, string $funnelId): ?array
    {
        $key = "tenant:{$tenantId}:funnel:{$funnelId}";

        /** @var array{id: string, name: string, is_active: bool, steps: array<int, array{id: string, name: string, color: string, order: int, is_active: bool}>}|null $result */
        $result = Cache::remember($key, self::FUNNEL_STEPS_TTL, function () use ($tenantId, $funnelId): ?array {
            $funnel = CRMNegotiationFunnel::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $funnelId)
                ->with('steps')
                ->first();

            if (! $funnel) {
                return null;
            }

            return [
                'id' => $funnel->id,
                'name' => $funnel->name,
                'is_active' => (bool) $funnel->is_active,
                'steps' => $funnel->steps->map(fn ($step) => [
                    'id' => $step->id,
                    'name' => $step->name,
                    'color' => $step->color,
                    'order' => (int) $step->order,
                    'is_active' => (bool) $step->is_active,
                ])->toArray(),
            ];
        });

        return $result;
    }

    /**
     * Retorna todos os funis do tenant em cache ou banco.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @return Collection<array-key, array{id: string, name: string, is_active: bool}> Coleção de funis do tenant.
     */
    public function getTenantFunnels(string $tenantId): Collection
    {
        $key = "tenant:{$tenantId}:funnels:all";

        /** @var array<int, array{id: string, name: string, is_active: bool}>|null $cached */
        $cached = Cache::remember($key, self::FUNNEL_STEPS_TTL, function () use ($tenantId): array {
            return CRMNegotiationFunnel::query()
                ->where('tenant_id', $tenantId)
                ->select(['id', 'name', 'is_active'])
                ->orderBy('name')
                ->get()
                ->map(fn ($funnel) => [
                    'id' => $funnel->id,
                    'name' => $funnel->name,
                    'is_active' => (bool) $funnel->is_active,
                ])
                ->toArray();
        });

        return collect($cached ?? []);
    }

    /**
     * Invalida o cache do funil e da listagem de funis do tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $funnelId  Identificador do funil.
     */
    public function forgetFunnel(string $tenantId, string $funnelId): void
    {
        Cache::forget("tenant:{$tenantId}:funnel:{$funnelId}");
        Cache::forget("tenant:{$tenantId}:funnels:all");
    }

    /**
     * Invalida o cache de todos os funis do tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     */
    public function forgetTenantFunnels(string $tenantId): void
    {
        Cache::forget("tenant:{$tenantId}:funnels:all");
    }

    /**
     * Retorna as respostas rápidas ativas do tenant em cache ou banco.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @return Collection<array-key, array{id: string, name: string, shortcut: string|null, content: string, category: string|null}> Coleção de respostas rápidas ativas.
     */
    public function getQuickAnswers(string $tenantId): Collection
    {
        $key = "tenant:{$tenantId}:quick_answers";

        /** @var array<int, array{id: string, name: string, shortcut: string|null, content: string, category: string|null}>|null $cached */
        $cached = Cache::remember($key, self::QUICK_ANSWERS_TTL, function () use ($tenantId): array {
            return ChatQuickAnswer::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->select(['id', 'name', 'shortcut', 'content', 'category'])
                ->orderBy('name')
                ->get()
                ->map(fn ($qa) => [
                    'id' => $qa->id,
                    'name' => $qa->name,
                    'shortcut' => $qa->shortcut,
                    'content' => $qa->content,
                    'category' => $qa->category,
                ])
                ->toArray();
        });

        // @phpstan-ignore-next-line
        return collect($cached ?? []);
    }

    /**
     * Invalida o cache de respostas rápidas do tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     */
    public function forgetQuickAnswers(string $tenantId): void
    {
        Cache::forget("tenant:{$tenantId}:quick_answers");
    }
}
