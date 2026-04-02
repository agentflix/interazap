<?php

declare(strict_types=1);

namespace Domain\Shared\DTOs;

use Domain\Shared\Http\Requests\GlobalSearchRequest;

/**
 * DTO for global search input payload.
 *
 * @readonly
 */
final readonly class GlobalSearchDTO
{
    /**
     * @param  list<string>  $types
     */
    public function __construct(
        public string $tenantId,
        public string $query,
        public array $types,
        public int $perType,
    ) {}

    public static function fromRequest(GlobalSearchRequest $request): self
    {
        /** @var list<string>|null $types */
        $types = $request->validated('types');

        return new self(
            tenantId: (string) $request->user('sanctum')?->tenant_id,
            query: trim((string) $request->validated('q')),
            types: self::normalizeTypes($types),
            perType: (int) $request->validated('per_type', 5),
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        /** @var list<string>|null $types */
        $types = is_array($data['types'] ?? null) ? $data['types'] : null;

        return new self(
            tenantId: (string) ($data['tenantId'] ?? ''),
            query: trim((string) ($data['query'] ?? '')),
            types: self::normalizeTypes($types),
            perType: max(1, min((int) ($data['perType'] ?? 5), 20)),
        );
    }

    public function shouldSearch(string $type): bool
    {
        return in_array($type, $this->types, true);
    }

    /**
     * @param  list<string>|null  $types
     * @return list<string>
     */
    private static function normalizeTypes(?array $types): array
    {
        $allowedTypes = ['contacts', 'companies', 'negotiations', 'tickets', 'users'];

        if ($types === null || $types === []) {
            return $allowedTypes;
        }

        $filtered = array_values(array_unique(array_filter(
            $types,
            static fn (string $type): bool => in_array($type, $allowedTypes, true)
        )));

        return $filtered === [] ? $allowedTypes : $filtered;
    }
}
