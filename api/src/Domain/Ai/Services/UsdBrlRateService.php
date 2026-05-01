<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Busca a cotação USD → BRL via AwesomeAPI (economia.awesomeapi.com.br).
 * Resultado é cacheado por 24h. Em caso de falha, usa o fallback do config.
 */
final class UsdBrlRateService
{
    private const string CACHE_KEY = 'ai:usd_brl_rate';

    private const string API_URL = 'https://economia.awesomeapi.com.br/json/last/USD-BRL';

    private const int TIMEOUT_SECONDS = 3;

    private const int CACHE_TTL_SECONDS = 86400; // 24h

    public function getRate(): float
    {
        $rate = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function (): float {
            return $this->fetchFromApi() ?? $this->fallback();
        });

        return (float) $rate;
    }

    private function fetchFromApi(): ?float
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)->get(self::API_URL);

            if (! $response->successful()) {
                Log::warning('[UsdBrlRateService] API retornou status inesperado', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            $bid = (float) data_get($response->json(), 'USDBRL.bid', 0);

            if ($bid <= 0) {
                return null;
            }

            return round($bid, 4);
        } catch (\Throwable $e) {
            Log::warning('[UsdBrlRateService] Falha ao buscar cotação', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function fallback(): float
    {
        return (float) config('ai.usd_brl_rate', 5.70);
    }
}
