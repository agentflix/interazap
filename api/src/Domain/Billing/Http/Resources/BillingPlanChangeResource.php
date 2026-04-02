<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource for Plan Change preview/execution response.
 */
final class BillingPlanChangeResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = is_array($this->resource) ? $this->resource : [];

        return $data;
    }
}
