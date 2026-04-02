<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_instances', function (Blueprint $table): void {
            if (! Schema::hasColumn('chat_instances', 'evaluation_enabled')) {
                $table->boolean('evaluation_enabled')->default(false);
            }

            if (! Schema::hasColumn('chat_instances', 'evaluation_cutoff_score')) {
                $table->unsignedTinyInteger('evaluation_cutoff_score')->default(3);
            }
        });
    }

    public function down(): void
    {
        Schema::table('chat_instances', function (Blueprint $table): void {
            if (Schema::hasColumn('chat_instances', 'evaluation_cutoff_score')) {
                $table->dropColumn('evaluation_cutoff_score');
            }
        });
    }
};
