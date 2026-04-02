<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_custom_field_values', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('crm_custom_field_id')->constrained('crm_custom_fields')->cascadeOnDelete();
            $table->uuidMorphs('entity'); // entity_type, entity_id
            $table->text('value')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'crm_custom_field_id', 'entity_type', 'entity_id'], 'crm_custom_values_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_custom_field_values');
    }
};
