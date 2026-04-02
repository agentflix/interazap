<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_company_tags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('crm_company_id')->constrained('crm_companies')->cascadeOnDelete();
            $table->foreignUuid('crm_tag_id')->constrained('crm_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'crm_company_id', 'crm_tag_id']);
        });

        Schema::create('crm_negotiation_tags', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('crm_negotiation_id')->constrained('crm_negotiations')->cascadeOnDelete();
            $table->foreignUuid('crm_tag_id')->constrained('crm_tags')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'crm_negotiation_id', 'crm_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_negotiation_tags');
        Schema::dropIfExists('crm_company_tags');
    }
};
