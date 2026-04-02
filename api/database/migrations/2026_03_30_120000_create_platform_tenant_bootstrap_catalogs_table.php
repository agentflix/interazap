<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_tenant_bootstrap_catalogs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('segment_code', 64)->unique();
            $table->jsonb('payload');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['segment_code', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_tenant_bootstrap_catalogs');
    }
};
