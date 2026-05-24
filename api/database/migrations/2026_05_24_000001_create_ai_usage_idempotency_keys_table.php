<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_idempotency_keys', function (Blueprint $table): void {
            $table->string('ai_turn_id', 36)->primary();
            $table->uuid('tenant_id')->index();
            $table->date('cycle_start');
            $table->json('result');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_idempotency_keys');
    }
};
