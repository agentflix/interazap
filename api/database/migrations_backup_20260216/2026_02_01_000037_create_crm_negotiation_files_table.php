<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_negotiation_files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('crm_negotiation_id')->constrained('crm_negotiations')->cascadeOnDelete();
            $table->foreignUuid('auth_user_id')->nullable()->constrained('auth_users')->nullOnDelete();
            $table->string('name');
            $table->string('path');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_negotiation_files');
    }
};
