<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->json('reactions')->nullable()->after('metadata');
            $table->boolean('is_edited')->default(false)->after('reactions');
            $table->timestamp('edited_at')->nullable()->after('is_edited');
            $table->json('edit_history')->nullable()->after('edited_at');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropColumn(['reactions', 'is_edited', 'edited_at', 'edit_history']);
        });
    }
};
