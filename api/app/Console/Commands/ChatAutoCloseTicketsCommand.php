<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Chat\Models\ChatTicket;
use Illuminate\Console\Command;

/**
 * Fecha tickets inativos após X minutos (configurado via CHAT_AUTO_CLOSE_MINUTES).
 */
class ChatAutoCloseTicketsCommand extends Command
{
    protected $signature = 'chat:auto-close';

    protected $description = 'Fecha tickets inativos após o tempo configurado';

    public function handle(): int
    {
        $minutes = (int) config('chat.auto_close_minutes', 0);
        if ($minutes <= 0) {
            $this->info('CHAT_AUTO_CLOSE_MINUTES não configurado ou <= 0. Nada a fazer.');

            return self::SUCCESS;
        }

        $cutoff = \Illuminate\Support\Facades\Date::now()->subMinutes($minutes);
        $closed = ChatTicket::query()
            ->where('status', '!=', 'closed')
            ->where(function ($q) use ($cutoff): void {
                $q->whereNull('last_message_at')->orWhere('last_message_at', '<=', $cutoff);
            })
            ->update([
                'status' => 'closed',
                'closed_at' => \Illuminate\Support\Facades\Date::now(),
                'close_reason' => 'Auto-close por inatividade',
            ]);

        $this->info("Tickets fechados por inatividade: {$closed}");

        return self::SUCCESS;
    }
}
