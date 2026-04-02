<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

describe('Performance indexes migration (Phase 3)', function (): void {
    it('can rollback and reapply performance indexes safely', function (): void {
        $migration = require base_path('database/migrations/2026_01_01_000099_create_performance_indexes.php');

        expect($migration)->toBeInstanceOf(Migration::class);

        $migration->down();
        $migration->up();

        $usageIndexes = collect(DB::select("SELECT indexname FROM pg_indexes WHERE schemaname = 'public' AND tablename = 'ai_usage_logs'"))
            ->pluck('indexname');

        $crmIndexes = collect(DB::select("SELECT indexname FROM pg_indexes WHERE schemaname = 'public' AND tablename = 'crm_contacts'"))
            ->pluck('indexname');

        expect($usageIndexes)->toContain('idx_ai_usage_logs_created_brin');
        expect($usageIndexes)->toContain('idx_ai_usage_logs_metadata_gin');
        expect($crmIndexes)->toContain('idx_crm_contacts_list');
        expect($crmIndexes)->toContain('idx_crm_contacts_tenant_company_active');
    });
});
