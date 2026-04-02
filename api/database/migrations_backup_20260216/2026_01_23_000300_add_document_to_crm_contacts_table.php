<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_contacts') || Schema::hasColumn('crm_contacts', 'document')) {
            return;
        }

        Schema::table('crm_contacts', function (Blueprint $table): void {
            $table->string('document', 32)->nullable()->after('email');
            $table->index(['tenant_id', 'document']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_contacts') || ! Schema::hasColumn('crm_contacts', 'document')) {
            return;
        }

        Schema::table('crm_contacts', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'document']);
            $table->dropColumn('document');
        });
    }
};
