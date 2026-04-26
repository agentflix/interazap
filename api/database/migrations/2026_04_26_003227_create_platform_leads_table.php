<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cria a tabela `platform_leads` — leads internos do produto InteraZap
 * (prospects da própria plataforma, sem `tenant_id`).
 *
 * Capturados via landing pages e modais de saída, alimentam o funil
 * de aquisição comercial e podem ser qualificados manualmente.
 */
return new class extends Migration
{
    /**
     * Executa a migração.
     */
    public function up(): void
    {
        Schema::create('platform_leads', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            $table->string('name', 150);
            $table->string('phone', 20);
            $table->string('email', 180);
            $table->string('company', 150)->nullable();

            $table->string('source', 40);

            $table->string('utm_source', 80)->nullable();
            $table->string('utm_medium', 80)->nullable();
            $table->string('utm_campaign', 120)->nullable();

            $table->text('referrer')->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('ip_address', 45)->nullable();

            $table->boolean('lgpd_consent')->default(false);
            $table->string('status', 20)->default('new');

            $table->timestamps();

            $table->index('email');
            $table->index('phone');
            $table->index('source');
            $table->index('created_at');
        });
    }

    /**
     * Reverte a migração.
     */
    public function down(): void
    {
        Schema::dropIfExists('platform_leads');
    }
};
