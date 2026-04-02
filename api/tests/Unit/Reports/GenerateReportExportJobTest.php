<?php

declare(strict_types=1);

namespace Tests\Unit\Reports;

use Domain\Reports\Actions\GetSalesFunnelReportAction;
use Domain\Reports\Jobs\GenerateReportExportJob;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class GenerateReportExportJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_exports_csv_file_to_storage(): void
    {
        Storage::fake('local');
        Date::setTestNow('2026-03-01 12:34:56');

        $fakeAction = new class
        {
            /**
             * @return array<string, mixed>
             */
            public function execute(object $dto): array
            {
                return [
                    'summary' => [
                        'total' => 1,
                    ],
                ];
            }
        };

        app()->instance(GetSalesFunnelReportAction::class, $fakeAction);

        $job = new GenerateReportExportJob(
            reportType: 'sales-funnel',
            dtoArray: [
                'tenant_id' => 'tenant-1',
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-01',
            ],
            format: 'csv',
            tenantId: 'tenant-1',
        );

        $job->handle();

        Storage::disk('local')->assertExists('exports/tenant-1/sales-funnel_2026-03-01_123456.csv');
        $content = Storage::disk('local')->get('exports/tenant-1/sales-funnel_2026-03-01_123456.csv');

        $this->assertStringContainsString("section,total\n", $content);
        $this->assertStringContainsString("summary,1\n", $content);
    }

    public function test_exports_xlsx_file_via_excel_facade(): void
    {
        Date::setTestNow('2026-03-01 12:34:56');

        $fakeAction = new class
        {
            /**
             * @return array<string, mixed>
             */
            public function execute(object $dto): array
            {
                return [
                    'summary' => [
                        'total' => 2,
                    ],
                ];
            }
        };

        app()->instance(GetSalesFunnelReportAction::class, $fakeAction);

        Excel::shouldReceive('store')
            ->once()
            ->withArgs(fn (object $exporter, string $filename): bool => $filename === 'exports/tenant-2/sales-funnel_2026-03-01_123456.xlsx'
                && $exporter instanceof \Domain\Reports\Exports\ReportXlsxExporter)
            ->andReturnTrue();

        $job = new GenerateReportExportJob(
            reportType: 'sales-funnel',
            dtoArray: [
                'tenant_id' => 'tenant-2',
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-01',
            ],
            format: 'xlsx',
            tenantId: 'tenant-2',
        );

        $job->handle();
    }

    public function test_throws_for_unknown_report_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown report type: unknown-report');

        $job = new GenerateReportExportJob(
            reportType: 'unknown-report',
            dtoArray: [
                'tenant_id' => 'tenant-3',
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-01',
            ],
            format: 'csv',
            tenantId: 'tenant-3',
        );

        $job->handle();
    }
}
