<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_proposal_items')) {
            return;
        }

        try {
            Schema::table('crm_proposal_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('crm_proposal_items', 'discount')) {
                    $table->decimal('discount', 10, 2)->default(0)->after('unit_price');
                }
                if (! Schema::hasColumn('crm_proposal_items', 'position')) {
                    $table->integer('position')->default(0)->after('total');
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return;
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_proposal_items')) {
            return;
        }

        Schema::table('crm_proposal_items', function (Blueprint $table): void {
            $table->dropColumn(['discount', 'position']);
        });
    }
};
