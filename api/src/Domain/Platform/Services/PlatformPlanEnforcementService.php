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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Serviço de enforcement de limites do plano.
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

    public function getCurrentPlan(string $tenantId): ?PlatformPlan
    {
        if ($this->cachedPlanTenantId === $tenantId) {
            return $this->cachedPlan;
        }

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

        $this->cachedPlanTenantId = $tenantId;
        $this->cachedPlan = $invoice?->plan;

        return $this->cachedPlan;
    }

    public function isAiEnabled(string $tenantId): bool
    {
        $plan = $this->getCurrentPlan($tenantId);
        if (! $plan) {
            return false;
        }

        return (bool) $plan->ai_enabled;
    }

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

    public function canUploadFile(string $tenantId, int $newFileSizeBytes = 0): bool
    {
        $limit = $this->getEffectiveStorageLimitBytes($tenantId);

        $used = $this->getTotalStorageUsageBytes($tenantId);

        return ($used + $newFileSizeBytes) <= $limit;
    }

    public function canDownloadFile(string $tenantId): bool
    {
        $limit = $this->getEffectiveStorageLimitBytes($tenantId);

        return $this->getStorageUsageBytes($tenantId) < $limit;
    }

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

    public function getStorageUsageBytes(string $tenantId): int
    {
        return $this->getTotalStorageUsageBytes($tenantId);
    }

    private function getTotalStorageUsageBytes(string $tenantId): int
    {
        $used = (int) CRMNegotiationFile::query()
            ->where('tenant_id', $tenantId)
            ->sum('size');

        return $used + $this->getChatStorageUsageBytes($tenantId);
    }

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

    public function isAdmin(AuthUser $user): bool
    {
        return $user->isInquilino();
    }
}
