<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('crm_company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('document', 32)->nullable()->after('email');
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('position')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'crm_company_id']);
            $table->index(['tenant_id', 'document']);
            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contacts');
    }
};
