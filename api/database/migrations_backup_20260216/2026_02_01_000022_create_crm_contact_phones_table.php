<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_contact_phones', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('crm_contact_id')->constrained('crm_contacts')->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('phone_e164');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'crm_contact_id']);
        });

        // Create partial unique index using raw SQL (Laravel's whereNull doesn't work with unique indexes)
        DB::statement('
            CREATE UNIQUE INDEX crm_contact_phones_unique_active
            ON crm_contact_phones (tenant_id, phone_e164)
            WHERE valid_to IS NULL
        ');
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contact_phones');
    }
};
