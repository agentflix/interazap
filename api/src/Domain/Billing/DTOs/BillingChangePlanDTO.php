<?php

declare(strict_types=1);

namespace Domain\Billing\DTOs;

use Illuminate\Foundation\Http\FormRequest;
use SensitiveParameter;

/**
 * DTO for subscription plan change.
 *
 * @readonly
 */
final readonly class BillingChangePlanDTO
{
    public function __construct(
        public string $planId,
        #[SensitiveParameter]
        public ?string $currentPassword,
    ) {}

    /**
     * Create DTO from form request.
     */
    public static function fromRequest(FormRequest $request): self
    {
        $payload = $request->validated();

        return self::fromArray($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            planId: (string) $payload['plan_id'],
            currentPassword: isset($payload['current_password']) ? (string) $payload['current_password'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'plan_id' => $this->planId,
        ];
    }
}
