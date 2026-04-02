<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Making playbook columns nullable to support V2 ad-hoc runs and simulator.
     */
    public function up(): void
    {
        Schema::table('ai_autopilot_runs', function (Blueprint $table): void {
            $table->uuid('playbook_id')->nullable()->change();
            $table->unsignedInteger('playbook_version')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_autopilot_runs', function (Blueprint $table): void {
            // Not reversing to NOT NULL because it would break existing V2 data
            $table->uuid('playbook_id')->nullable()->change();
            $table->unsignedInteger('playbook_version')->nullable()->change();
        });
    }
};
