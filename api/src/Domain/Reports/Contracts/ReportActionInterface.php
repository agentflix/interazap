<?php

declare(strict_types=1);

namespace Domain\Reports\Contracts;

use Domain\Reports\DTOs\ReportsFilterDTO;

/**
 * Contract for report actions resolved by the report registry.
 */
interface ReportActionInterface
{
    /**
     * Execute a report action.
     *
     * @return array<string, mixed>
     */
    public function execute(ReportsFilterDTO $dto): array;
}
