<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * DTO for CRM paginator result.
 *
 * @readonly
 */
final readonly class CRMPaginatorResultDTO
{
    public function __construct(public LengthAwarePaginator $paginator) {}
}
