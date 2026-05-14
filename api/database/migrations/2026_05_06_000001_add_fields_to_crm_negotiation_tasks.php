<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona campos faltantes à tabela de tarefas de negociação.
 *
 * Campos necessários para o frontend (action_type, horários, notificações, etc.)
 * que não existiam na migration original.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_negotiation_tasks', function (Blueprint $table): void {
            $table->string('action_type', 50)->nullable()->after('description')->comment('Tipo de ação: call, email, whatsapp, meeting, etc.');
            $table->time('start_time')->nullable()->after('due_date')->comment('Horário de início da tarefa');
            $table->time('end_time')->nullable()->after('start_time')->comment('Horário de término da tarefa');
            $table->timestamp('reminder_at')->nullable()->after('end_time')->comment('Data/hora do lembrete');
            $table->boolean('add_to_agenda')->default(false)->after('reminder_at')->comment('Se deve adicionar à agenda');
            $table->foreignUuid('agenda_event_id')->nullable()->after('add_to_agenda')->comment('Evento de agenda vinculado')->constrained('crm_events');
            $table->boolean('notify_ui')->default(false)->after('agenda_event_id')->comment('Notificação na interface');
            $table->boolean('notify_email')->default(false)->after('notify_ui')->comment('Notificação por email');
            $table->boolean('notify_push')->default(false)->after('notify_email')->comment('Notificação push');
            $table->boolean('notify_whatsapp')->default(false)->after('notify_push')->comment('Notificação WhatsApp');
            $table->boolean('is_completed')->default(false)->after('status')->comment('Se a tarefa foi concluída');
            $table->timestamp('completed_at')->nullable()->after('is_completed')->comment('Data/hora da conclusão');
            $table->string('priority', 20)->default('medium')->after('status')->comment('Prioridade: low, medium, high');
        });
    }

    public function down(): void
    {
        Schema::table('crm_negotiation_tasks', function (Blueprint $table): void {
            $table->dropForeign(['agenda_event_id']);
            $table->dropColumn([
                'action_type',
                'start_time',
                'end_time',
                'reminder_at',
                'add_to_agenda',
                'agenda_event_id',
                'notify_ui',
                'notify_email',
                'notify_push',
                'notify_whatsapp',
                'is_completed',
                'completed_at',
                'priority',
            ]);
        });
    }
};
