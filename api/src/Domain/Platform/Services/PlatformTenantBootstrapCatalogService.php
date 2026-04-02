<?php

declare(strict_types=1);

namespace Domain\Platform\Services;

use Database\Seeders\AiCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DB-backed catalog for tenant bootstrap defaults by segment.
 */
final class PlatformTenantBootstrapCatalogService
{
    public const FORCED_SUPER_ADMIN_SEGMENT_CODE = 'SAAS';

    public const DEFAULT_SEGMENT_CODE = 'GENERAL';

    /**
     * @return array<string, mixed>
     */
    public function forSegmentCode(string $segmentCode): array
    {
        $normalizedSegmentCode = strtoupper(trim($segmentCode));

        $catalogs = $this->loadCatalogs([$normalizedSegmentCode, self::DEFAULT_SEGMENT_CODE]);

        if (isset($catalogs[$normalizedSegmentCode])) {
            return $catalogs[$normalizedSegmentCode];
        }

        if (isset($catalogs[self::DEFAULT_SEGMENT_CODE])) {
            return $catalogs[self::DEFAULT_SEGMENT_CODE];
        }

        return $this->fallbackCatalog();
    }

    /**
     * @param  list<string>  $segmentCodes
     * @return array<string, array<string, mixed>>
     */
    private function loadCatalogs(array $segmentCodes): array
    {
        if (! Schema::hasTable('platform_tenant_bootstrap_catalogs')) {
            return [];
        }

        if (DB::table('platform_tenant_bootstrap_catalogs')->count() === 0) {
            app(AiCatalogSeeder::class)->run();
        }

        $rows = DB::table('platform_tenant_bootstrap_catalogs')
            ->select(['segment_code', 'payload'])
            ->whereIn('segment_code', $segmentCodes)
            ->where('is_active', true)
            ->get();

        $catalogs = [];
        foreach ($rows as $row) {
            $decoded = json_decode((string) $row->payload, true);
            if (! is_array($decoded)) {
                continue;
            }

            $catalogs[(string) $row->segment_code] = $decoded;
        }

        return $catalogs;
    }

    /**
     * @return array<string, mixed>
     */
    private function fallbackCatalog(): array
    {
        return [
            'prompt_suffix' => 'Atue com foco em atendimento consultivo, segurança e geração de oportunidades qualificadas.',
            'agents' => [],
            'funnels' => [],
            'loss_reasons' => [],
            'tags' => [],
            'departments' => [],
        ];
    }
}
