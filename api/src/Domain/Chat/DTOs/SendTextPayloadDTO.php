<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

/**
 * DTO for text message send payload.
 *
 * @readonly
 */
final readonly class SendTextPayloadDTO
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $phone,
        public string $message,
        public ?string $quotedMessageId = null,
        public int $delayMs = 0,
        public array $metadata = [],
    ) {}
}
