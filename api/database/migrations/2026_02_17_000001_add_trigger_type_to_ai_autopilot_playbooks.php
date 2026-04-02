<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add trigger_type to ai_autopilot_playbooks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_autopilot_playbooks', function (Blueprint $table): void {
            $table->string('trigger_type', 50)->default('MANUAL');
        });
    }

    public function down(): void
    {
        Schema::table('ai_autopilot_playbooks', function (Blueprint $table): void {
            $table->dropColumn('trigger_type');
        });
    }
};
