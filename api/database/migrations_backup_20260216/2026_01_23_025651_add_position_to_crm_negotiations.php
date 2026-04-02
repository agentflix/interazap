<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_negotiations')) {
            return;
        }

        if (Schema::hasColumn('crm_negotiations', 'position')) {
            return;
        }

        try {
            Schema::table('crm_negotiations', function (Blueprint $table): void {
                $table->integer('position')->default(0)->after('lead_score');
            });
        } catch (\Throwable $e) {
            report($e);

            return;
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_negotiations')) {
            return;
        }

        Schema::table('crm_negotiations', function (Blueprint $table): void {
            $table->dropColumn('position');
        });
    }
};
