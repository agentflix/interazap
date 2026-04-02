<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip if table doesn't exist (will be created with correct index in original migration)
        if (! \Illuminate\Support\Facades\Schema::hasTable('crm_contact_phones')) {
            return;
        }

        // Drop the constraint that created the incorrect full unique index
        DB::statement('ALTER TABLE crm_contact_phones DROP CONSTRAINT IF EXISTS crm_contact_phones_unique_active');
        DB::statement('DROP INDEX IF EXISTS crm_contact_phones_unique_active');

        // Create the correct partial unique index (only for active phones where valid_to IS NULL)
        DB::statement('
            CREATE UNIQUE INDEX crm_contact_phones_unique_active
            ON crm_contact_phones (tenant_id, phone_e164)
            WHERE valid_to IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS crm_contact_phones_unique_active');

        // Recreate the original (incorrect) full unique index
        DB::statement('
            CREATE UNIQUE INDEX crm_contact_phones_unique_active
            ON crm_contact_phones (tenant_id, phone_e164)
        ');
    }
};
