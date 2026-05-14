<?php

declare(strict_types=1);

use Domain\Ai\Events\AiBudgetThresholdExceeded;
use Domain\Ai\Services\TokenBudgetService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Redis;

/**
 * @group redis
 * @group integration
 *
 * These tests require Redis to be running with phpredis extension.
 * They test the TokenBudgetService which stores budget data in Redis.
 *
 * To run these tests, ensure:
 * 1. Redis server is running on localhost:6379
 * 2. PHP redis extension is installed (php --ri redis)
 * 3. Redis is accessible from the test database
 *
 * These tests are @integration because they verify the Redis integration
 * which is difficult to mock reliably at the unit test level.
 */
uses(LazilyRefreshDatabase::class);

// Global spy - initialized once per process, reset per test
$spy = new class
{
    public array $daily = [];

    public array $monthly = [];

    public function incrbyfloat(string $key, float $value): float
    {
        // Key format: autopilot:budget:daily:{tenantId}:{date}
        // Or: autopilot:budget:monthly:{tenantId}:{year-month}
        $parts = explode(':', $key);
        // parts[0] = 'autopilot'
        // parts[1] = 'budget'
        // parts[2] = 'daily' or 'monthly'
        // parts[3] = tenantId
        // parts[4] = date or year-month
        $period = $parts[2] ?? 'daily';
        $tenantId = $parts[3] ?? 'unknown';

        if ($period === 'daily') {
            $this->daily[$tenantId] = ($this->daily[$tenantId] ?? 0.0) + $value;

            return $this->daily[$tenantId];
        }
        $this->monthly[$tenantId] = ($this->monthly[$tenantId] ?? 0.0) + $value;

        return $this->monthly[$tenantId];
    }

    public function get(string $key): string
    {
        $parts = explode(':', $key);
        $period = $parts[2] ?? 'daily';
        $tenantId = $parts[3] ?? 'unknown';

        if ($period === 'daily') {
            return isset($this->daily[$tenantId]) ? (string) $this->daily[$tenantId] : '0';
        }

        return isset($this->monthly[$tenantId]) ? (string) $this->monthly[$tenantId] : '0';
    }

    public function ttl(): int
    {
        return -1;
    }

    public function expire(): bool
    {
        return true;
    }

    public function reset(string $tenantId): void
    {
        $this->daily[$tenantId] = 0.0;
        $this->monthly[$tenantId] = 0.0;
    }
};

beforeEach(function () use ($spy): void {
    Cache::flush();

    $mockConnection = Mockery::mock();
    $mockConnection->shouldReceive('incrbyfloat')->andReturnUsing(fn (string $k, float $v): float => $spy->incrbyfloat($k, $v));
    $mockConnection->shouldReceive('get')->andReturnUsing(fn (string $k): string => $spy->get($k));
    $mockConnection->shouldReceive('ttl')->andReturn(-1);
    $mockConnection->shouldReceive('expire')->andReturn(true);

    Redis::shouldReceive('connection')->andReturn($mockConnection);

    $tenant = PlatformTenant::factory()->create();
    $this->tenantId = (string) $tenant->id;
    $spy->reset($this->tenantId);
    $this->service = new TokenBudgetService;
});

afterEach(function (): void {
    Mockery::close();
});

describe('TokenBudgetService', function (): void {
    describe('canExecuteRun()', function (): void {
        it('allows execution when no budget limit is configured', function (): void {
            $result = $this->service->canExecuteRun($this->tenantId, 0.50);

            expect($result['allowed'])->toBeTrue()
                ->and($result['reason'])->toBe('no_budget_limit')
                ->and($result['usage_ratio'])->toBe(0.0);
        });

        it('allows execution when within daily budget', function (): void {
            $this->service->updateBudgetConfig($this->tenantId, dailyLimit: 10.0, monthlyLimit: null);

            $this->service->recordUsage($this->tenantId, 5.0);

            $dailyUsage = $this->service->getDailyUsageDollars($this->tenantId);
            $result = $this->service->canExecuteRun($this->tenantId, 2.0);

            expect($result['allowed'])->toBeTrue()
                ->and($result['reason'])->toBe('within_budget')
                ->and($result['usage_ratio'])->toBeLessThan(1.0);
        });

        it('blocks execution when daily budget would be exceeded', function (): void {
            $this->service->updateBudgetConfig($this->tenantId, dailyLimit: 10.0, monthlyLimit: null);
            $this->service->recordUsage($this->tenantId, 9.0);

            $result = $this->service->canExecuteRun($this->tenantId, 2.0);

            expect($result['allowed'])->toBeFalse()
                ->and($result['reason'])->toBe('daily_budget_exceeded')
                ->and($result['usage_ratio'])->toBeGreaterThan(1.0);
        });

        it('blocks execution when monthly budget would be exceeded', function (): void {
            $this->service->updateBudgetConfig($this->tenantId, dailyLimit: null, monthlyLimit: 100.0);
            $this->service->recordUsage($this->tenantId, 99.0);

            $result = $this->service->canExecuteRun($this->tenantId, 5.0);

            expect($result['allowed'])->toBeFalse()
                ->and($result['reason'])->toBe('monthly_budget_exceeded')
                ->and($result['usage_ratio'])->toBeGreaterThan(1.0);
        });
    });

    describe('recordUsage()', function (): void {
        it('increments daily usage counter', function (): void {
            $this->service->recordUsage($this->tenantId, 1.5);
            $this->service->recordUsage($this->tenantId, 2.5);

            $dailyUsage = $this->service->getDailyUsageDollars($this->tenantId);

            expect($dailyUsage)->toBe(4.0);
        });

        it('increments monthly usage counter', function (): void {
            $this->service->recordUsage($this->tenantId, 10.0);
            $this->service->recordUsage($this->tenantId, 5.0);

            $monthlyUsage = $this->service->getMonthlyUsageDollars($this->tenantId);

            expect($monthlyUsage)->toBe(15.0);
        });

        it('dispatches warning event when threshold exceeds 80%', function (): void {
            Event::fake();

            $this->service->updateBudgetConfig($this->tenantId, dailyLimit: 10.0, monthlyLimit: null);
            $this->service->recordUsage($this->tenantId, 8.5);

            Event::assertDispatched(AiBudgetThresholdExceeded::class, fn ($event): bool => $event->tenantId === $this->tenantId
                && $event->period === 'daily'
                && $event->level === 'warning'
                && $event->ratio > 0.8);
        });

        it('dispatches critical event when threshold exceeds 100%', function (): void {
            Event::fake();

            $this->service->updateBudgetConfig($this->tenantId, dailyLimit: 10.0, monthlyLimit: null);
            $this->service->recordUsage($this->tenantId, 10.5);

            Event::assertDispatched(AiBudgetThresholdExceeded::class, fn ($event): bool => $event->tenantId === $this->tenantId
                && $event->period === 'daily'
                && $event->level === 'critical'
                && $event->ratio > 1.0);
        });

        it('auto-disables tenant budget when critical threshold is exceeded', function (): void {
            $this->service->updateBudgetConfig($this->tenantId, dailyLimit: 10.0, monthlyLimit: null);
            $this->service->recordUsage($this->tenantId, 10.5);

            $result = $this->service->canExecuteRun($this->tenantId, 0.1);

            expect($result['allowed'])->toBeFalse()
                ->and($result['reason'])->toBe('budget_auto_disabled');
        });
    });

    describe('getUsageStats()', function (): void {
        it('returns accurate usage statistics', function (): void {
            $this->service->updateBudgetConfig($this->tenantId, dailyLimit: 50.0, monthlyLimit: 500.0);
            $this->service->recordUsage($this->tenantId, 25.0);

            $stats = $this->service->getUsageStats($this->tenantId);

            expect($stats['daily_usage'])->toBe(25.0)
                ->and($stats['daily_limit'])->toBe(50.0)
                ->and($stats['daily_ratio'])->toBe(0.5)
                ->and($stats['monthly_usage'])->toBe(25.0)
                ->and($stats['monthly_limit'])->toBe(500.0)
                ->and($stats['monthly_ratio'])->toBe(0.05);
        });
    });

    describe('updateBudgetConfig()', function (): void {
        it('stores and retrieves budget configuration', function (): void {
            $this->service->updateBudgetConfig($this->tenantId, dailyLimit: 20.0, monthlyLimit: 300.0);

            $result = $this->service->canExecuteRun($this->tenantId, 1.0);

            expect($result['allowed'])->toBeTrue();

            // Verify limits are enforced
            $this->service->recordUsage($this->tenantId, 20.5);
            $blocked = $this->service->canExecuteRun($this->tenantId, 1.0);

            expect($blocked['allowed'])->toBeFalse();
        });
    });
});
