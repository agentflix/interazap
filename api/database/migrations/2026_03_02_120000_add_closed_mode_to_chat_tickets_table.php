<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add closed_mode column to chat_tickets for forced close tracking.
 *
 * Supports FEAT-CHAT-TICKETS-MANAGER-059: Manager forced close without
 * customer notification requires distinguishing close modes.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('chat_tickets', function (Blueprint $table): void {
            $table->string('closed_mode', 20)->nullable()->after('close_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_tickets', function (Blueprint $table): void {
            $table->dropColumn('closed_mode');
        });
    }
};
