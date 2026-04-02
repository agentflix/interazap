<?php

declare(strict_types=1);

namespace Domain\Gateway\Contracts;

use Domain\Gateway\DTOs\GatewayMessage;
use Domain\Gateway\DTOs\GatewayResponse;
use Domain\Gateway\Exceptions\GatewayTimeoutException;
use Domain\Gateway\Exceptions\ProviderException;

interface GatewayClientInterface
{
    /**
     * Send a message and WAIT for response (blocking).
     *
     * @param  GatewayMessage  $message  The message to send
     * @param  int  $timeoutSeconds  Maximum time to wait for response
     * @return GatewayResponse The response from the gateway
     *
     * @throws GatewayTimeoutException If timeout expires before receiving response
     * @throws ProviderException If provider returns an error
     */
    public function send(GatewayMessage $message, int $timeoutSeconds = 180): GatewayResponse;

    /**
     * Send a message WITHOUT waiting for response (fire-and-forget).
     *
     * @param  GatewayMessage  $message  The message to dispatch
     * @return string The correlationId for tracking
     */
    public function dispatch(GatewayMessage $message): string;
}
