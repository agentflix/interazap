<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO que representa um prompt vinculado a um plano de assinatura.
 *
 * Utilizado para definir instruções específicas que variam conforme o
 * plano do tenant (free, pro, enterprise), permitindo diferentes níveis
 * de capacidade e comportamento da IA por tier.
 *
 * @readonly
 */
final readonly class PlanPromptDTO
{
    public function __construct(
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
        return new self(
            content: (string) $request->validated('content'),
            isActive: $request->validated('is_active'),
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
            'content' => $this->content,
        ];

        if ($this->isActive !== null) {
            $data['is_active'] = $this->isActive;
        }

        return $data;
    }
}
