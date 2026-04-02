<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_purge_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('tenant_name', 255);
            $table->string('tenant_document', 32)->nullable();
            $table->string('tenant_email', 255)->nullable();
            $table->jsonb('summary');
            $table->jsonb('invoices_snapshot');
            $table->timestamp('purged_at');
            $table->string('purged_by', 20)->default('system');
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->index('tenant_id');
            $table->index('purged_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_purge_reports');
    }
};
