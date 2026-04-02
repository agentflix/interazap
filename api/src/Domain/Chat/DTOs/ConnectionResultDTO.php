<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Carbon\Carbon;

/**
 * DTO for instance connection response.
 *
 * @readonly
 */
final readonly class ConnectionResultDTO
{
    public function __construct(
        public string $mode,
        public ?string $qrCode,
        public ?string $pairCode,
        public Carbon $expiresAt,
        public string $provider,
        public ?string $phone = null,
    ) {}

    /**
     * @return array{mode:string,qr_code:?string,pair_code:?string,expires_at:string,provider:string,phone:?string}
     */
    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
            'qr_code' => $this->qrCode,
            'pair_code' => $this->pairCode,
            'expires_at' => $this->expiresAt->toIso8601String(),
            'provider' => $this->provider,
            'phone' => $this->phone,
        ];
    }
}
