<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_collection_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('invoice_id');
            $table->string('template_id', 50);
            $table->string('channel', 20);
            $table->string('recipient', 255);
            $table->string('status', 20);
            $table->string('provider_message_id', 100)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('invoice_id')
                ->references('id')
                ->on('billing_invoices')
                ->cascadeOnDelete();

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'template_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_collection_logs');
    }
};
