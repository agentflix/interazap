<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Carbon\Carbon;
use Domain\Chat\Enums\WebhookEventType;

/**
 * DTO for normalized webhook event.
 *
 * @readonly
 */
final readonly class WebhookEventDTO
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public WebhookEventType $eventType,
        public string $providerMessageId,
        public string $phone,
        public ?string $senderName,
        public string $direction,
        public ?string $textContent,
        public ?string $mediaType,
        public ?string $mediaUrl,
        public Carbon $timestamp,
        public bool $isGroup,
        public ?string $status,
        public ?string $quotedMessageId,
        public array $rawPayload,
    ) {}
}
