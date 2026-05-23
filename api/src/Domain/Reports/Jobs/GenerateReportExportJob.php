<?php

declare(strict_types=1);

namespace Domain\Reports\Jobs;

use Domain\Reports\Contracts\ReportActionInterface;
use Domain\Reports\DTOs\ReportsFilterDTO;
use Domain\Reports\Exports\ReportCsvExporter;
use Domain\Reports\Exports\ReportXlsxExporter;
use Domain\Reports\ReportActionRegistry;
use Domain\Shared\Support\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Job assíncrono para exports de relatórios com grande volume de dados (> 10k linhas).
 */
final class GenerateReportExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** @var int Número máximo de tentativas */
    public int $tries = 3;

    /** @var int Timeout em segundos */
    public int $timeout = 300;

    /**
     * @param  string  $reportType  Tipo do relatório (ex: sales-funnel, billing)
     * @param  array<string, mixed>  $dtoArray  DTO serializado como array
     * @param  string  $format  Formato de exportação (csv ou xlsx)
     * @param  string  $tenantId  ID do tenant
     */
    public function __construct(
        private readonly string $reportType,
        private readonly array $dtoArray,
        private readonly string $format,
        private readonly string $tenantId,
    ) {}

    /**
     * Executa o job de exportação.
     */
    public function handle(): void
    {
        TenantContext::set($this->tenantId);

        Log::info('GenerateReportExportJob started', [
            'report_type' => $this->reportType,
            'format' => $this->format,
            'tenant_id' => $this->tenantId,
        ]);

        try {
            $dto = ReportsFilterDTO::fromArray($this->dtoArray);
            $action = $this->resolveAction();
            $data = $action->execute($dto);

            $filename = sprintf(
                'exports/%s/%s_%s.%s',
                $this->tenantId,
                $this->reportType,
                now()->format('Y-m-d_His'),
                $this->format
            );

            if ($this->format === 'csv') {
                $exporter = new ReportCsvExporter;
                $content = $exporter->export($data, $this->reportType);
                Storage::put($filename, $content);
            } else {
                $exporter = new ReportXlsxExporter;
                $exporter->prepare($data, $this->reportType);
                Excel::store($exporter, $filename);
            }

            Log::info('GenerateReportExportJob completed', [
                'filename' => $filename,
            ]);
        } catch (\Throwable $exception) {
            Log::error('GenerateReportExportJob failed', [
                'report_type' => $this->reportType,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        } finally {
            TenantContext::clear();
        }
    }

    /**
     * Resolve a action de relatório pelo tipo.
     */
    private function resolveAction(): ReportActionInterface
    {
        $registry = app(ReportActionRegistry::class);
        $action = $registry->resolve($this->reportType);

        if ($action === null) {
            throw new \InvalidArgumentException("Unknown report type: {$this->reportType}");
        }

        return $action;
    }
}
