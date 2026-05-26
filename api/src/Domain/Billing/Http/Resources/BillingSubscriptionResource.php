<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Resources;

use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource para serialização do resumo da assinatura atual do tenant.
 *
 * Encapsula o array com plano, uso de recursos e próxima fatura retornado pelo controller.
 */
final class BillingSubscriptionResource extends BaseJsonResource
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
