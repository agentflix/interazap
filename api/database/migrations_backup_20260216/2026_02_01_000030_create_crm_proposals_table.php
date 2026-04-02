<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_proposals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('crm_negotiation_id')->constrained('crm_negotiations')->cascadeOnDelete();
            $table->string('title');
            $table->integer('number')->nullable();
            $table->decimal('total', 12, 2)->default(0);
            $table->string('status')->default('draft');
            $table->date('valid_until')->nullable();
            $table->string('public_token')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_proposal_items', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('crm_proposal_id')->constrained('crm_proposals')->cascadeOnDelete();
            $table->foreignUuid('crm_product_id')->nullable()->constrained('crm_products')->nullOnDelete();
            $table->string('name');
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->integer('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_proposal_items');
        Schema::dropIfExists('crm_proposals');
    }
};
