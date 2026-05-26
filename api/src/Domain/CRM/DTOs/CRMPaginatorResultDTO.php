<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * DTO para resultado paginado de listagens CRM.
 *
 * @readonly
 */
final readonly class CRMPaginatorResultDTO
{
    public function __construct(public LengthAwarePaginator $paginator) {}
}
