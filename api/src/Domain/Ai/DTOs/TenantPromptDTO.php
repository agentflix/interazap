<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO que representa um prompt personalizado por tenant.
 *
 * Utilizado quando um inquilino deseja sobrescrever ou complementar
 * as instruções padrão da plataforma com regras e comportamentos
 * específicos do seu negócio.
 *
 * @readonly
 */
final readonly class TenantPromptDTO
{
    public function __construct(
        public string $content,
    ) {}

    /**
     * Cria o DTO a partir de um form request validado.
     *
     * @param  FormRequest  $request  Requisição validada.
     */
    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * Cria o DTO a partir de um array de dados.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            content: (string) ($data['content'] ?? ''),
        );
    }

    /**
     * Converte para array compatível com o model Eloquent.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'content' => $this->content,
        ];
    }
}
