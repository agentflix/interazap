<?php

declare(strict_types=1);

namespace Domain\Chat\Contracts;

use Domain\Chat\DTOs\WebhookEventDTO;

/**
 * Port para normalização de webhooks de diferentes provedores.
 */
interface WebhookNormalizerPort
{
    public function getProviderName(): string;

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function normalize(array $rawPayload): WebhookEventDTO;

    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function supports(array $rawPayload): bool;
}
