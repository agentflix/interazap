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
        Schema::table('tenant_message_usage', function (Blueprint $table) {
            $table->timestamp('trial_expired_notified_at')->nullable()->after('alert_100_sent_at');
            $table->timestamp('trial_ending_soon_notified_at')->nullable()->after('trial_expired_notified_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_message_usage', function (Blueprint $table) {
            $table->dropColumn(['trial_expired_notified_at', 'trial_ending_soon_notified_at']);
        });
    }
};
