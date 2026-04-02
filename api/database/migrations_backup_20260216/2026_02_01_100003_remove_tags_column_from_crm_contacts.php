<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove a coluna `tags` (JSONB) da tabela crm_contacts.
 *
 * MOTIVO: Conflito entre a coluna `tags` (array cast) e a relação `tags()` (BelongsToMany).
 * Quando ambos existem, $contact->tags retorna um array em vez de uma Collection,
 * quebrando código que espera métodos como `isNotEmpty()`, `pluck()`, etc.
 *
 * SOLUÇÃO: Usar apenas a relação BelongsToMany via tabela pivot `crm_contact_tags`.
 *
 * @see \Domain\CRM\Models\CRMContact::tags()
 * @see \Domain\Ai\Services\AiContextBuilderService::buildContactContext()
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('crm_contacts', 'tags')) {
            return;
        }

        Schema::table('crm_contacts', function (Blueprint $table): void {
            $table->dropColumn('tags');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('crm_contacts', 'tags')) {
            return;
        }

        Schema::table('crm_contacts', function (Blueprint $table): void {
            $table->jsonb('tags')->nullable()->default('[]')->after('is_active');
        });
    }
};
