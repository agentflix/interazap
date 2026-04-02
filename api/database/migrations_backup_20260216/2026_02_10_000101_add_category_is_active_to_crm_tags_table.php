<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_tags', function (Blueprint $table): void {
            $table->string('category')->nullable()->after('color');
            $table->boolean('is_active')->default(true)->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('crm_tags', function (Blueprint $table): void {
            $table->dropColumn(['category', 'is_active']);
        });
    }
};
