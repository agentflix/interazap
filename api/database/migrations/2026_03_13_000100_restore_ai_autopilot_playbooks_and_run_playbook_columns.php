<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_autopilot_playbooks')) {
            Schema::create('ai_autopilot_playbooks', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id')->index();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('trigger_type', 50)->default('MANUAL');
                $table->unsignedInteger('version')->default(1);
                $table->json('steps')->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('tenant_id')->references('id')->on('platform_tenants')->cascadeOnDelete();
                $table->unique(['tenant_id', 'name']);
            });
        }

        if (Schema::hasTable('ai_autopilot_runs')) {
            Schema::table('ai_autopilot_runs', function (Blueprint $table): void {
                if (! Schema::hasColumn('ai_autopilot_runs', 'playbook_id')) {
                    $table->uuid('playbook_id')->nullable()->index()->after('tenant_id');
                }

                if (! Schema::hasColumn('ai_autopilot_runs', 'playbook_version')) {
                    $table->unsignedInteger('playbook_version')->nullable()->after('status');
                }
            });

            try {
                Schema::table('ai_autopilot_runs', function (Blueprint $table): void {
                    $table->foreign('playbook_id')->references('id')->on('ai_autopilot_playbooks')->nullOnDelete();
                });
            } catch (\Throwable) {
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_autopilot_runs')) {
            Schema::table('ai_autopilot_runs', function (Blueprint $table): void {
                if (Schema::hasColumn('ai_autopilot_runs', 'playbook_id')) {
                    try {
                        $table->dropForeign(['playbook_id']);
                    } catch (\Throwable) {
                    }
                    $table->dropColumn('playbook_id');
                }

                if (Schema::hasColumn('ai_autopilot_runs', 'playbook_version')) {
                    $table->dropColumn('playbook_version');
                }
            });
        }

        if (Schema::hasTable('ai_autopilot_playbooks')) {
            Schema::drop('ai_autopilot_playbooks');
        }
    }
};
