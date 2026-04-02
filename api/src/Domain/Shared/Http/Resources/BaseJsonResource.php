<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Resources;

use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Recurso base para serialização com helpers de data.
 */
abstract class BaseJsonResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->data($request);
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function data(Request $request): array;

    protected function iso(?DateTimeInterface $value): ?string
    {
        return $value?->format(DATE_ATOM);
    }
}
