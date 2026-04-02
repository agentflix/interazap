<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona coluna tags (JSONB) à tabela crm_contacts.
 * Permite armazenar tags diretamente no contato para acesso rápido pela IA.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('crm_contacts', 'tags')) {
            return;
        }

        Schema::table('crm_contacts', function (Blueprint $table): void {
            $table->jsonb('tags')->nullable()->default('[]')->after('is_active');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('crm_contacts', 'tags')) {
            return;
        }

        Schema::table('crm_contacts', function (Blueprint $table): void {
            $table->dropColumn('tags');
        });
    }
};
