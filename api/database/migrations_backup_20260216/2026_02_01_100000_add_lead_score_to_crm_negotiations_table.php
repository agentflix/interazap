<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add lead_score column for AI lead qualification.
     */
    public function up(): void
    {
        if (Schema::hasColumn('crm_negotiations', 'lead_score')) {
            return;
        }

        Schema::table('crm_negotiations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('lead_score')->default(0)->after('status');
        });
    }

    /**
     * Remove lead_score column.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('crm_negotiations', 'lead_score')) {
            return;
        }

        Schema::table('crm_negotiations', function (Blueprint $table): void {
            $table->dropColumn('lead_score');
        });
    }
};
