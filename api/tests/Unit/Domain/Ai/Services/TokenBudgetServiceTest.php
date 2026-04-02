<?php

declare(strict_types=1);

use Domain\Ai\Events\AiBudgetThresholdExceeded;
use Domain\Ai\Services\TokenBudgetService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Cache::flush();
    $tenant = PlatformTenant::factory()->create();
    $this->tenantId = (string) $tenant->id;
    $this->service = new TokenBudgetService;
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
