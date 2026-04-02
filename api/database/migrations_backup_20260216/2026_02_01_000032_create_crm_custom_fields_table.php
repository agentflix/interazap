<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_custom_fields', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('type');
            $table->string('entity')->default('contact');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'name', 'entity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_custom_fields');
    }
};
