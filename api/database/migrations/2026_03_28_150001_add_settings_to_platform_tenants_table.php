<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('platform_tenants', function (Blueprint $table): void {
            $table->jsonb('settings_localization')->default('{}');
            $table->jsonb('settings_privacy')->default('{}');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_tenants', function (Blueprint $table): void {
            $table->dropColumn('settings_localization');
            $table->dropColumn('settings_privacy');
        });
    }
};
