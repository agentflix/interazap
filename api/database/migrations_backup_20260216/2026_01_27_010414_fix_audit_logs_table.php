<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignUuid('tenant_id')->nullable()->constrained('platform_tenants')->cascadeOnDelete();
                $table->foreignUuid('user_id')->nullable()->constrained('auth_users')->nullOnDelete();
                $table->string('event');
                $table->string('user_type')->nullable()->after('user_id');
                $table->string('auditable_type');
                $table->string('auditable_id');
                $table->text('old_values')->nullable()->after('auditable_id');
                $table->text('new_values')->nullable()->after('old_values');
                $table->text('url')->nullable()->after('new_values');
                $table->ipAddress('ip_address')->nullable()->after('url');
                $table->string('user_agent', 1023)->nullable()->after('ip_address');
                $table->string('tags')->nullable()->after('user_agent');
                $table->timestamps();

                $table->index(['tenant_id', 'event']);
            });

            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->change();
        });

        if (Schema::hasColumn('audit_logs', 'action') && ! Schema::hasColumn('audit_logs', 'event')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->renameColumn('action', 'event');
            });
        }

        if (! Schema::hasColumn('audit_logs', 'user_type')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->string('user_type')->nullable()->after('user_id');
            });
        }

        if (! Schema::hasColumn('audit_logs', 'old_values')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->text('old_values')->nullable()->after('auditable_id');
            });
        }

        if (! Schema::hasColumn('audit_logs', 'new_values')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->text('new_values')->nullable()->after('old_values');
            });
        }

        if (! Schema::hasColumn('audit_logs', 'url')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->text('url')->nullable()->after('new_values');
            });
        }

        if (! Schema::hasColumn('audit_logs', 'ip_address')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->ipAddress('ip_address')->nullable()->after('url');
            });
        }

        if (! Schema::hasColumn('audit_logs', 'user_agent')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->string('user_agent', 1023)->nullable()->after('ip_address');
            });
        }

        if (! Schema::hasColumn('audit_logs', 'tags')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->string('tags')->nullable()->after('user_agent');
            });
        }

        if (Schema::hasColumn('audit_logs', 'changes')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->dropColumn('changes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        if (Schema::hasColumn('audit_logs', 'event') && ! Schema::hasColumn('audit_logs', 'action')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->renameColumn('event', 'action');
            });
        }

        if (! Schema::hasColumn('audit_logs', 'changes')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->json('changes')->nullable();
            });
        }

        $columnsToDrop = array_filter(
            ['user_type', 'old_values', 'new_values', 'url', 'ip_address', 'user_agent', 'tags'],
            fn (string $column): bool => Schema::hasColumn('audit_logs', $column)
        );

        if ($columnsToDrop !== []) {
            Schema::table('audit_logs', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable(false)->change();
        });
    }
};
