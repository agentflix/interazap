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
        Schema::table('chat_campaigns', function (Blueprint $table): void {
            if (! Schema::hasColumn('chat_campaigns', 'message')) {
                $table->text('message')->nullable()->after('name');
            }

            if (! Schema::hasColumn('chat_campaigns', 'filter_criteria')) {
                $table->jsonb('filter_criteria')->nullable()->after('message');
            }

            if (! Schema::hasColumn('chat_campaigns', 'instance_id')) {
                $table->uuid('instance_id')->nullable()->after('tenant_id');
            }
            // Assuming instance_id relates to chat_instances, but spec doesn't explicitly mandate FK constraint yet,
            // helpful to add if table exists. Spec says 'chat_instances' exists.
            // Let's verify chat_instances exists or just leave column. Better add FK for integrity if possible.
            // Checking spec: "chat_instances | Instâncias WhatsApp".
            // I'll add the column index but maybe not foreign key if I'm not sure of the table name (it is chat_instances).
            // Let's stick to simple column first to avoid foreign key issues if table doesn't exist yet (though it should).
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_campaigns', function (Blueprint $table): void {
            $columns = [];

            if (Schema::hasColumn('chat_campaigns', 'message')) {
                $columns[] = 'message';
            }

            if (Schema::hasColumn('chat_campaigns', 'filter_criteria')) {
                $columns[] = 'filter_criteria';
            }

            if (Schema::hasColumn('chat_campaigns', 'instance_id')) {
                $columns[] = 'instance_id';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
