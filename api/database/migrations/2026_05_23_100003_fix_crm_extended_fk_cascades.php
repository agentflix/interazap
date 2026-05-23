<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // crm_proposals.crm_negotiation_id → CASCADE (proposta sem negociação é inútil)
        Schema::table('crm_proposals', function (Blueprint $table): void {
            $table->dropForeign(['crm_negotiation_id']);
            $table->foreign('crm_negotiation_id')->references('id')->on('crm_negotiations')->onDelete('cascade');
        });

        // crm_proposal_items — CASCADE na proposta, SET NULL no produto
        Schema::table('crm_proposal_items', function (Blueprint $table): void {
            $table->dropForeign(['crm_proposal_id']);
            $table->dropForeign(['crm_product_id']);
            $table->foreign('crm_proposal_id')->references('id')->on('crm_proposals')->onDelete('cascade');
            $table->foreign('crm_product_id')->references('id')->on('crm_products')->onDelete('set null');
        });

        // crm_custom_field_values.crm_custom_field_id → CASCADE (valor sem definição é inútil)
        Schema::table('crm_custom_field_values', function (Blueprint $table): void {
            $table->dropForeign(['crm_custom_field_id']);
            $table->foreign('crm_custom_field_id')->references('id')->on('crm_custom_fields')->onDelete('cascade');
        });

        // crm_notes.auth_user_id → SET NULL (nota permanece ao deletar autor)
        Schema::table('crm_notes', function (Blueprint $table): void {
            $table->dropForeign(['auth_user_id']);
            $table->foreign('auth_user_id')->references('id')->on('auth_users')->onDelete('set null');
        });

        // crm_negotiation_files — CASCADE na negociação, SET NULL no usuário
        Schema::table('crm_negotiation_files', function (Blueprint $table): void {
            $table->dropForeign(['crm_negotiation_id']);
            $table->dropForeign(['auth_user_id']);
            $table->foreign('crm_negotiation_id')->references('id')->on('crm_negotiations')->onDelete('cascade');
            $table->foreign('auth_user_id')->references('id')->on('auth_users')->onDelete('set null');
        });

        // crm_events.auth_user_id → SET NULL (evento permanece ao deletar criador)
        Schema::table('crm_events', function (Blueprint $table): void {
            $table->dropForeign(['auth_user_id']);
            $table->foreign('auth_user_id')->references('id')->on('auth_users')->onDelete('set null');
        });

        // crm_event_links.crm_event_id → CASCADE (link sem evento é inútil)
        Schema::table('crm_event_links', function (Blueprint $table): void {
            $table->dropForeign(['crm_event_id']);
            $table->foreign('crm_event_id')->references('id')->on('crm_events')->onDelete('cascade');
        });

        // crm_event_participants — CASCADE no evento, SET NULL em user e contact
        Schema::table('crm_event_participants', function (Blueprint $table): void {
            $table->dropForeign(['crm_event_id']);
            $table->dropForeign(['auth_user_id']);
            $table->dropForeign(['crm_contact_id']);
            $table->foreign('crm_event_id')->references('id')->on('crm_events')->onDelete('cascade');
            $table->foreign('auth_user_id')->references('id')->on('auth_users')->onDelete('set null');
            $table->foreign('crm_contact_id')->references('id')->on('crm_contacts')->onDelete('set null');
        });

        // crm_event_reminders — CASCADE no evento, SET NULL no usuário
        Schema::table('crm_event_reminders', function (Blueprint $table): void {
            $table->dropForeign(['crm_event_id']);
            $table->dropForeign(['auth_user_id']);
            $table->foreign('crm_event_id')->references('id')->on('crm_events')->onDelete('cascade');
            $table->foreign('auth_user_id')->references('id')->on('auth_users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('crm_event_reminders', function (Blueprint $table): void {
            $table->dropForeign(['crm_event_id']);
            $table->dropForeign(['auth_user_id']);
            $table->foreignUuid('crm_event_id')->constrained('crm_events');
            $table->foreignUuid('auth_user_id')->nullable()->constrained('auth_users');
        });

        Schema::table('crm_event_participants', function (Blueprint $table): void {
            $table->dropForeign(['crm_event_id']);
            $table->dropForeign(['auth_user_id']);
            $table->dropForeign(['crm_contact_id']);
            $table->foreignUuid('crm_event_id')->constrained('crm_events');
            $table->foreignUuid('auth_user_id')->nullable()->constrained('auth_users');
            $table->foreignUuid('crm_contact_id')->nullable()->constrained('crm_contacts');
        });

        Schema::table('crm_event_links', function (Blueprint $table): void {
            $table->dropForeign(['crm_event_id']);
            $table->foreignUuid('crm_event_id')->constrained('crm_events');
        });

        Schema::table('crm_events', function (Blueprint $table): void {
            $table->dropForeign(['auth_user_id']);
            $table->foreignUuid('auth_user_id')->nullable()->constrained('auth_users');
        });

        Schema::table('crm_negotiation_files', function (Blueprint $table): void {
            $table->dropForeign(['crm_negotiation_id']);
            $table->dropForeign(['auth_user_id']);
            $table->foreignUuid('crm_negotiation_id')->constrained('crm_negotiations');
            $table->foreignUuid('auth_user_id')->nullable()->constrained('auth_users');
        });

        Schema::table('crm_notes', function (Blueprint $table): void {
            $table->dropForeign(['auth_user_id']);
            $table->foreignUuid('auth_user_id')->nullable()->constrained('auth_users');
        });

        Schema::table('crm_custom_field_values', function (Blueprint $table): void {
            $table->dropForeign(['crm_custom_field_id']);
            $table->foreignUuid('crm_custom_field_id')->constrained('crm_custom_fields');
        });

        Schema::table('crm_proposal_items', function (Blueprint $table): void {
            $table->dropForeign(['crm_proposal_id']);
            $table->dropForeign(['crm_product_id']);
            $table->foreignUuid('crm_proposal_id')->constrained('crm_proposals');
            $table->foreignUuid('crm_product_id')->nullable()->constrained('crm_products');
        });

        Schema::table('crm_proposals', function (Blueprint $table): void {
            $table->dropForeign(['crm_negotiation_id']);
            $table->foreignUuid('crm_negotiation_id')->constrained('crm_negotiations');
        });
    }
};
