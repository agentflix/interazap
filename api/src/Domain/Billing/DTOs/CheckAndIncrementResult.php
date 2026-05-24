<?php

declare(strict_types=1);

namespace Domain\Billing\DTOs;

use Domain\Billing\Enums\OverageMode;

final readonly class CheckAndIncrementResult
{
    public function __construct(
        public bool $allowed,
        public int $current,
        public int $limit,
        public OverageMode $mode,
        public bool $isOverage,
        public string $cycleStart = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'current' => $this->current,
            'limit' => $this->limit,
            'mode' => $this->mode->value,
            'is_overage' => $this->isOverage,
            'cycle_start' => $this->cycleStart,
        ];
    }
}
