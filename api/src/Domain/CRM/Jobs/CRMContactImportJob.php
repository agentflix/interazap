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
 * Job assíncrono para importação de contatos CRM a partir de arquivo CSV.
 *
 * Implementa ShouldBeUnique para evitar importações duplicadas do mesmo arquivo
 * durante janela de 300 segundos.
 */
final class CRMContactImportJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use HasJobDefaults;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Janela de unicidade em segundos: impede importações duplicadas do mesmo arquivo. */
    public int $uniqueFor = 300;

    /**
     * @param  array<string, mixed>  $payload  Payload serializado do CRMContactImportDTO
     */
    public function __construct(private readonly array $payload) {}

    /** ID único baseado no hash do caminho do arquivo para deduplicação na fila. */
    public function uniqueId(): string
    {
        return md5($this->payload['filePath'] ?? $this->payload['tenantId'] ?? 'default');
    }

    /** Executa a importação delegando ao CRMContactImportActions com contexto de tenant. */
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
