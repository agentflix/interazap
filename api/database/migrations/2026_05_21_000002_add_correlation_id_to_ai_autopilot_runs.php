<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ai_autopilot_runs', 'correlation_id')) {
            Schema::table('ai_autopilot_runs', function (Blueprint $table): void {
                $table->uuid('correlation_id')->nullable()->index()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ai_autopilot_runs', 'correlation_id')) {
            Schema::table('ai_autopilot_runs', function (Blueprint $table): void {
                $table->dropIndex(['correlation_id']);
                $table->dropColumn('correlation_id');
            });
        }
    }
};
