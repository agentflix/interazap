<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_chatbot_rules', function (Blueprint $table): void {
            $table->boolean('is_welcome')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('chat_chatbot_rules', function (Blueprint $table): void {
            $table->dropColumn('is_welcome');
        });
    }
};
