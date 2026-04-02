<?php

declare(strict_types=1);

namespace Domain\Chat\Jobs;

use Domain\Chat\Services\ChatGatewayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Sincroniza estado de leitura no gateway de forma assíncrona.
 *
 * Evita bloquear o ciclo de resposta HTTP do endpoint de marcação de leitura,
 * delegando a chamada externa para a fila.
 */
final class SyncReadReceiptJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 20;

    public int $tries = 2;

    /**
     * @param  string  $token  Token da instância no gateway.
     * @param  string  $number  Número do contato normalizado (somente dígitos).
     */
    public function __construct(
        private readonly string $token,
        private readonly string $number,
    ) {
        $this->onQueue('chat');
    }

    /**
     * Processa a sincronização de leitura no provedor externo.
     */
    public function handle(ChatGatewayService $gateway): void
    {
        $gateway->markAsRead($this->token, [
            'number' => $this->number,
            'read' => true,
        ]);
    }

    /**
     * Trata falha permanente do job sem impactar a UX de chat.
     */
    public function failed(\Throwable $exception): void
    {
        Log::debug('[SyncReadReceiptJob] markAsRead gateway sync failed', [
            'error' => $exception->getMessage(),
        ]);
    }
}
