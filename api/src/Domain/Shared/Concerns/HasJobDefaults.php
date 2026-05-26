<?php

declare(strict_types=1);

namespace Domain\Shared\Concerns;

use Carbon\Carbon;

/**
 * Trait que fornece configurações padrão para jobs em fila.
 *
 * Centraliza o comportamento de retentativa, timeout e estratégia de backoff
 * para todos os jobs da aplicação, garantindo consistência entre domínios.
 */
trait HasJobDefaults
{
    /**
     * Número de tentativas permitidas para o job.
     */
    public int $tries = 3;

    /**
     * Intervalo de espera em segundos entre tentativas (backoff exponencial).
     *
     * @var array<int, int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * Tempo máximo em segundos que o job pode executar antes de expirar.
     */
    public int $timeout = 120;

    /**
     * Número máximo de exceções não tratadas antes de marcar o job como falho.
     */
    public int $maxExceptions = 2;

    /**
     * Define o limite de tempo absoluto até o qual o job pode ser retentado.
     *
     * @return Carbon Data/hora limite para novas tentativas.
     */
    public function retryUntil(): Carbon
    {
        return now()->addHours(6);
    }
}
