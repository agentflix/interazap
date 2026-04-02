<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use Carbon\Carbon;
use Domain\Shared\Concerns\HasJobDefaults;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class HasJobDefaultsTest extends TestCase
{
    #[Test]
    public function it_provides_default_tries_value(): void
    {
        $job = new class
        {
            use HasJobDefaults;
        };

        $this->assertSame(3, $job->tries);
    }

    #[Test]
    public function it_provides_default_backoff_array(): void
    {
        $job = new class
        {
            use HasJobDefaults;
        };

        $this->assertSame([10, 60, 300], $job->backoff);
    }

    #[Test]
    public function it_provides_default_timeout_value(): void
    {
        $job = new class
        {
            use HasJobDefaults;
        };

        $this->assertSame(120, $job->timeout);
    }

    #[Test]
    public function it_provides_default_max_exceptions_value(): void
    {
        $job = new class
        {
            use HasJobDefaults;
        };

        $this->assertSame(2, $job->maxExceptions);
    }

    #[Test]
    public function it_provides_retry_until_six_hours_from_now(): void
    {
        \Illuminate\Support\Facades\Date::setTestNow(\Illuminate\Support\Facades\Date::parse('2026-01-28 10:00:00'));

        $job = new class
        {
            use HasJobDefaults;
        };

        $retryUntil = $job->retryUntil();

        $this->assertInstanceOf(Carbon::class, $retryUntil);
        $this->assertEquals(
            \Illuminate\Support\Facades\Date::parse('2026-01-28 16:00:00'),
            $retryUntil
        );

        \Illuminate\Support\Facades\Date::setTestNow();
    }

    #[Test]
    public function trait_properties_are_public_and_accessible(): void
    {
        $job = new class
        {
            use HasJobDefaults;
        };

        // Verify all properties exist and are accessible
        $this->assertIsInt($job->tries);
        $this->assertIsArray($job->backoff);
        $this->assertIsInt($job->timeout);
        $this->assertIsInt($job->maxExceptions);
        $this->assertCount(3, $job->backoff);
    }
}
