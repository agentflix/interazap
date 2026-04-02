<?php

declare(strict_types=1);

namespace Tests\Unit\Reports;

use Domain\Reports\Actions\AbstractReportAction;
use Domain\Reports\DTOs\ReportsFilterDTO;
use Domain\Reports\ReportConstants;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AbstractReportActionTest extends TestCase
{
    #[Test]
    public function it_builds_cache_key_with_expected_prefix(): void
    {
        $action = $this->newAction();

        $key = $action->buildKey('tenant-1', 'demo', ['a' => 1, 'b' => 2]);

        $this->assertStringStartsWith('reports:tenant-1:demo:', $key);
    }

    #[Test]
    public function it_parses_date_range_with_day_boundaries(): void
    {
        $action = $this->newAction();

        [$start, $end] = $action->parseRange('2026-03-01', '2026-03-10');

        $this->assertSame('2026-03-01 00:00:00', $start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-10 23:59:59', $end->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_converts_rows_to_csv(): void
    {
        $action = $this->newAction();

        $csv = $action->csv([
            ['name' => 'Alice', 'total' => 10],
            ['name' => 'Bob', 'total' => 20],
        ], ['name', 'total']);

        $this->assertStringContainsString("name,total\n", $csv);
        $this->assertStringContainsString("Alice,10\n", $csv);
        $this->assertStringContainsString("Bob,20\n", $csv);
    }

    #[Test]
    public function it_exposes_default_cache_ttl(): void
    {
        $action = $this->newAction();

        $this->assertSame(ReportConstants::CACHE_TTL, $action->ttl());
    }

    private function newAction(): object
    {
        return new class extends AbstractReportAction
        {
            public function execute(ReportsFilterDTO $dto): array
            {
                return [];
            }

            /**
             * @param  array<int|string, mixed>  $parts
             */
            public function buildKey(string $tenantId, string $prefix, array $parts): string
            {
                return $this->buildCacheKey($tenantId, $prefix, $parts);
            }

            /**
             * @return array{0: Carbon, 1: Carbon}
             */
            public function parseRange(?string $startDate, ?string $endDate): array
            {
                return $this->parseDateRange($startDate, $endDate);
            }

            /**
             * @param  array<int, array<int|string, mixed>>  $rows
             * @param  array<int, string>  $columns
             */
            public function csv(array $rows, array $columns): string
            {
                return $this->toCsv($rows, $columns);
            }

            public function ttl(): int
            {
                return $this->cacheTtl();
            }
        };
    }
}
