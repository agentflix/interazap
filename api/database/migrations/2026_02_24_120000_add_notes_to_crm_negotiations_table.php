<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adicionar coluna notes à tabela crm_negotiations.
     */
    public function up(): void
    {
        Schema::table('crm_negotiations', function (Blueprint $table): void {
            $table->text('notes')->nullable()->after('status');
        });
    }

    /**
     * Remover coluna notes da tabela crm_negotiations.
     */
    public function down(): void
    {
        Schema::table('crm_negotiations', function (Blueprint $table): void {
            $table->dropColumn('notes');
        });
    }
};
