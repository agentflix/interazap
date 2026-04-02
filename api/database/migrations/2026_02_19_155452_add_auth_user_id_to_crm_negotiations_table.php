<?php

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
        Schema::table('crm_negotiations', function (Blueprint $table): void {
            $table->uuid('auth_user_id')->nullable()->after('crm_reason_loss_id');
            $table->foreign('auth_user_id')->references('id')->on('auth_users')->nullOnDelete();
            $table->index('auth_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_negotiations', function (Blueprint $table): void {
            $table->dropForeign(['auth_user_id']);
            $table->dropIndex(['auth_user_id']);
            $table->dropColumn('auth_user_id');
        });
    }
};
