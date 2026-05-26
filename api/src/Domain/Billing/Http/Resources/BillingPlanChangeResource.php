<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource para serialização da resposta de preview e execução de troca de plano.
 *
 * Encapsula diretamente o array retornado pela action (upgrade/downgrade + efeitos financeiros).
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
