<?php

declare(strict_types=1);

namespace Domain\Reports\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Resource for Report serialization with standard envelope.
 */
final class ReportResource extends JsonResource
{
    private string $startDate;

    private string $endDate;

    /**
     * @param  array<string, mixed>  $resource
     */
    public function __construct(array $resource, string $startDate, string $endDate)
    {
        parent::__construct($resource);
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * Serialize the report data in standard format.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $data */
        $data = $this->resource;

        $total = 0;
        foreach ($data as $value) {
            if (is_array($value) && array_is_list($value)) {
                $total += count($value);
            }
        }

        return [
            'data' => $data,
            'meta' => [
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'total' => $total,
                'generated_at' => Carbon::now()->toIso8601String(),
            ],
        ];
    }
}
