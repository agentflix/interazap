<?php

declare(strict_types=1);

namespace Domain\Gateway\Contracts;

use Domain\Gateway\DTOs\GatewayMessage;
use Domain\Gateway\DTOs\GatewayResponse;
use Domain\Gateway\Exceptions\GatewayTimeoutException;
use Domain\Gateway\Exceptions\ProviderException;

/**
 * Contrato para clientes de comunicação com o gateway NestJS via Redis Streams.
 *
 * Define duas modalidades: envio bloqueante (aguarda resposta) e
 * disparo sem espera (fire-and-forget).
 */
interface GatewayClientInterface
{
    /**
     * Envia uma mensagem e aguarda a resposta (bloqueante).
     *
     * @param  GatewayMessage  $message  Mensagem a ser enviada ao gateway
     * @param  int  $timeoutSeconds  Tempo máximo de espera pela resposta em segundos
     * @return GatewayResponse Resposta recebida do gateway
     *
     * @throws GatewayTimeoutException Quando o tempo limite expira antes da resposta
     * @throws ProviderException Quando o provider retorna um erro
     */
    public function send(GatewayMessage $message, int $timeoutSeconds = 180): GatewayResponse;

    /**
     * Envia uma mensagem sem aguardar resposta (fire-and-forget).
     *
     * @param  GatewayMessage  $message  Mensagem a ser despachada ao gateway
     * @return string O correlationId gerado para rastreamento posterior
     */
    public function dispatch(GatewayMessage $message): string;
}
