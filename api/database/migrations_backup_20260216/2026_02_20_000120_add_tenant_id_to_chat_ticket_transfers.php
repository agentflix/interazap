<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_ticket_transfers', function (Blueprint $table): void {
            if (! Schema::hasColumn('chat_ticket_transfers', 'tenant_id')) {
                $table->foreignUuid('tenant_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('platform_tenants')
                    ->cascadeOnDelete();
            }
        });

        DB::statement('UPDATE chat_ticket_transfers AS transfers SET tenant_id = tickets.tenant_id FROM chat_tickets AS tickets WHERE transfers.ticket_id = tickets.id AND transfers.tenant_id IS NULL');
    }

    public function down(): void
    {
        Schema::table('chat_ticket_transfers', function (Blueprint $table): void {
            if (Schema::hasColumn('chat_ticket_transfers', 'tenant_id')) {
                $table->dropConstrainedForeignId('tenant_id');
            }
        });
    }
};
