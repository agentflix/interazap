<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

use Carbon\Carbon;

/**
 * DTO de resposta de conexão de instância.
 *
 * Representa o resultado do processo de pareamento via QR Code ou código de par.
 *
 * @readonly
 */
final readonly class ConnectionResultDTO
{
    /**
     * @param  string  $mode  Modo de conexão ('qr' ou 'pair').
     * @param  string|null  $qrCode  String do QR code (base64 ou URL) ou null.
     * @param  string|null  $pairCode  Código de pareamento de 8 dígitos ou null.
     * @param  Carbon  $expiresAt  Data/hora de expiração do código.
     * @param  string  $provider  Nome do provedor (ex.: uazapi).
     * @param  string|null  $phone  Número de telefone para modo pair.
     */
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
