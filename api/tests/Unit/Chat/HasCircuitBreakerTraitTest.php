<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Shared\Infrastructure\WhatsApp\Concerns\HasCircuitBreaker;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class HasCircuitBreakerTraitTest extends TestCase
{
    private HasCircuitBreakerHarness $harness;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->harness = new HasCircuitBreakerHarness;
    }

    public function test_executes_operation_when_circuit_is_closed(): void
    {
        $result = $this->harness->run('provider-a', fn (): string => 'ok');

        $this->assertSame('ok', $result);
        $this->assertNull(Cache::get('circuit:provider-a:failures'));
    }

    public function test_throws_when_circuit_is_open_and_timeout_not_elapsed(): void
    {
        $this->harness->setState('provider-b', 'open');
        Cache::put('circuit:provider-b:opened_at', now());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circuit breaker aberto para: provider-b');

        $this->harness->run('provider-b', fn (): string => 'nope');
    }

    public function test_transitions_from_open_to_half_open_and_then_closes(): void
    {
        $this->harness->setState('provider-c', 'open');

        $this->assertSame('ok-1', $this->harness->run('provider-c', fn (): string => 'ok-1'));
        $this->assertSame('half-open', $this->harness->state('provider-c'));

        $this->assertSame('ok-2', $this->harness->run('provider-c', fn (): string => 'ok-2'));
        $this->assertSame('closed', $this->harness->state('provider-c'));
        $this->assertNull(Cache::get('circuit:provider-c:successes'));
    }

    public function test_opens_circuit_after_threshold_failures_in_closed_state(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $this->harness->run('provider-d', static function (): void {
                    throw new RuntimeException('fail');
                });
            } catch (RuntimeException) {
                // expected
            }
        }

        $this->assertSame('open', $this->harness->state('provider-d'));
        $this->assertNotNull(Cache::get('circuit:provider-d:opened_at'));
    }

    public function test_reopens_circuit_when_half_open_operation_fails(): void
    {
        $this->harness->setState('provider-e', 'half-open');

        try {
            $this->harness->run('provider-e', static function (): void {
                throw new RuntimeException('half-open-fail');
            });
            $this->fail('Expected exception was not thrown.');
        } catch (RuntimeException $exception) {
            $this->assertSame('half-open-fail', $exception->getMessage());
        }

        $this->assertSame('open', $this->harness->state('provider-e'));
    }
}

final class HasCircuitBreakerHarness
{
    use HasCircuitBreaker;

    public function run(string $key, callable $operation): mixed
    {
        return $this->withCircuitBreaker($key, $operation);
    }

    public function setState(string $key, string $state): void
    {
        $this->setCircuitState($key, $state);
    }

    public function state(string $key): string
    {
        return $this->getCircuitState($key);
    }
}
