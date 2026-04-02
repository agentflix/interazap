<?php

declare(strict_types=1);

use Domain\Shared\Services\HealthCheckService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

describe('HealthCheckService', function (): void {
    beforeEach(function (): void {
        $this->service = new HealthCheckService;
    });

    describe('check()', function (): void {
        it('returns healthy status when all services are ok', function (): void {
            // Mock services
            DB::shouldReceive('connection->getPdo')->once()->andReturn(true);
            DB::shouldReceive('select')->with('SELECT 1')->once()->andReturn([]);

            Cache::shouldReceive('store')->with('redis')->andReturnSelf();
            Cache::shouldReceive('put')->andReturn(true);
            Cache::shouldReceive('get')->andReturn('ok');
            Cache::shouldReceive('forget')->andReturn(true);

            Queue::shouldReceive('size')->once()->andReturn(0);

            $result = $this->service->check();

            expect($result)
                ->toHaveKey('status', 'healthy')
                ->toHaveKey('timestamp')
                ->toHaveKey('services');

            expect($result['services'])
                ->toHaveKeys(['database', 'redis', 'queue']);
        });

        it('returns degraded status when database is down', function (): void {
            // Mock failing database
            DB::shouldReceive('connection->getPdo')
                ->once()
                ->andThrow(new Exception('Connection refused'));

            // Mock healthy redis
            Cache::shouldReceive('store')->with('redis')->andReturnSelf();
            Cache::shouldReceive('put')->andReturn(true);
            Cache::shouldReceive('get')->andReturn('ok');
            Cache::shouldReceive('forget')->andReturn(true);

            // Mock healthy queue
            Queue::shouldReceive('size')->once()->andReturn(0);

            $result = $this->service->check();

            expect($result['status'])->toBe('degraded');
            expect($result['services']['database']['status'])->toBe('unhealthy');
            expect($result['services']['redis']['status'])->toBe('healthy');
            expect($result['services']['queue']['status'])->toBe('healthy');
        });

        it('returns degraded status when redis is down', function (): void {
            // Mock healthy database
            DB::shouldReceive('connection->getPdo')->once()->andReturn(true);
            DB::shouldReceive('select')->with('SELECT 1')->once()->andReturn([]);

            // Mock failing redis
            Cache::shouldReceive('store')
                ->with('redis')
                ->andThrow(new Exception('Redis connection failed'));

            // Mock healthy queue
            Queue::shouldReceive('size')->once()->andReturn(0);

            $result = $this->service->check();

            expect($result['status'])->toBe('degraded');
            expect($result['services']['database']['status'])->toBe('healthy');
            expect($result['services']['redis']['status'])->toBe('unhealthy');
        });

        it('returns unhealthy status when all services are down', function (): void {
            // Mock failing database
            DB::shouldReceive('connection->getPdo')
                ->once()
                ->andThrow(new Exception('Connection refused'));

            // Mock failing redis
            Cache::shouldReceive('store')
                ->with('redis')
                ->andThrow(new Exception('Redis connection failed'));

            // Mock failing queue
            Queue::shouldReceive('size')
                ->once()
                ->andThrow(new Exception('Queue connection failed'));

            $result = $this->service->check();

            expect($result['status'])->toBe('unhealthy');
        });

        it('includes latency in healthy service responses', function (): void {
            DB::shouldReceive('connection->getPdo')->once()->andReturn(true);
            DB::shouldReceive('select')->with('SELECT 1')->once()->andReturn([]);

            Cache::shouldReceive('store')->with('redis')->andReturnSelf();
            Cache::shouldReceive('put')->andReturn(true);
            Cache::shouldReceive('get')->andReturn('ok');
            Cache::shouldReceive('forget')->andReturn(true);

            Queue::shouldReceive('size')->once()->andReturn(5);

            $result = $this->service->check();

            expect($result['services']['database'])->toHaveKey('latency_ms');
            expect($result['services']['redis'])->toHaveKey('latency_ms');
            expect($result['services']['queue'])->toHaveKey('latency_ms');
            expect($result['services']['queue'])->toHaveKey('queue_size', 5);
        });
    });

    describe('checkDatabase()', function (): void {
        it('returns healthy with latency when connection succeeds', function (): void {
            DB::shouldReceive('connection->getPdo')->once()->andReturn(true);
            DB::shouldReceive('select')->with('SELECT 1')->once()->andReturn([]);

            $result = $this->service->checkDatabase();

            expect($result['status'])->toBe('healthy');
            expect($result)->toHaveKey('latency_ms');
            expect($result['latency_ms'])->toBeFloat();
        });

        it('returns unhealthy with message when connection fails', function (): void {
            DB::shouldReceive('connection->getPdo')
                ->once()
                ->andThrow(new Exception('SQLSTATE connection refused'));

            $result = $this->service->checkDatabase();

            expect($result['status'])->toBe('unhealthy');
            expect($result)->toHaveKey('message');
            expect($result['message'])->toContain('SQLSTATE');
        });
    });

    describe('checkRedis()', function (): void {
        it('returns healthy with latency when redis is accessible', function (): void {
            Cache::shouldReceive('store')->with('redis')->andReturnSelf();
            Cache::shouldReceive('put')->andReturn(true);
            Cache::shouldReceive('get')->andReturn('ok');
            Cache::shouldReceive('forget')->andReturn(true);

            $result = $this->service->checkRedis();

            expect($result['status'])->toBe('healthy');
            expect($result)->toHaveKey('latency_ms');
        });

        it('returns unhealthy when redis verification fails', function (): void {
            Cache::shouldReceive('store')->with('redis')->andReturnSelf();
            Cache::shouldReceive('put')->andReturn(true);
            Cache::shouldReceive('get')->andReturn('wrong_value');
            Cache::shouldReceive('forget')->andReturn(true);

            $result = $this->service->checkRedis();

            expect($result['status'])->toBe('unhealthy');
            expect($result['message'])->toContain('verification failed');
        });
    });

    describe('checkQueue()', function (): void {
        it('returns healthy with queue size', function (): void {
            Queue::shouldReceive('size')->once()->andReturn(10);

            $result = $this->service->checkQueue();

            expect($result['status'])->toBe('healthy');
            expect($result['queue_size'])->toBe(10);
        });
    });
});
