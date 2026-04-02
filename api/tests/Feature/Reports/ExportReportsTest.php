<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Maatwebsite\Excel\Facades\Excel;

class ExportReportsTest extends ReportsTestCase
{
    use LazilyRefreshDatabase;

    private AuthUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = AuthUser::factory()->create();
        $this->user->givePermissionTo('reports.export');

        Sanctum::actingAs($this->user, abilities: ['*']);
    }

    public function test_exports_report_as_csv(): void
    {
        $response = $this->get('/api/reports/sales-funnel/export?start_date=2026-01-01&end_date=2026-01-31&format=csv');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition');
    }

    public function test_exports_report_as_xlsx(): void
    {
        Excel::fake();

        $response = $this->get('/api/reports/sales-funnel/export?start_date=2026-01-01&end_date=2026-01-31&format=xlsx');

        $response->assertOk();

        Excel::assertDownloaded('sales-funnel_'.now()->format('Y-m-d_His').'.xlsx');
    }

    public function test_returns_404_for_unknown_report_export(): void
    {
        $response = $this->getJson('/api/reports/unknown/export?start_date=2026-01-01&end_date=2026-01-31&format=csv');

        $response->assertStatus(404);
    }

    public function test_requires_format_parameter_on_export(): void
    {
        $response = $this->getJson('/api/reports/sales-funnel/export?start_date=2026-01-01&end_date=2026-01-31');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['format']);
    }
}
