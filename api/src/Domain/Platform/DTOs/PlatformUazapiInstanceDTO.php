<?php

declare(strict_types=1);

namespace Domain\Platform\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO para criação de instância Uazapi.
 *
 * Transporta nome, nome de sistema e configuração JSONB ao domínio.
 *
 * @readonly
 */
final readonly class PlatformUazapiInstanceDTO
{
    /**
     * @param  array<string, mixed>  $config  Campos de configuração JSONB.
     */
    public function __construct(
        public string $name,
        public ?string $systemName = null,
        public array $config = [],
    ) {}

    /**
     * Cria o DTO a partir de um FormRequest validado.
     *
     * @param  FormRequest  $request  Requisição validada.
     * @return self DTO preenchido.
     */
    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * Cria o DTO a partir de um array de dados.
     *
     * @param  array<string, mixed>  $data  Dados da instância.
     * @return self DTO preenchido.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) ($data['name'] ?? ''),
            systemName: $data['system_name'] ?? null,
            config: (array) ($data['config'] ?? []),
        );
    }

    /**
     * Converte o DTO em array para envio ao gateway.
     *
     * @return array<string, mixed> Representação em array da instância.
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'system_name' => $this->systemName,
            'config' => $this->config,
        ];
    }
}
