<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow auth_user_id to be null for AI-generated notes.
     */
    public function up(): void
    {
        Schema::table('crm_notes', function (Blueprint $table): void {
            // Drop the FK constraint first
            $table->dropForeign(['auth_user_id']);
        });

        Schema::table('crm_notes', function (Blueprint $table): void {
            // Make the column nullable and re-add constraint
            $table->uuid('auth_user_id')->nullable()->change();
            $table->foreign('auth_user_id')
                ->references('id')
                ->on('auth_users')
                ->nullOnDelete();
        });
    }

    /**
     * Revert: make auth_user_id not null again.
     */
    public function down(): void
    {
        Schema::table('crm_notes', function (Blueprint $table): void {
            $table->dropForeign(['auth_user_id']);
        });

        Schema::table('crm_notes', function (Blueprint $table): void {
            $table->uuid('auth_user_id')->nullable(false)->change();
            $table->foreign('auth_user_id')
                ->references('id')
                ->on('auth_users')
                ->cascadeOnDelete();
        });
    }
};
