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
        if (Schema::hasTable('chat_campaign_contacts')) {
            return;
        }

        Schema::create('chat_campaign_contacts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->uuid('campaign_id');
            $table->uuid('contact_id');
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            if (Schema::hasTable('chat_campaigns')) {
                $table->foreign('campaign_id')
                    ->references('id')
                    ->on('chat_campaigns')
                    ->onDelete('cascade');
            }

            if (Schema::hasTable('crm_contacts')) {
                $table->foreign('contact_id')
                    ->references('id')
                    ->on('crm_contacts')
                    ->onDelete('cascade');
            }

            $table->unique(['campaign_id', 'contact_id']); // Prevent duplicate contacts in same campaign
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_campaign_contacts');
    }
};
