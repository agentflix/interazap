<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_negotiation_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('crm_negotiation_id')->constrained('crm_negotiations')->cascadeOnDelete();
            $table->foreignUuid('auth_user_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('due_date')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();

            $table->index('auth_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_negotiation_tasks');
    }
};
