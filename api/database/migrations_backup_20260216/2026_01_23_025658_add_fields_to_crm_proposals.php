<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('crm_proposals')) {
            return;
        }

        try {
            Schema::table('crm_proposals', function (Blueprint $table): void {
                if (! Schema::hasColumn('crm_proposals', 'number')) {
                    $table->integer('number')->nullable()->after('title');
                }
                if (! Schema::hasColumn('crm_proposals', 'valid_until')) {
                    $table->date('valid_until')->nullable()->after('status');
                }
                if (! Schema::hasColumn('crm_proposals', 'notes')) {
                    $table->text('notes')->nullable()->after('public_token');
                }
                if (! Schema::hasColumn('crm_proposals', 'accepted_at')) {
                    $table->timestamp('accepted_at')->nullable()->after('viewed_at');
                }
                if (! Schema::hasColumn('crm_proposals', 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable()->after('accepted_at');
                }
            });
        } catch (\Throwable $e) {
            report($e);

            return;
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('crm_proposals')) {
            return;
        }

        Schema::table('crm_proposals', function (Blueprint $table): void {
            $table->dropColumn(['number', 'valid_until', 'notes', 'accepted_at', 'rejected_at']);
        });
    }
};
