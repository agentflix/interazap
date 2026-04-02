<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_negotiation_products', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('crm_negotiation_id')->constrained('crm_negotiations')->cascadeOnDelete();
            $table->foreignUuid('crm_product_id')->nullable()->constrained('crm_products')->nullOnDelete();
            $table->string('name');
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'crm_negotiation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_negotiation_products');
    }
};
