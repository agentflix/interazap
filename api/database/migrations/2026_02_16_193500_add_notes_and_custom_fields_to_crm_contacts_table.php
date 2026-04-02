<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table): void {
            $table->text('notes')->nullable()->after('position');
            $table->jsonb('custom_fields')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table): void {
            $table->dropColumn(['notes', 'custom_fields']);
        });
    }
};
