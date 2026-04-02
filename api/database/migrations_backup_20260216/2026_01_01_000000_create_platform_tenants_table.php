<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('tenant_code', 12)->unique();
            $table->string('primary_email')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_tenants');
    }
};
