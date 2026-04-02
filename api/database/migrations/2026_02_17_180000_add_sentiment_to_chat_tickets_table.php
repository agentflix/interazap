<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_tickets', function (Blueprint $table): void {
            $table->string('sentiment', 20)->nullable()->after('close_reason');
            $table->smallInteger('sentiment_score')->nullable()->after('sentiment');
            $table->timestamp('sentiment_updated_at')->nullable()->after('sentiment_score');

            $table->index(['tenant_id', 'sentiment'], 'idx_chat_tickets_sentiment');
            $table->index(['tenant_id', 'sentiment_score'], 'idx_chat_tickets_sentiment_score');
        });
    }

    public function down(): void
    {
        Schema::table('chat_tickets', function (Blueprint $table): void {
            $table->dropIndex('idx_chat_tickets_sentiment');
            $table->dropIndex('idx_chat_tickets_sentiment_score');

            $table->dropColumn(['sentiment', 'sentiment_score', 'sentiment_updated_at']);
        });
    }
};
