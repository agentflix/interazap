<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Carbon\Carbon;
use Domain\Chat\Enums\MessageDeliveryStatus;

/**
 * DTO for unified message send response.
 *
 * @readonly
 */
final readonly class ProviderMessageDTO
{
    /**
     * @param  array<string, mixed>|null  $rawResponse
     */
    public function __construct(
        public string $providerMessageId,
        public MessageDeliveryStatus $status,
        public Carbon $sentAt,
        public ?string $errorCode = null,
        public ?string $errorMessage = null,
        public ?array $rawResponse = null,
    ) {}

    /**
     * @param  array<string, mixed>|null  $rawResponse
     */
    /**
     * @param  array<string, mixed>|null  $rawResponse
     */
    public static function success(string $providerMessageId, Carbon $sentAt, ?array $rawResponse = null): self
    {
        return new self(
            providerMessageId: $providerMessageId,
            status: MessageDeliveryStatus::SENT,
            sentAt: $sentAt,
            rawResponse: $rawResponse,
        );
    }

    /**
     * @param  array<string, mixed>|null  $rawResponse
     */
    /**
     * @param  array<string, mixed>|null  $rawResponse
     */
    public static function failed(string $errorCode, string $errorMessage, ?array $rawResponse = null): self
    {
        return new self(
            providerMessageId: '',
            status: MessageDeliveryStatus::FAILED,
            sentAt: Carbon::now(),
            errorCode: $errorCode,
            errorMessage: $errorMessage,
            rawResponse: $rawResponse,
        );
    }

    /**
     * Check if message was sent successfully.
     */
    public function isSuccess(): bool
    {
        return $this->status !== MessageDeliveryStatus::FAILED;
    }
}
