<?php

declare(strict_types=1);

namespace Domain\Ai\DTOs;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DTO for Plan Prompt creation and update.
 *
 * @readonly
 */
final readonly class PlanPromptDTO
{
    public function __construct(
        public string $content,
        public ?int $tokenLimitMonthly = null,
        public ?bool $allowOverage = null,
        public ?float $overagePricePer1k = null,
        public ?bool $isActive = null,
    ) {}

    /**
     * Create DTO from form request.
     */
    public static function fromRequest(FormRequest $request): self
    {
        return new self(
            content: (string) $request->validated('content'),
            tokenLimitMonthly: $request->validated('token_limit_monthly'),
            allowOverage: $request->validated('allow_overage'),
            overagePricePer1k: $request->validated('overage_price_per_1k'),
            isActive: $request->validated('is_active'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'content' => $this->content,
        ];

        if ($this->tokenLimitMonthly !== null) {
            $data['token_limit_monthly'] = $this->tokenLimitMonthly;
        }

        if ($this->allowOverage !== null) {
            $data['allow_overage'] = $this->allowOverage;
        }

        if ($this->overagePricePer1k !== null) {
            $data['overage_price_per_1k'] = $this->overagePricePer1k;
        }

        if ($this->isActive !== null) {
            $data['is_active'] = $this->isActive;
        }

        return $data;
    }
}
