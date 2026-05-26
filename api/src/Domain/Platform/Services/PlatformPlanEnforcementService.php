<?php

declare(strict_types=1);

namespace Domain\Platform\Services;

use Domain\Auth\Models\AuthUser;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Chat\Models\ChatInstance;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFile;
use Domain\Platform\Enums\PlatformNegotiationsMode;
use Domain\Platform\Enums\PlatformReportsMode;
use Domain\Platform\Enums\PlatformStorageMode;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Serviço de verificação e aplicação dos limites do plano contratado pelo tenant.
 *
 * Centraliza as consultas de elegibilidade para criação de usuários, instâncias,
 * negociações e uso de armazenamento conforme as regras do plano vigente.
 */
final class PlatformPlanEnforcementService
{
    private const int MAX_STORAGE_LIMIT_BYTES = 50 * 1024 * 1024;

    private ?string $cachedPlanTenantId = null;

    private ?PlatformPlan $cachedPlan = null;

    /**
     * @var array<string, list<string>>
     */
    private const REPORTS_BY_MODE = [
        'BASIC' => [
            'reports.chat.volume',
        ],
        'ADVANCED' => [
            'reports.chat.volume',
            'reports.chat.agent_performance',
            'reports.crm.funnel',
            'reports.crm.salesperson_performance',
            'reports.crm.loss_reason',
            'reports.crm.contact_crm',
            'reports.ai.autopilot_performance',
            'reports.ai.sentiment',
        ],
        'FULL' => [
            'reports.chat.volume',
            'reports.chat.agent_performance',
            'reports.crm.funnel',
            'reports.crm.salesperson_performance',
            'reports.crm.loss_reason',
            'reports.crm.contact_crm',
            'reports.ai.autopilot_performance',
            'reports.ai.sentiment',
            'reports.sla.resolution',
            'reports.csat_nps',
            'reports.export',
        ],
        'ADMIN_ONLY' => [
            'reports.ai.usage_cost',
            'reports.billing.revenue',
        ],
    ];

    /**
     * Retorna o plano vigente do tenant, priorizando a última fatura ativa.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @return PlatformPlan|null Plano vigente ou null se não houver.
     */
    public function getCurrentPlan(string $tenantId): ?PlatformPlan
    {
        if ($this->cachedPlanTenantId === $tenantId) {
            return $this->cachedPlan;
        }

        // 1. Prioridade: plano via última fatura ativa (histórico de pagamentos)
        $invoice = BillingInvoice::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('plan_id')
            ->whereIn('status', [
                BillingInvoiceStatus::PAID->value,
                BillingInvoiceStatus::PENDING->value,
                BillingInvoiceStatus::OVERDUE->value,
                BillingInvoiceStatus::DRAFT->value,
            ])
            ->with('plan')
            ->orderByDesc('paid_at')
            ->orderByDesc('due_date')
            ->orderByDesc('created_at')
            ->first();

        // 2. Fallback: usa plan_id direto do tenant (tenants sem fatura ainda ativa)
        $tenant = PlatformTenant::query()
            ->withoutGlobalScopes()
            ->where('id', $tenantId)
            ->with('plan')
            ->first();

        $plan = $invoice?->plan;
        if ($plan === null && $tenant !== null) {
            $plan = $tenant->plan;
        }

        $this->cachedPlanTenantId = $tenantId;
        $this->cachedPlan = $plan;

        return $this->cachedPlan;
    }

    /**
     * Verifica se a funcionalidade de IA está habilitada no plano do tenant.
     *
     * @param  string  $tenantId  UUID do tenant.
     */
    public function isAiEnabled(string $tenantId): bool
    {
        $plan = $this->getCurrentPlan($tenantId);
        if (! $plan) {
            return false;
        }

        return (bool) $plan->ai_enabled;
    }

    /**
     * Verifica se o tenant pode criar mais usuários conforme o limite do plano.
     *
     * @param  string  $tenantId  UUID do tenant.
     */
    public function canCreateUser(string $tenantId): bool
    {
        $plan = $this->getCurrentPlan($tenantId);
        if (! $plan) {
            return true;
        }

        if ($plan->limit_users <= 0) {
            return true;
        }

        $count = AuthUser::query()->where('tenant_id', $tenantId)->count();

        return $count < $plan->limit_users;
    }

    /**
     * Verifica se o tenant pode criar mais instâncias de chat conforme o limite do plano.
     *
     * @param  string  $tenantId  UUID do tenant.
     */
    public function canCreateInstance(string $tenantId): bool
    {
        $plan = $this->getCurrentPlan($tenantId);
        if (! $plan) {
            return true;
        }

        if ($plan->chat_channels_limit <= 0) {
            return true;
        }

        $count = ChatInstance::query()->where('tenant_id', $tenantId)->count();

        return $count < $plan->chat_channels_limit;
    }

    /**
     * Verifica se o tenant pode criar mais negociações conforme o limite do plano.
     *
     * @param  string  $tenantId  UUID do tenant.
     */
    public function canCreateNegotiation(string $tenantId): bool
    {
        $plan = $this->getCurrentPlan($tenantId);
        if (! $plan) {
            return true;
        }

        if ($plan->negotiations_mode !== PlatformNegotiationsMode::LIMITED) {
            return true;
        }

        if (! $plan->negotiations_limit) {
            return true;
        }

        $count = CRMNegotiation::query()->where('tenant_id', $tenantId)->count();

        return $count < $plan->negotiations_limit;
    }

    /**
     * Verifica se o tenant pode fazer upload de um arquivo com o tamanho informado.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  int  $newFileSizeBytes  Tamanho do novo arquivo em bytes.
     */
    public function canUploadFile(string $tenantId, int $newFileSizeBytes = 0): bool
    {
        $limit = $this->getEffectiveStorageLimitBytes($tenantId);

        $used = $this->getTotalStorageUsageBytes($tenantId);

        return ($used + $newFileSizeBytes) <= $limit;
    }

    /**
     * Verifica se o tenant pode baixar arquivos (abaixo do limite de armazenamento).
     *
     * @param  string  $tenantId  UUID do tenant.
     */
    public function canDownloadFile(string $tenantId): bool
    {
        $limit = $this->getEffectiveStorageLimitBytes($tenantId);

        return $this->getStorageUsageBytes($tenantId) < $limit;
    }

    /**
     * Retorna o limite efetivo de armazenamento em bytes para o tenant.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @return int Limite em bytes.
     */
    public function getEffectiveStorageLimitBytes(string $tenantId): int
    {
        $plan = $this->getCurrentPlan($tenantId);

        if (! $plan) {
            return self::MAX_STORAGE_LIMIT_BYTES;
        }

        if ($plan->storage_mode !== PlatformStorageMode::LIMITED) {
            return self::MAX_STORAGE_LIMIT_BYTES;
        }

        $configuredLimit = (int) ($plan->storage_limit_bytes ?? 0);
        if ($configuredLimit <= 0) {
            return self::MAX_STORAGE_LIMIT_BYTES;
        }

        return min($configuredLimit, self::MAX_STORAGE_LIMIT_BYTES);
    }

    /**
     * Retorna o total de bytes utilizados pelo tenant em armazenamento.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @return int Total de bytes usados.
     */
    public function getStorageUsageBytes(string $tenantId): int
    {
        return $this->getTotalStorageUsageBytes($tenantId);
    }

    /**
     * Calcula o total de bytes usados somando arquivos CRM e armazenamento de chat.
     */
    private function getTotalStorageUsageBytes(string $tenantId): int
    {
        $used = (int) CRMNegotiationFile::query()
            ->where('tenant_id', $tenantId)
            ->sum('size');

        return $used + $this->getChatStorageUsageBytes($tenantId);
    }

    /**
     * Calcula o total de bytes usados no armazenamento de chat com cache de 5 minutos.
     */
    private function getChatStorageUsageBytes(string $tenantId): int
    {
        $cacheKey = "tenant:{$tenantId}:chat_storage_bytes";

        return (int) Cache::remember($cacheKey, now()->addMinutes(5), function () use ($tenantId): int {
            $path = "chat/{$tenantId}";
            $disk = Storage::disk('public');

            if (! $disk->exists($path)) {
                return 0;
            }

            $size = 0;

            foreach ($disk->allFiles($path) as $file) {
                try {
                    $size += $disk->size($file);
                } catch (\Throwable $e) {
                    // SECURITY: Log em vez de silenciar completamente
                    report($e);

                    continue;
                }
            }

            return $size;
        });
    }

    /**
     * Retorna o modo de relatórios do plano vigente do tenant.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @return PlatformReportsMode Modo de relatórios (BASIC, ADVANCED ou FULL).
     */
    public function getReportsMode(string $tenantId): PlatformReportsMode
    {
        $plan = $this->getCurrentPlan($tenantId);

        if (! $plan) {
            return PlatformReportsMode::BASIC;
        }

        return $plan->reports_mode ?? PlatformReportsMode::BASIC;
    }

    /**
     * Verifica se o tenant pode ver um relatório específico.
     *
     * @param  string  $tenantId  UUID do tenant
     * @param  string  $permission  Permissão do relatório (ex: reports.chat.volume)
     */
    public function canViewReport(string $tenantId, string $permission): bool
    {
        $user = auth()->user();

        if ($user instanceof AuthUser && $this->isAdmin($user)) {
            return true;
        }

        $mode = $this->getReportsMode($tenantId);
        $allowedPermissions = self::REPORTS_BY_MODE[$mode->value] ?? [];

        return in_array($permission, $allowedPermissions, true);
    }

    /**
     * Verifica se o usuário tem papel de administrador (inquilino).
     *
     * @param  AuthUser  $user  Usuário a verificar.
     */
    public function isAdmin(AuthUser $user): bool
    {
        return $user->isInquilino();
    }
}
