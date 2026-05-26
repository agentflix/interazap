<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

/**
 * DTO que representa um arquivo de configuração ou instrução anexado a um agente de IA.
 *
 * Utilizado para carregar arquivos de texto (JSON, TXT, YAML) que contêm
 * instruções adicionais, dados de contexto ou configurações específicas
 * que o agente deve considerar durante o processamento.
 *
 * @readonly
 */
final readonly class AiAgentFileDTO
{
    public function __construct(
        public string $slug,
        public ?string $content,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            slug: (string) ($data['slug'] ?? ''),
            content: isset($data['content']) ? (string) $data['content'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'content' => $this->content,
        ];
    }
}
