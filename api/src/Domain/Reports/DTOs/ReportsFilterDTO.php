<?php

declare(strict_types=1);

namespace Domain\Reports\DTOs;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * DTO for report filters.
 *
 * @readonly
 */
final readonly class ReportsFilterDTO
{
    public function __construct(
        public string $tenantId,
        public Carbon $startDate,
        public Carbon $endDate,
        public string $granularity = 'day',
        public ?string $userId = null,
        public ?string $funnelId = null,
        public ?string $stepId = null,
        public ?string $channel = null,
        public ?string $instanceId = null,
        public ?string $reasonLossId = null,
        public ?string $productId = null,
        public string $exportFormat = 'json',
    ) {}

    /**
     * Create DTO from validated form request.
     */
    public static function fromRequest(FormRequest $request, string $tenantId): self
    {
        $data = $request->validated();

        return new self(
            tenantId: $tenantId,
            startDate: Carbon::parse($data['start_date'])->startOfDay(),
            endDate: Carbon::parse($data['end_date'])->endOfDay(),
            granularity: $data['granularity'] ?? 'day',
            userId: $data['user_id'] ?? null,
            funnelId: $data['funnel_id'] ?? null,
            stepId: $data['step_id'] ?? null,
            channel: $data['channel'] ?? null,
            instanceId: $data['instance_id'] ?? null,
            reasonLossId: $data['reason_loss_id'] ?? null,
            productId: $data['product_id'] ?? null,
            exportFormat: $data['export_format'] ?? 'json',
        );
    }

    /**
     * Cria DTO a partir de um array.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            tenantId: (string) $data['tenant_id'],
            startDate: Carbon::parse($data['start_date'])->startOfDay(),
            endDate: Carbon::parse($data['end_date'])->endOfDay(),
            granularity: (string) ($data['granularity'] ?? 'day'),
            userId: isset($data['user_id']) ? (string) $data['user_id'] : null,
            funnelId: isset($data['funnel_id']) ? (string) $data['funnel_id'] : null,
            stepId: isset($data['step_id']) ? (string) $data['step_id'] : null,
            channel: isset($data['channel']) ? (string) $data['channel'] : null,
            instanceId: isset($data['instance_id']) ? (string) $data['instance_id'] : null,
            reasonLossId: isset($data['reason_loss_id']) ? (string) $data['reason_loss_id'] : null,
            productId: isset($data['product_id']) ? (string) $data['product_id'] : null,
            exportFormat: (string) ($data['export_format'] ?? 'json'),
        );
    }
}
