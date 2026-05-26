<?php

declare(strict_types=1);

namespace Domain\Chat\Contracts;

use Domain\Chat\DTOs\WebhookEventDTO;

/**
 * Port para normalização de webhooks de diferentes provedores.
 *
 * Cada provedor (Uazapi, Z-API, Meta, etc.) implementa este contrato para
 * converter seu payload bruto no formato canônico {@see WebhookEventDTO}.
 */
interface WebhookNormalizerPort
{
    /** Retorna o nome do provedor que este normalizador suporta. */
    public function getProviderName(): string;

    /**
     * Converte o payload bruto do provedor no DTO canônico de evento.
     *
     * @param  array<string, mixed>  $rawPayload  Payload original recebido no webhook.
     */
    public function normalize(array $rawPayload): WebhookEventDTO;

    /**
     * Verifica se este normalizador é capaz de processar o payload informado.
     *
     * @param  array<string, mixed>  $rawPayload  Payload original recebido no webhook.
     */
    public function supports(array $rawPayload): bool;
}
