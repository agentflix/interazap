<?php

declare(strict_types=1);

namespace Domain\CRM\DTOs;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * DTO for CRM contact import.
 *
 * Carries mapping, file reference and import options.
 *
 * @readonly
 */
final readonly class CRMContactImportDTO
{
    /**
     * @param  array{name:string,number:string,email?:string|null,company?:string|null}  $mapping
     */
    public function __construct(
        public string $tenantId,
        public string $filePath,
        public array $mapping,
        public string $delimiter = ',',
        public bool $hasHeader = true,
        public ?string $instanceId = null,
        public ?string $userId = null,
    ) {}

    /**
     * Build DTO from Request.
     *
     * The import_id is a storage-relative path; we resolve it to absolute.
     */
    public static function fromRequest(Request $request): self
    {
        $tenantId = (string) $request->user()->tenant_id;
        $mapping = self::normalizeMapping($request->input('mapping', []));
        $importId = (string) $request->input('import_id');

        return new self(
            tenantId: $tenantId,
            filePath: self::resolveFilePath($importId),
            mapping: $mapping,
            delimiter: (string) $request->input('delimiter', ','),
            hasHeader: (bool) $request->boolean('has_header', true),
            instanceId: $request->input('instance_id'),
            userId: $request->user()?->id ? (string) $request->user()->id : null,
        );
    }

    /**
     * Build DTO from array (job payload).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): self
    {
        $mapping = self::normalizeMapping($payload['mapping'] ?? []);

        return new self(
            tenantId: (string) $payload['tenant_id'],
            filePath: (string) $payload['file_path'],
            mapping: $mapping,
            delimiter: (string) ($payload['delimiter'] ?? ','),
            hasHeader: (bool) ($payload['has_header'] ?? true),
            instanceId: $payload['instance_id'] ?? null,
            userId: $payload['user_id'] ?? null,
        );
    }

    /**
     * @return array{name:string,number:string,email?:string|null,company?:string|null}
     */
    private static function normalizeMapping(mixed $mapping): array
    {
        $data = is_array($mapping) ? $mapping : [];

        return [
            'name' => (string) ($data['name'] ?? ''),
            'number' => (string) ($data['number'] ?? ''),
            'email' => isset($data['email']) ? (string) $data['email'] : null,
            'company' => isset($data['company']) ? (string) $data['company'] : null,
        ];
    }

    /**
     * Resolve a storage-relative path to absolute filesystem path.
     *
     * Accepts both relative (imports/contacts/uuid.csv) and absolute paths
     * for backward compatibility.
     */
    private static function resolveFilePath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            return $path;
        }

        return Storage::path($path);
    }

    /**
     * Convert DTO to array (job payload).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'file_path' => $this->filePath,
            'mapping' => $this->mapping,
            'delimiter' => $this->delimiter,
            'has_header' => $this->hasHeader,
            'instance_id' => $this->instanceId,
            'user_id' => $this->userId,
        ];
    }
}
