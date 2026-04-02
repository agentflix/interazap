<?php

declare(strict_types=1);

namespace Domain\Chat\Jobs;

use Domain\Chat\Services\ChatChatbotResponder;
use Domain\Shared\Concerns\HasJobDefaults;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Executes Chatbot response asynchronously.
 */
final class ChatChatbotRespondJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use HasJobDefaults;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Unique lock time window.
     */
    public int $uniqueFor = 60;

    /**
     * @param  string  $tenantId  Tenant identifier.
     * @param  string  $ticketId  Ticket identifier.
     * @param  string  $body  Incoming message body.
     * @param  bool  $isFirstInteraction  Whether this is the first ticket interaction.
     */
    public function __construct(
        private readonly string $tenantId,
        private readonly string $ticketId,
        private readonly string $body,
        private readonly bool $isFirstInteraction = false,
    ) {
        $this->onQueue('chatbot');
    }

    /**
     * The unique ID of the job.
     */
    public function uniqueId(): string
    {
        return $this->ticketId.'|'.sha1($this->body);
    }

    /**
     * Handle chatbot response generation.
     */
    public function handle(ChatChatbotResponder $responder): void
    {
        $responder->respond(
            $this->tenantId,
            $this->ticketId,
            $this->body,
            $this->isFirstInteraction,
        );
    }
}
