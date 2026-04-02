<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_negotiation_funnel_steps', function (Blueprint $table): void {
            $table->string('color')->nullable()->after('name');
            $table->boolean('is_active')->default(true)->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('crm_negotiation_funnel_steps', function (Blueprint $table): void {
            $table->dropColumn(['color', 'is_active']);
        });
    }
};
