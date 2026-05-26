<?php

declare(strict_types=1);

namespace Domain\Billing\DTOs;

use Illuminate\Foundation\Http\FormRequest;
use SensitiveParameter;

/**
 * DTO para troca de plano de assinatura.
 *
 * Carrega o ID do plano de destino, a senha atual do usuário (quando exigida)
 * e o flag de bypass usado em trocas internas (ex: upgrade de trial).
 *
 * @readonly
 */
final readonly class BillingChangePlanDTO
{
    public function __construct(
        public string $planId,
        #[SensitiveParameter]
        public ?string $currentPassword,
        public bool $bypassPassword = false,
    ) {}

    /** Cria o DTO a partir de um FormRequest validado. */
    public static function fromRequest(FormRequest $request): self
    {
        $payload = $request->validated();

        return self::fromArray($payload);
    }

    /**
     * Cria o DTO a partir de um array de dados.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            planId: (string) $payload['plan_id'],
            currentPassword: isset($payload['current_password']) ? (string) $payload['current_password'] : null,
            bypassPassword: (bool) ($payload['bypass_password'] ?? false),
        );
    }

    /**
     * Converte o DTO para array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'plan_id' => $this->planId,
        ];
    }
}
