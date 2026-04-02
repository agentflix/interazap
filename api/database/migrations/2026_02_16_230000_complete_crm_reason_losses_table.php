<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // No-op: campos de crm_reason_losses foram consolidados em
        // 2026_01_01_000020_create_crm_base_tables.php.
    }

    public function down(): void
    {
        // No-op.
    }
};
