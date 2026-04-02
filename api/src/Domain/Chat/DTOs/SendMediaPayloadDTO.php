<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Domain\Chat\Enums\MediaType;

/**
 * DTO for media send payload.
 *
 * @readonly
 */
final readonly class SendMediaPayloadDTO
{
    public function __construct(
        public string $phone,
        public MediaType $type,
        public string $media,
        public ?string $caption = null,
        public ?string $fileName = null,
        public ?string $extension = null,
        public bool $isBase64 = false,
        public ?string $quotedMessageId = null,
    ) {}
}
