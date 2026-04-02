<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_tenants', function (Blueprint $table): void {
            if (! Schema::hasColumn('platform_tenants', 'document')) {
                $table->string('document', 32)->nullable()->after('primary_email');
            }

            if (! Schema::hasColumn('platform_tenants', 'asaas_customer_id')) {
                $table->string('asaas_customer_id', 120)->nullable()->after('document');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_tenants', function (Blueprint $table): void {
            if (Schema::hasColumn('platform_tenants', 'asaas_customer_id')) {
                $table->dropColumn('asaas_customer_id');
            }

            if (Schema::hasColumn('platform_tenants', 'document')) {
                $table->dropColumn('document');
            }
        });
    }
};
