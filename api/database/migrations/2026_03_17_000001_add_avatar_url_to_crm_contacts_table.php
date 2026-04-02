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
            $table->string('avatar_url', 500)->nullable()->after('whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table): void {
            $table->dropColumn('avatar_url');
        });
    }
};
