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
            if (! Schema::hasColumn('platform_tenants', 'phone')) {
                $table->string('phone', 20)->nullable()->after('document');
            }

            if (! Schema::hasColumn('platform_tenants', 'street')) {
                $table->string('street', 255)->nullable()->after('phone');
            }

            if (! Schema::hasColumn('platform_tenants', 'number')) {
                $table->string('number', 20)->nullable()->after('street');
            }

            if (! Schema::hasColumn('platform_tenants', 'complement')) {
                $table->string('complement', 120)->nullable()->after('number');
            }

            if (! Schema::hasColumn('platform_tenants', 'district')) {
                $table->string('district', 120)->nullable()->after('complement');
            }

            if (! Schema::hasColumn('platform_tenants', 'city')) {
                $table->string('city', 120)->nullable()->after('district');
            }

            if (! Schema::hasColumn('platform_tenants', 'state')) {
                $table->string('state', 2)->nullable()->after('city');
            }

            if (! Schema::hasColumn('platform_tenants', 'zip_code')) {
                $table->string('zip_code', 20)->nullable()->after('state');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_tenants', function (Blueprint $table): void {
            foreach (['zip_code', 'state', 'city', 'district', 'complement', 'number', 'street', 'phone'] as $column) {
                if (Schema::hasColumn('platform_tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
