<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_users', function (Blueprint $table): void {
            // OAuth users have no password — make it nullable
            $table->string('password', 255)->nullable()->change();

            if (! Schema::hasColumn('auth_users', 'provider')) {
                $table->string('provider', 20)->nullable()->after('email');
            }

            if (! Schema::hasColumn('auth_users', 'provider_id')) {
                $table->string('provider_id', 255)->nullable()->after('provider');
            }
        });

        // Add unique index only if it doesn't already exist
        $indexExists = collect(Schema::getIndexes('auth_users'))
            ->contains(fn ($index) => $index['name'] === 'uq_provider');

        if (! $indexExists) {
            Schema::table('auth_users', function (Blueprint $table): void {
                $table->unique(['provider', 'provider_id'], 'uq_provider');
            });
        }
    }

    public function down(): void
    {
        Schema::table('auth_users', function (Blueprint $table): void {
            // Revert password to NOT NULL
            $table->string('password', 255)->nullable(false)->change();

            // Drop index before columns
            $indexExists = collect(Schema::getIndexes('auth_users'))
                ->contains(fn ($index) => $index['name'] === 'uq_provider');

            if ($indexExists) {
                $table->dropUnique('uq_provider');
            }

            if (Schema::hasColumn('auth_users', 'provider_id')) {
                $table->dropColumn('provider_id');
            }

            if (Schema::hasColumn('auth_users', 'provider')) {
                $table->dropColumn('provider');
            }
        });
    }
};
