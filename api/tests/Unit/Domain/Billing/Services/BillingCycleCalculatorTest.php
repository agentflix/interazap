<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Billing\Services;

use Carbon\CarbonImmutable;
use Domain\Billing\Services\BillingCycleCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingCycleCalculatorTest extends TestCase
{
    private BillingCycleCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new BillingCycleCalculator;
    }

    #[Test]
    public function it_calculates_cycle_when_reference_is_after_anchor(): void
    {
        $result = $this->calculator->calculate(15, CarbonImmutable::parse('2026-06-20'));
        $this->assertSame('2026-06-15', $result['cycle_start']->toDateString());
        $this->assertSame('2026-07-14', $result['cycle_end']->toDateString());
    }

    #[Test]
    public function it_calculates_cycle_when_reference_is_before_anchor(): void
    {
        $result = $this->calculator->calculate(15, CarbonImmutable::parse('2026-06-10'));
        $this->assertSame('2026-05-15', $result['cycle_start']->toDateString());
        $this->assertSame('2026-06-14', $result['cycle_end']->toDateString());
    }

    #[Test]
    public function it_calculates_cycle_on_anchor_day_itself(): void
    {
        $result = $this->calculator->calculate(15, CarbonImmutable::parse('2026-06-15'));
        $this->assertSame('2026-06-15', $result['cycle_start']->toDateString());
        $this->assertSame('2026-07-14', $result['cycle_end']->toDateString());
    }

    #[Test]
    public function it_caps_anchor_at_28(): void
    {
        $result = $this->calculator->calculate(31, CarbonImmutable::parse('2026-03-29'));
        $this->assertSame('2026-03-28', $result['cycle_start']->toDateString());
    }

    #[Test]
    public function it_handles_year_boundary(): void
    {
        $result = $this->calculator->calculate(15, CarbonImmutable::parse('2026-01-10'));
        $this->assertSame('2025-12-15', $result['cycle_start']->toDateString());
        $this->assertSame('2026-01-14', $result['cycle_end']->toDateString());
    }

    #[Test]
    public function it_handles_anchor_1_on_first_of_month(): void
    {
        $result = $this->calculator->calculate(1, CarbonImmutable::parse('2026-01-01'));
        $this->assertSame('2026-01-01', $result['cycle_start']->toDateString());
        $this->assertSame('2026-01-31', $result['cycle_end']->toDateString());
    }

    #[Test]
    public function test_calculate_with_cycle_days_returns_n_days_window(): void
    {
        $result = $this->calculator->calculate(
            anchorDay: 1,
            reference: CarbonImmutable::parse('2026-01-15'),
            cycleDays: 7
        );

        $this->assertSame('2026-01-15', $result['cycle_start']->toDateString());
        $this->assertSame('2026-01-21', $result['cycle_end']->toDateString());
    }
}
