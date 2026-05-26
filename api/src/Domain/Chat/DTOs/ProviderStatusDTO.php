<?php

declare(strict_types=1);

namespace Domain\Chat\DTOs;

/**
 * DTO de status da instância do provedor.
 *
 * @readonly
 */
final readonly class ProviderStatusDTO
{
    /**
     * @param  bool  $connected  Indica se a instância está conectada.
     * @param  string|null  $phone  Número de telefone da instância.
     * @param  string|null  $name  Nome de perfil da instância.
     * @param  array<string, mixed>|null  $rawResponse  Resposta bruta do provedor.
     */
    public function __construct(
        public bool $connected,
        public ?string $phone = null,
        public ?string $name = null,
        public ?array $rawResponse = null,
    ) {}
}
