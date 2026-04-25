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
        Schema::create('chat_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('contact_id')->nullable();
            $table->uuid('ticket_id');
            $table->string('token')->unique();
            $table->json('client_info')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('contact_id');
            $table->index('ticket_id');
            $table->index('last_activity_at');

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->onDelete('cascade');

            $table->foreign('contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->onDelete('set null');

            $table->foreign('ticket_id')
                ->references('id')
                ->on('chat_tickets')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
