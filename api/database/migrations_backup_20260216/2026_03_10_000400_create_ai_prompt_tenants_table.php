<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create the enum type for validation status (PostgreSQL only)
        if (DB::connection()->getDriverName() === 'pgsql') {
            // Drop type if exists to handle migrate:fresh scenarios
            DB::statement('DROP TYPE IF EXISTS ai_prompt_validation_status CASCADE');
            DB::statement("CREATE TYPE ai_prompt_validation_status AS ENUM ('pending', 'approved', 'rejected', 'quarantine')");
        }

        Schema::create('ai_prompt_tenants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->unique();
            $table->uuid('segment_id');
            $table->text('content');
            $table->text('previous_content')->nullable();
            $table->integer('version')->default(1);
            $table->string('validation_status', 20)->default('pending');
            $table->string('validated_hash', 64)->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->text('guardian_analysis')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->cascadeOnDelete();

            $table->foreign('segment_id')
                ->references('id')
                ->on('ai_prompt_segments')
                ->restrictOnDelete();

            $table->index('validation_status');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_tenants');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP TYPE IF EXISTS ai_prompt_validation_status');
        }
    }
};
