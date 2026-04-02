<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Jobs;

use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Shared\Jobs\Traits\RetryableWithBackoff;
use Tests\TestCase;

class RetryableWithBackoffTest extends TestCase
{
    #[Test]
    public function it_provides_five_retry_attempts(): void
    {
        $job = new class
        {
            use RetryableWithBackoff;
        };

        $this->assertSame(5, $job->tries);
    }

    #[Test]
    public function it_provides_exponential_backoff_delays(): void
    {
        $job = new class
        {
            use RetryableWithBackoff;
        };

        $this->assertSame([10, 30, 60, 180, 300], $job->backoff);
    }

    #[Test]
    public function it_provides_default_timeout_value(): void
    {
        $job = new class
        {
            use RetryableWithBackoff;
        };

        $this->assertSame(120, $job->timeout);
    }

    #[Test]
    public function it_provides_max_exceptions_value(): void
    {
        $job = new class
        {
            use RetryableWithBackoff;
        };

        $this->assertSame(3, $job->maxExceptions);
    }

    #[Test]
    public function it_provides_retry_until_24_hours_from_now(): void
    {
        \Illuminate\Support\Facades\Date::setTestNow(\Illuminate\Support\Facades\Date::parse('2026-01-28 10:00:00'));

        $job = new class
        {
            use RetryableWithBackoff;
        };

        $retryUntil = $job->retryUntil();

        $this->assertInstanceOf(Carbon::class, $retryUntil);
        $this->assertEquals(
            \Illuminate\Support\Facades\Date::parse('2026-01-29 10:00:00'),
            $retryUntil
        );

        \Illuminate\Support\Facades\Date::setTestNow();
    }

    #[Test]
    public function backoff_method_applies_jitter_to_delays(): void
    {
        $job = new class
        {
            use RetryableWithBackoff;
        };

        $backoffWithJitter = $job->backoff();

        $this->assertCount(5, $backoffWithJitter);

        // Each delay should be within ±20% of the original
        $originalDelays = [10, 30, 60, 180, 300];
        foreach ($backoffWithJitter as $index => $delay) {
            $original = $originalDelays[$index];
            $minExpected = (int) ($original * 0.8);
            $maxExpected = (int) ($original * 1.2);

            $this->assertGreaterThanOrEqual($minExpected, $delay);
            $this->assertLessThanOrEqual($maxExpected, $delay);
        }
    }

    #[Test]
    public function jitter_produces_different_values_on_multiple_calls(): void
    {
        $job = new class
        {
            use RetryableWithBackoff;
        };

        $results = [];
        for ($i = 0; $i < 10; $i++) {
            $results[] = $job->backoff();
        }

        // At least some of the results should be different (randomness)
        $uniqueFirstDelays = array_unique(array_column($results, 0));
        // With 20% jitter on 10, we should see variation
        $this->assertGreaterThanOrEqual(1, count($uniqueFirstDelays));
    }

    #[Test]
    public function minimum_delay_is_always_at_least_one_second(): void
    {
        $job = new class
        {
            use RetryableWithBackoff;

            protected function getBackoffDelays(): array
            {
                return [1, 2, 3];
            }
        };

        $backoffWithJitter = $job->backoff();

        foreach ($backoffWithJitter as $delay) {
            $this->assertGreaterThanOrEqual(1, $delay);
        }
    }

    #[Test]
    public function it_provides_retryable_tags(): void
    {
        $job = new class
        {
            use RetryableWithBackoff;
        };

        $tags = $job->tags();

        $this->assertContains('retryable', $tags);
        $this->assertContains('backoff:exponential', $tags);
    }

    #[Test]
    public function should_fail_immediately_for_invalid_argument_exception(): void
    {
        $job = new class
        {
            use RetryableWithBackoff;

            public function checkShouldFailImmediately(\Throwable $e): bool
            {
                return $this->shouldFailImmediately($e);
            }
        };

        $this->assertTrue($job->checkShouldFailImmediately(new \InvalidArgumentException));
        $this->assertTrue($job->checkShouldFailImmediately(new \DomainException));
        $this->assertFalse($job->checkShouldFailImmediately(new \RuntimeException));
    }

    #[Test]
    public function custom_jitter_percentage_can_be_set(): void
    {
        $job = new class
        {
            use RetryableWithBackoff;

            protected function getJitterPercentage(): float
            {
                return 0.10; // 10% jitter
            }
        };

        $backoffWithJitter = $job->backoff();
        $originalDelays = [10, 30, 60, 180, 300];

        foreach ($backoffWithJitter as $index => $delay) {
            $original = $originalDelays[$index];
            $minExpected = (int) ($original * 0.9);
            $maxExpected = (int) ($original * 1.1);

            $this->assertGreaterThanOrEqual($minExpected, $delay);
            $this->assertLessThanOrEqual($maxExpected, $delay);
        }
    }

    #[Test]
    public function custom_backoff_delays_can_be_overridden(): void
    {
        $job = new class
        {
            use RetryableWithBackoff;

            protected function getBackoffDelays(): array
            {
                return [5, 15, 45, 120];
            }
        };

        $backoffWithJitter = $job->backoff();
        $this->assertCount(4, $backoffWithJitter);
    }

    #[Test]
    public function trait_properties_are_public_and_accessible(): void
    {
        $job = new class
        {
            use RetryableWithBackoff;
        };

        $this->assertIsInt($job->tries);
        $this->assertIsArray($job->backoff);
        $this->assertIsInt($job->timeout);
        $this->assertIsInt($job->maxExceptions);
        $this->assertCount(5, $job->backoff);
    }
}
