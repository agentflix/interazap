<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // crm_contacts.crm_company_id → SET NULL (contato permanece ao deletar empresa)
        Schema::table('crm_contacts', function (Blueprint $table): void {
            $table->dropForeign(['crm_company_id']);
            $table->foreign('crm_company_id')->references('id')->on('crm_companies')->onDelete('set null');
        });

        // crm_contact_phones.crm_contact_id → CASCADE (telefone sem contato é inútil)
        Schema::table('crm_contact_phones', function (Blueprint $table): void {
            $table->dropForeign(['crm_contact_id']);
            $table->foreign('crm_contact_id')->references('id')->on('crm_contacts')->onDelete('cascade');
        });

        // crm_company_contacts (pivot) → CASCADE ambos os lados
        Schema::table('crm_company_contacts', function (Blueprint $table): void {
            $table->dropForeign(['crm_company_id']);
            $table->dropForeign(['crm_contact_id']);
            $table->foreign('crm_company_id')->references('id')->on('crm_companies')->onDelete('cascade');
            $table->foreign('crm_contact_id')->references('id')->on('crm_contacts')->onDelete('cascade');
        });

        // crm_contact_tags (pivot) → CASCADE ambos os lados
        Schema::table('crm_contact_tags', function (Blueprint $table): void {
            $table->dropForeign(['crm_contact_id']);
            $table->dropForeign(['crm_tag_id']);
            $table->foreign('crm_contact_id')->references('id')->on('crm_contacts')->onDelete('cascade');
            $table->foreign('crm_tag_id')->references('id')->on('crm_tags')->onDelete('cascade');
        });

        // crm_company_tags (pivot) → CASCADE ambos os lados
        Schema::table('crm_company_tags', function (Blueprint $table): void {
            $table->dropForeign(['crm_company_id']);
            $table->dropForeign(['crm_tag_id']);
            $table->foreign('crm_company_id')->references('id')->on('crm_companies')->onDelete('cascade');
            $table->foreign('crm_tag_id')->references('id')->on('crm_tags')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('crm_company_tags', function (Blueprint $table): void {
            $table->dropForeign(['crm_company_id']);
            $table->dropForeign(['crm_tag_id']);
            $table->foreign('crm_company_id')->references('id')->on('crm_companies');
            $table->foreign('crm_tag_id')->references('id')->on('crm_tags');
        });

        Schema::table('crm_contact_tags', function (Blueprint $table): void {
            $table->dropForeign(['crm_contact_id']);
            $table->dropForeign(['crm_tag_id']);
            $table->foreign('crm_contact_id')->references('id')->on('crm_contacts');
            $table->foreign('crm_tag_id')->references('id')->on('crm_tags');
        });

        Schema::table('crm_company_contacts', function (Blueprint $table): void {
            $table->dropForeign(['crm_company_id']);
            $table->dropForeign(['crm_contact_id']);
            $table->foreign('crm_company_id')->references('id')->on('crm_companies');
            $table->foreign('crm_contact_id')->references('id')->on('crm_contacts');
        });

        Schema::table('crm_contact_phones', function (Blueprint $table): void {
            $table->dropForeign(['crm_contact_id']);
            $table->foreign('crm_contact_id')->references('id')->on('crm_contacts');
        });

        Schema::table('crm_contacts', function (Blueprint $table): void {
            $table->dropForeign(['crm_company_id']);
            $table->foreign('crm_company_id')->references('id')->on('crm_companies');
        });
    }
};
