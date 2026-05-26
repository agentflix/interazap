<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO que representa um Master Prompt — template base de instruções para IA.
 *
 * Utilizado na gestão de prompts globais que servem como base para todos
 * os agentes do tenant, definindo comportamento padrão, tom de voz e
 * diretrizes gerais de resposta.
 *
 * @readonly
 */
final readonly class MasterPromptDTO
{
    public function __construct(
        public string $name,
        public string $content,
        public ?bool $isActive = null,
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
            name: (string) ($data['name'] ?? ''),
            content: (string) ($data['content'] ?? ''),
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : null,
        );
    }

    /**
     * Converte para array compatível com o model Eloquent.
     *
     * Exclui is_active quando null (campo opcional na atualização).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'name' => $this->name,
            'content' => $this->content,
        ];

        if ($this->isActive !== null) {
            $data['is_active'] = $this->isActive;
        }

        return $data;
    }
}
