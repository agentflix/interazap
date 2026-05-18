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
        Schema::create('configuration_scheduling_settings', static function (Blueprint $table): void {
            $table->uuid('id')->primary()->comment('Identificador unico da configuracao');
            $table->uuid('tenant_id')->unique()->comment('Tenant proprietario');
            $table->unsignedInteger('event_confirmation_advance_minutes')->default(1440)->comment('Minutos de antecedencia para confirmacao');
            $table->boolean('event_confirmation_enabled')->default(true)->comment('Se a confirmacao esta habilitada');
            $table->boolean('event_confirmation_notify_ui')->default(true)->comment('Notificar via UI');
            $table->boolean('event_confirmation_notify_push')->default(true)->comment('Notificar via push');
            $table->timestamps();

            $table->index('tenant_id', 'idx_configuration_scheduling_settings_tenant_id');

            $table->foreign('tenant_id', 'fk_configuration_scheduling_settings_tenant_id')
                ->references('id')
                ->on('platform_tenants')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuration_scheduling_settings');
    }
};
