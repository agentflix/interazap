<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_logs')) {
            return;
        }

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
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
