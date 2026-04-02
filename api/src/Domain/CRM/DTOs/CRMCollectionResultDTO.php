<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Illuminate\Support\Collection;

/**
 * DTO for CRM collection result.
 *
 * @readonly
 */
final readonly class CRMCollectionResultDTO
{
    public function __construct(public Collection $items) {}
}
