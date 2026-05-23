<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // crm_negotiation_funnel_steps.crm_negotiation_funnel_id → CASCADE (step sem funil é inútil)
        Schema::table('crm_negotiation_funnel_steps', function (Blueprint $table): void {
            $table->dropForeign(['crm_negotiation_funnel_id']);
            $table->foreign('crm_negotiation_funnel_id')->references('id')->on('crm_negotiation_funnels')->onDelete('cascade');
        });

        // crm_negotiations — múltiplas FKs com comportamentos distintos
        Schema::table('crm_negotiations', function (Blueprint $table): void {
            $table->dropForeign(['crm_company_id']);
            $table->dropForeign(['crm_contact_id']);
            $table->dropForeign(['crm_negotiation_funnel_id']);
            $table->dropForeign(['crm_negotiation_funnel_step_id']);
            $table->dropForeign(['crm_reason_loss_id']);
            $table->dropForeign(['auth_user_id']);

            // nullable → SET NULL (negociação permanece ao deletar empresa)
            $table->foreign('crm_company_id')->references('id')->on('crm_companies')->onDelete('set null');
            // RESTRICT → impede deleção de contato com negociações ativas
            $table->foreign('crm_contact_id')->references('id')->on('crm_contacts')->onDelete('restrict');
            // RESTRICT → impede deleção de funil com negociações
            $table->foreign('crm_negotiation_funnel_id')->references('id')->on('crm_negotiation_funnels')->onDelete('restrict');
            // RESTRICT → impede deleção de etapa com negociações
            $table->foreign('crm_negotiation_funnel_step_id')->references('id')->on('crm_negotiation_funnel_steps')->onDelete('restrict');
            // nullable → SET NULL
            $table->foreign('crm_reason_loss_id')->references('id')->on('crm_reason_losses')->onDelete('set null');
            // nullable → SET NULL (responsável pode ser deletado)
            $table->foreign('auth_user_id')->references('id')->on('auth_users')->onDelete('set null');
        });

        // crm_negotiation_tasks — CASCADE na negociação, SET NULL no usuário
        Schema::table('crm_negotiation_tasks', function (Blueprint $table): void {
            $table->dropForeign(['crm_negotiation_id']);
            $table->dropForeign(['auth_user_id']);
            $table->foreign('crm_negotiation_id')->references('id')->on('crm_negotiations')->onDelete('cascade');
            $table->foreign('auth_user_id')->references('id')->on('auth_users')->onDelete('set null');
        });

        // crm_negotiation_products → CASCADE na negociação, SET NULL no produto
        Schema::table('crm_negotiation_products', function (Blueprint $table): void {
            $table->dropForeign(['crm_negotiation_id']);
            $table->dropForeign(['crm_product_id']);
            $table->foreign('crm_negotiation_id')->references('id')->on('crm_negotiations')->onDelete('cascade');
            $table->foreign('crm_product_id')->references('id')->on('crm_products')->onDelete('set null');
        });

        // crm_negotiation_tags (pivot) → CASCADE ambos os lados
        Schema::table('crm_negotiation_tags', function (Blueprint $table): void {
            $table->dropForeign(['crm_negotiation_id']);
            $table->dropForeign(['crm_tag_id']);
            $table->foreign('crm_negotiation_id')->references('id')->on('crm_negotiations')->onDelete('cascade');
            $table->foreign('crm_tag_id')->references('id')->on('crm_tags')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('crm_negotiation_tags', function (Blueprint $table): void {
            $table->dropForeign(['crm_negotiation_id']);
            $table->dropForeign(['crm_tag_id']);
            $table->foreignUuid('crm_negotiation_id')->constrained('crm_negotiations');
            $table->foreignUuid('crm_tag_id')->constrained('crm_tags');
        });

        Schema::table('crm_negotiation_products', function (Blueprint $table): void {
            $table->dropForeign(['crm_negotiation_id']);
            $table->dropForeign(['crm_product_id']);
            $table->foreignUuid('crm_negotiation_id')->constrained('crm_negotiations');
            $table->foreignUuid('crm_product_id')->nullable()->constrained('crm_products');
        });

        Schema::table('crm_negotiation_tasks', function (Blueprint $table): void {
            $table->dropForeign(['crm_negotiation_id']);
            $table->dropForeign(['auth_user_id']);
            $table->foreignUuid('crm_negotiation_id')->constrained('crm_negotiations');
            $table->foreignUuid('auth_user_id')->nullable()->constrained('auth_users');
        });

        Schema::table('crm_negotiations', function (Blueprint $table): void {
            $table->dropForeign(['crm_company_id']);
            $table->dropForeign(['crm_contact_id']);
            $table->dropForeign(['crm_negotiation_funnel_id']);
            $table->dropForeign(['crm_negotiation_funnel_step_id']);
            $table->dropForeign(['crm_reason_loss_id']);
            $table->dropForeign(['auth_user_id']);
            $table->foreignUuid('crm_company_id')->nullable()->constrained('crm_companies');
            $table->foreignUuid('crm_contact_id')->constrained('crm_contacts');
            $table->foreignUuid('crm_negotiation_funnel_id')->constrained('crm_negotiation_funnels');
            $table->foreignUuid('crm_negotiation_funnel_step_id')->constrained('crm_negotiation_funnel_steps');
            $table->foreignUuid('crm_reason_loss_id')->nullable()->constrained('crm_reason_losses');
            $table->foreignUuid('auth_user_id')->nullable()->constrained('auth_users');
        });

        Schema::table('crm_negotiation_funnel_steps', function (Blueprint $table): void {
            $table->dropForeign(['crm_negotiation_funnel_id']);
            $table->foreignUuid('crm_negotiation_funnel_id')->constrained('crm_negotiation_funnels');
        });
    }
};
