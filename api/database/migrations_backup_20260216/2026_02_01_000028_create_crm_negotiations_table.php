<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_negotiations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('platform_tenants')->cascadeOnDelete();
            $table->foreignUuid('crm_company_id')->nullable()->constrained('crm_companies')->nullOnDelete();
            $table->foreignUuid('crm_contact_id')->nullable()->constrained('crm_contacts')->nullOnDelete();
            $table->foreignUuid('crm_negotiation_funnel_id')->constrained('crm_negotiation_funnels')->cascadeOnDelete();
            $table->foreignUuid('crm_negotiation_funnel_step_id')->constrained('crm_negotiation_funnel_steps')->cascadeOnDelete();
            $table->foreignUuid('crm_reason_loss_id')->nullable()->constrained('crm_reason_losses')->nullOnDelete();
            $table->string('title');
            $table->decimal('amount', 12, 2)->default(0);
            $table->integer('position')->default(0);
            $table->string('status')->default('open');
            $table->date('expected_close')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_negotiations');
    }
};
