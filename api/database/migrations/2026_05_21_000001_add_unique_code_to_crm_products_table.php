<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adiciona índice único parcial (tenant_id, code) na tabela crm_products.
 *
 * Resolve duplicatas existentes antes de criar o índice, preservando o
 * registro mais antigo e suffixando os demais com o short ID.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Resolver duplicatas existentes (preserva o mais antigo, suffixa os demais)
        $duplicates = DB::select("
            SELECT tenant_id, code, array_to_string(array_agg(id ORDER BY created_at), ',') as ids
            FROM crm_products
            WHERE code IS NOT NULL
            GROUP BY tenant_id, code
            HAVING COUNT(*) > 1
        ");

        foreach ($duplicates as $duplicate) {
            $ids = explode(',', (string) $duplicate->ids);
            $counter = count($ids);
            // Manter o primeiro (mais antigo), suffixar os demais
            for ($i = 1; $i < $counter; $i++) {
                $suffix = substr($ids[$i], 0, 8);
                DB::table('crm_products')
                    ->where('id', $ids[$i])
                    ->update(['code' => $duplicate->code.'-'.$suffix]);
            }
        }

        // 2. Criar índice único parcial: (tenant_id, code) WHERE code IS NOT NULL
        DB::statement('
            CREATE UNIQUE INDEX uq_crm_products_tenant_code
            ON crm_products (tenant_id, code)
            WHERE code IS NOT NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_crm_products_tenant_code');
    }
};
