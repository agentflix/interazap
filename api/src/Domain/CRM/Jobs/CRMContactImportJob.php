<?php

declare(strict_types=1);

namespace Domain\CRM\Jobs;

use Domain\CRM\Actions\CRMContactImportActions;
use Domain\CRM\DTOs\CRMContactImportDTO;
use Domain\Shared\Concerns\HasJobDefaults;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Context;

/**
 * Async job for CSV contact import.
 */
final class CRMContactImportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use HasJobDefaults;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Prevent duplicate imports for the same file.
     */
    public int $uniqueFor = 300;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload) {}

    /**
     * Unique ID based on file path to prevent duplicate imports.
     */
    public function uniqueId(): string
    {
        return md5($this->payload['filePath'] ?? $this->payload['tenantId'] ?? 'default');
    }

    public function handle(CRMContactImportActions $actions): void
    {
        $dto = CRMContactImportDTO::fromArray($this->payload);

        Context::add([
            'tenant_id' => $dto->tenantId,
            'import_id' => $dto->filePath,
        ]);

        $actions->process($dto);
    }
}
