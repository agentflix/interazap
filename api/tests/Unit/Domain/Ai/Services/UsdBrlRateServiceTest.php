<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Services;

use Domain\Ai\Services\UsdBrlRateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class UsdBrlRateServiceTest extends TestCase
{
    public function test_returns_rate_from_api(): void
    {
        Cache::forget('ai:usd_brl_rate');

        Http::fake([
            'economia.awesomeapi.com.br/*' => Http::response([
                'USDBRL' => ['bid' => '5.8234'],
            ], 200),
        ]);

        $rate = app(UsdBrlRateService::class)->getRate();

        $this->assertSame(5.8234, $rate);
    }

    public function test_caches_rate_after_first_call(): void
    {
        Cache::forget('ai:usd_brl_rate');

        Http::fake([
            'economia.awesomeapi.com.br/*' => Http::response([
                'USDBRL' => ['bid' => '5.9000'],
            ], 200),
        ]);

        app(UsdBrlRateService::class)->getRate();
        app(UsdBrlRateService::class)->getRate(); // segunda chamada usa cache

        Http::assertSentCount(1);
    }

    public function test_falls_back_to_config_when_api_fails(): void
    {
        Cache::forget('ai:usd_brl_rate');
        config(['ai.usd_brl_rate' => 5.70]);

        Http::fake([
            'economia.awesomeapi.com.br/*' => Http::response([], 500),
        ]);

        $rate = app(UsdBrlRateService::class)->getRate();

        $this->assertSame(5.70, $rate);
    }

    public function test_falls_back_to_config_when_api_throws(): void
    {
        Cache::forget('ai:usd_brl_rate');
        config(['ai.usd_brl_rate' => 5.70]);

        Http::fake([
            'economia.awesomeapi.com.br/*' => fn () => throw new \RuntimeException('Connection refused'),
        ]);

        $rate = app(UsdBrlRateService::class)->getRate();

        $this->assertSame(5.70, $rate);
    }

    public function test_falls_back_when_bid_is_zero(): void
    {
        Cache::forget('ai:usd_brl_rate');
        config(['ai.usd_brl_rate' => 5.70]);

        Http::fake([
            'economia.awesomeapi.com.br/*' => Http::response([
                'USDBRL' => ['bid' => '0'],
            ], 200),
        ]);

        $rate = app(UsdBrlRateService::class)->getRate();

        $this->assertSame(5.70, $rate);
    }
}
