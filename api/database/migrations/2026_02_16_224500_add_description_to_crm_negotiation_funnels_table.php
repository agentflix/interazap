<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_negotiation_funnels', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('crm_negotiation_funnels', function (Blueprint $table): void {
            $table->dropColumn('description');
        });
    }
};
