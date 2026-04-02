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
            $table->uuid('segment_id')->nullable()->after('is_active');

            $table->foreign('segment_id')
                ->references('id')
                ->on('ai_prompt_segments')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('platform_tenants', function (Blueprint $table): void {
            $table->dropForeign(['segment_id']);
            $table->dropColumn('segment_id');
        });
    }
};
