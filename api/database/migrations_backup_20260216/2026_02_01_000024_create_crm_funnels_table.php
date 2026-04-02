<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_negotiation_funnels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
        });

        Schema::create('crm_negotiation_funnel_steps', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('crm_negotiation_funnel_id')->constrained('crm_negotiation_funnels')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('order');
            $table->timestamps();

            $table->unique(['tenant_id', 'crm_negotiation_funnel_id', 'order'], 'crm_funnel_steps_order_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_negotiation_funnel_steps');
        Schema::dropIfExists('crm_negotiation_funnels');
    }
};
