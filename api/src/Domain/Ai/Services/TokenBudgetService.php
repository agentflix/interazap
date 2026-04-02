<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Events\AiBudgetThresholdExceeded;
use Domain\Shared\Services\MetricsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * Token Budget System (Autopilot Phase 4).
 *
 * Manages per-run, per-day, and per-month budget tracking.
 * Alerts when > 80% consumed, auto-disables when > 100%.
 */
final class TokenBudgetService
{
    private const CACHE_PREFIX = 'autopilot:budget:';

    private const CACHE_TTL_DAY = 86400; // 24 hours

    private const CACHE_TTL_MONTH = 2592000; // 30 days

    private const THRESHOLD_WARNING = 0.8; // 80%

    private const THRESHOLD_CRITICAL = 1.0; // 100%

    /**
     * Check if a run can execute within the tenant's budget.
     *
     * @param  string  $tenantId  Tenant UUID
     * @param  float  $estimatedCostDollars  Estimated cost for the run
     * @return array{allowed: bool, reason: string, usage_ratio: float}
     */
    public function canExecuteRun(string $tenantId, float $estimatedCostDollars): array
    {
        $config = $this->getTenantBudgetConfig($tenantId);

        if ($config['is_disabled'] === true) {
            $stats = $this->getUsageStats($tenantId);

            return [
                'allowed' => false,
                'reason' => 'budget_auto_disabled',
                'usage_ratio' => max($stats['daily_ratio'], $stats['monthly_ratio']),
            ];
        }

        if ($config['daily_limit'] === null && $config['monthly_limit'] === null) {
            return [
                'allowed' => true,
                'reason' => 'no_budget_limit',
                'usage_ratio' => 0.0,
            ];
        }

        $dailyUsage = $this->getDailyUsageDollars($tenantId);
        $monthlyUsage = $this->getMonthlyUsageDollars($tenantId);

        // Check daily limit
        if ($config['daily_limit'] !== null) {
            $dailyRatio = ($dailyUsage + $estimatedCostDollars) / $config['daily_limit'];

            if ($dailyRatio > self::THRESHOLD_CRITICAL) {
                return [
                    'allowed' => false,
                    'reason' => 'daily_budget_exceeded',
                    'usage_ratio' => $dailyRatio,
                ];
            }
        }

        // Check monthly limit
        if ($config['monthly_limit'] !== null) {
            $monthlyRatio = ($monthlyUsage + $estimatedCostDollars) / $config['monthly_limit'];

            if ($monthlyRatio > self::THRESHOLD_CRITICAL) {
                return [
                    'allowed' => false,
                    'reason' => 'monthly_budget_exceeded',
                    'usage_ratio' => $monthlyRatio,
                ];
            }
        }

        return [
            'allowed' => true,
            'reason' => 'within_budget',
            'usage_ratio' => max(
                $config['daily_limit'] !== null ? $dailyUsage / $config['daily_limit'] : 0,
                $config['monthly_limit'] !== null ? $monthlyUsage / $config['monthly_limit'] : 0
            ),
        ];
    }

    /**
     * Record actual usage after run completion.
     *
     * @param  string  $tenantId  Tenant UUID
     * @param  float  $actualCostDollars  Actual cost of the run
     */
    public function recordUsage(string $tenantId, float $actualCostDollars): void
    {
        $dailyKey = self::CACHE_PREFIX.'daily:'.$tenantId.':'.date('Y-m-d');
        $monthlyKey = self::CACHE_PREFIX.'monthly:'.$tenantId.':'.date('Y-m');

        // Atomic increments via Redis INCRBYFLOAT to prevent race conditions (C-01)
        $redis = Redis::connection(config('cache.stores.redis.connection', 'default'));
        $prefix = config('cache.prefix', '');

        $redis->incrbyfloat($prefix.$dailyKey, $actualCostDollars);
        if ((int) $redis->ttl($prefix.$dailyKey) < 0) {
            $redis->expire($prefix.$dailyKey, self::CACHE_TTL_DAY);
        }

        $redis->incrbyfloat($prefix.$monthlyKey, $actualCostDollars);
        if ((int) $redis->ttl($prefix.$monthlyKey) < 0) {
            $redis->expire($prefix.$monthlyKey, self::CACHE_TTL_MONTH);
        }

        // Check thresholds and dispatch alerts
        $this->checkThresholdsAndAlert($tenantId, $actualCostDollars);

        $stats = $this->getUsageStats($tenantId);
        $this->metrics()->setAutopilotBudgetUsageRatio(
            max($stats['daily_ratio'], $stats['monthly_ratio']),
            ['tenant_id' => $tenantId]
        );
    }

    /**
     * Get current daily usage in dollars.
     *
     * @param  string  $tenantId  Tenant UUID
     */
    public function getDailyUsageDollars(string $tenantId): float
    {
        $key = self::CACHE_PREFIX.'daily:'.$tenantId.':'.date('Y-m-d');
        $prefix = config('cache.prefix', '');

        return (float) (Redis::connection(config('cache.stores.redis.connection', 'default'))->get($prefix.$key) ?? 0);
    }

    /**
     * Get current monthly usage in dollars.
     *
     * @param  string  $tenantId  Tenant UUID
     */
    public function getMonthlyUsageDollars(string $tenantId): float
    {
        $key = self::CACHE_PREFIX.'monthly:'.$tenantId.':'.date('Y-m');
        $prefix = config('cache.prefix', '');

        return (float) (Redis::connection(config('cache.stores.redis.connection', 'default'))->get($prefix.$key) ?? 0);
    }

    /**
     * Get usage statistics for a tenant.
     *
     * @param  string  $tenantId  Tenant UUID
     * @return array{daily_usage: float, daily_limit: float|null, daily_ratio: float, monthly_usage: float, monthly_limit: float|null, monthly_ratio: float}
     */
    public function getUsageStats(string $tenantId): array
    {
        $config = $this->getTenantBudgetConfig($tenantId);
        $dailyUsage = $this->getDailyUsageDollars($tenantId);
        $monthlyUsage = $this->getMonthlyUsageDollars($tenantId);

        return [
            'daily_usage' => $dailyUsage,
            'daily_limit' => $config['daily_limit'],
            'daily_ratio' => $config['daily_limit'] ? $dailyUsage / $config['daily_limit'] : 0.0,
            'monthly_usage' => $monthlyUsage,
            'monthly_limit' => $config['monthly_limit'],
            'monthly_ratio' => $config['monthly_limit'] ? $monthlyUsage / $config['monthly_limit'] : 0.0,
        ];
    }

    /**
     * Update budget configuration for a tenant.
     *
     * @param  string  $tenantId  Tenant UUID
     * @param  float|null  $dailyLimit  Daily budget limit in dollars
     * @param  float|null  $monthlyLimit  Monthly budget limit in dollars
     */
    public function updateBudgetConfig(string $tenantId, ?float $dailyLimit, ?float $monthlyLimit): void
    {
        $key = self::CACHE_PREFIX.'config:'.$tenantId;

        Cache::put($key, [
            'daily_limit' => $dailyLimit,
            'monthly_limit' => $monthlyLimit,
            'is_disabled' => false,
            'updated_at' => now()->toIso8601String(),
        ], self::CACHE_TTL_MONTH);

        Log::info('Autopilot budget config updated', [
            'tenant_id' => $tenantId,
            'daily_limit' => $dailyLimit,
            'monthly_limit' => $monthlyLimit,
        ]);
    }

    /**
     * Get budget configuration for tenant management endpoints.
     *
     * @param  string  $tenantId  Tenant UUID
     * @return array{daily_limit: float|null, monthly_limit: float|null, is_disabled: bool}
     */
    public function getBudgetConfig(string $tenantId): array
    {
        return $this->getTenantBudgetConfig($tenantId);
    }

    /**
     * Get tenant budget configuration.
     *
     * @param  string  $tenantId  Tenant UUID
     * @return array{daily_limit: float|null, monthly_limit: float|null, is_disabled: bool}
     */
    private function getTenantBudgetConfig(string $tenantId): array
    {
        $key = self::CACHE_PREFIX.'config:'.$tenantId;

        $config = Cache::get($key);

        if ($config === null) {
            // Default: no limits (fallback to database or config)
            $config = [
                'daily_limit' => config('ai.budget.default_daily_limit'),
                'monthly_limit' => config('ai.budget.default_monthly_limit'),
                'is_disabled' => false,
            ];

            Cache::put($key, $config, self::CACHE_TTL_MONTH);
        }

        return [
            'daily_limit' => isset($config['daily_limit']) ? (is_null($config['daily_limit']) ? null : (float) $config['daily_limit']) : null,
            'monthly_limit' => isset($config['monthly_limit']) ? (is_null($config['monthly_limit']) ? null : (float) $config['monthly_limit']) : null,
            'is_disabled' => (bool) ($config['is_disabled'] ?? false),
        ];
    }

    /**
     * Check thresholds and dispatch alerts if necessary.
     *
     * @param  string  $tenantId  Tenant UUID
     * @param  float  $lastUsageCost  Last usage cost in dollars
     */
    private function checkThresholdsAndAlert(string $tenantId, float $lastUsageCost): void
    {
        $stats = $this->getUsageStats($tenantId);

        // Check daily threshold
        if ($stats['daily_limit'] !== null && $stats['daily_ratio'] > self::THRESHOLD_WARNING) {
            $level = $stats['daily_ratio'] >= self::THRESHOLD_CRITICAL ? 'critical' : 'warning';

            if ($level === 'critical') {
                $this->disableBudget($tenantId);
            }

            event(new AiBudgetThresholdExceeded(
                tenantId: $tenantId,
                period: 'daily',
                usage: $stats['daily_usage'],
                limit: $stats['daily_limit'],
                ratio: $stats['daily_ratio'],
                level: $level
            ));
        }

        // Check monthly threshold
        if ($stats['monthly_limit'] !== null && $stats['monthly_ratio'] > self::THRESHOLD_WARNING) {
            $level = $stats['monthly_ratio'] >= self::THRESHOLD_CRITICAL ? 'critical' : 'warning';

            if ($level === 'critical') {
                $this->disableBudget($tenantId);
            }

            event(new AiBudgetThresholdExceeded(
                tenantId: $tenantId,
                period: 'monthly',
                usage: $stats['monthly_usage'],
                limit: $stats['monthly_limit'],
                ratio: $stats['monthly_ratio'],
                level: $level
            ));
        }
    }

    private function disableBudget(string $tenantId): void
    {
        $configKey = self::CACHE_PREFIX.'config:'.$tenantId;
        $currentConfig = $this->getTenantBudgetConfig($tenantId);

        Cache::put($configKey, [
            'daily_limit' => $currentConfig['daily_limit'],
            'monthly_limit' => $currentConfig['monthly_limit'],
            'is_disabled' => true,
            'updated_at' => now()->toIso8601String(),
        ], self::CACHE_TTL_MONTH);
    }

    private function metrics(): MetricsService
    {
        /** @var MetricsService $metrics */
        $metrics = app(MetricsService::class);

        return $metrics;
    }
}
