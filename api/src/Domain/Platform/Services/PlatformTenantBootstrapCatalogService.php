<?php

declare(strict_types=1);

namespace Domain\Platform\Services;

use Database\Seeders\AiCatalogSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de bootstrap de defaults de tenant baseado no segmento.
 *
 * Carrega os dados padrão (agentes, funis, motivos de perda, etc.) para cada segmento
 * a partir da tabela platform_tenant_bootstrap_catalogs, com fallback para o segmento GENERAL.
 */
final class PlatformTenantBootstrapCatalogService
{
    public const FORCED_SUPER_ADMIN_SEGMENT_CODE = 'SAAS';

    public const DEFAULT_SEGMENT_CODE = 'GENERAL';

    /**
     * Retorna o catálogo de bootstrap para o código de segmento informado.
     *
     * Busca o segmento exato e, caso não encontrado, retorna o catálogo GENERAL.
     * Se nem o GENERAL existir, retorna um catálogo mínimo de fallback.
     *
     * @param  string  $segmentCode  Código do segmento (ex: 'GENERAL', 'SAAS').
     * @return array<string, mixed> Catálogo de dados padrão para o segmento.
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
     * Carrega os catálogos da tabela de banco de dados para os segmentos informados.
     *
     * Se a tabela não existir ou estiver vazia, executa o seeder antes de consultar.
     *
     * @param  list<string>  $segmentCodes  Códigos de segmento a carregar.
     * @return array<string, array<string, mixed>> Mapa segmentCode => payload.
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
     * Retorna um catálogo mínimo de fallback quando nenhum segmento é encontrado.
     *
     * @return array<string, mixed> Catálogo com campos obrigatórios vazios.
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
