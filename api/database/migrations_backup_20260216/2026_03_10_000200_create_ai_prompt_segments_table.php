<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ai_prompt_segments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('master_id')->nullable();
            $table->string('code', 50)->unique();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->text('content');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('master_id')
                ->references('id')
                ->on('ai_prompt_masters')
                ->nullOnDelete();

            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_prompt_segments');
    }
};
