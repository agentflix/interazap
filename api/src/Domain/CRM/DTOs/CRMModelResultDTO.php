<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Illuminate\Database\Eloquent\Model;

/**
 * DTO for CRM model result.
 *
 * @readonly
 *
 * @template TModel of Model
 */
final readonly class CRMModelResultDTO
{
    /**
     * @param  TModel  $model
     */
    public function __construct(public Model $model) {}
}
