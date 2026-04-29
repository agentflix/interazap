<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Services\ChatActivityBroadcastService;
use Domain\Chat\Services\ChatGatewayService;
use Domain\Chat\Services\WebChatRedisPublisher;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Fachada de compatibilidade para acoes de mensagens de chat.
 *
 * @deprecated Utilize diretamente ListChatMessagesAction, SendChatMessageAction
 *             ou ProcessChatMessageAction conforme a responsabilidade.
 *
 * @category Actions
 */
final readonly class ChatMessageActions
{
    private ListChatMessagesAction $listAction;

    private SendChatMessageAction $sendAction;

    public function __construct(
        ChatGatewayService $gateway,
        ChatTicketActions $ticketActions,
        ChatActivityBroadcastService $activityBroadcast,
    ) {
        $processAction = new ProcessChatMessageAction($activityBroadcast);
        $this->listAction = new ListChatMessagesAction;
        $this->sendAction = new SendChatMessageAction(
            $gateway,
            $ticketActions,
            $processAction,
            app(WebChatRedisPublisher::class),
            app(VerifyContactWindowAction::class),
        );
    }

    /**
     * @deprecated Use ListChatMessagesAction::listByTicket()
     *
     * @param  array<string, mixed>  $filters
     */
    public function listByTicket(string $tenantId, string $ticketId, array $filters = [], bool $includeMetadata = false): LengthAwarePaginator
    {
        return $this->listAction->listByTicket($tenantId, $ticketId, $filters, $includeMetadata);
    }

    /**
     * @deprecated Use ListChatMessagesAction::listByTicketCursor()
     *
     * @param  array<string, mixed>  $filters
     * @return array{messages: \Illuminate\Support\Collection<int, ChatMessage>, meta: array<string, bool|int|string|null>}
     */
    public function listByTicketCursor(
        string $tenantId,
        string $ticketId,
        array $filters = [],
        int $limit = 30,
        ?string $after = null,
        ?string $before = null,
        ?string $since = null,
    ): array {
        return $this->listAction->listByTicketCursor($tenantId, $ticketId, $filters, $limit, $after, $before, $since);
    }

    /**
     * @deprecated Use ListChatMessagesAction::prefetchQuotedMessages()
     *
     * @param  \Illuminate\Support\Collection<int, ChatMessage>  $messages
     * @return array<string, ChatMessage>
     */
    public function prefetchQuotedMessages(\Illuminate\Support\Collection $messages): array
    {
        return $this->listAction->prefetchQuotedMessages($messages);
    }

    /**
     * @deprecated Use SendChatMessageAction::create()
     */
    public function create(string $tenantId, ChatMessageDTO $dto): ChatMessage
    {
        return $this->sendAction->create($tenantId, $dto);
    }

    /**
     * @deprecated Use SendChatMessageAction::sendContact()
     */
    public function sendContact(string $tenantId, ChatMessageDTO $dto): ChatMessage
    {
        return $this->sendAction->sendContact($tenantId, $dto);
    }

    /**
     * @deprecated Use SendChatMessageAction::sendLocation()
     */
    public function sendLocation(string $tenantId, ChatMessageDTO $dto): ChatMessage
    {
        return $this->sendAction->sendLocation($tenantId, $dto);
    }
}
