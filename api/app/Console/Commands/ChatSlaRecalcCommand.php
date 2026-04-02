<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Chat\Models\ChatTicketExtended;
use Illuminate\Console\Command;

/**
 * Recalcula SLA e marca brechas nos tickets de atendimento.
 *
 * Consulta a tabela chat_tickets_extended (onde os campos SLA residem)
 * fazendo JOIN com chat_tickets para filtrar por status.
 */
class ChatSlaRecalcCommand extends Command
{
    protected $signature = 'chat:sla-recalc';

    protected $description = 'Recalcula SLA de tickets e marca brechas';

    /**
     * Executa o recalculo de SLA.
     */
    public function handle(): int
    {
        $now = now();

        // SLA columns now live in chat_tickets_extended; JOIN with chat_tickets for status filter
        $firstResponseUpdated = ChatTicketExtended::query()
            ->join('chat_tickets', 'chat_tickets.id', '=', 'chat_tickets_extended.ticket_id')
            ->where('chat_tickets.status', '!=', 'closed')
            ->whereNotNull('chat_tickets_extended.sla_first_response_due_at')
            ->where('chat_tickets_extended.sla_first_response_due_at', '<', $now)
            ->where('chat_tickets_extended.sla_first_response_breached', false)
            ->update(['sla_first_response_breached' => true]);

        $resolutionUpdated = ChatTicketExtended::query()
            ->join('chat_tickets', 'chat_tickets.id', '=', 'chat_tickets_extended.ticket_id')
            ->where('chat_tickets.status', '!=', 'closed')
            ->whereNotNull('chat_tickets_extended.sla_resolution_due_at')
            ->where('chat_tickets_extended.sla_resolution_due_at', '<', $now)
            ->where('chat_tickets_extended.sla_resolution_breached', false)
            ->update(['sla_resolution_breached' => true]);

        $this->info("SLA recalculado: {$firstResponseUpdated} first response, {$resolutionUpdated} resolution breaches");

        return self::SUCCESS;
    }
}
