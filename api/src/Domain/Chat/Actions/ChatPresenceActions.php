<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\DTOs\ChatPresenceDTO;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatGatewayService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Casos de Uso para Presença de Chat.
 *
 * Implementa a comunicação com o gateway de mensageria para indicar o estado
 * de interação do atendente (typing, recording, available), permitindo
 * configurar atrasos (delay) para simular comportamento humano.
 *
 * @category Actions
 */
final class ChatPresenceActions
{
    private const MAX_DELAY_MS = 300000; // 5 minutos

    public function __construct(private readonly ChatGatewayService $gateway) {}

    /**
     * Enviar um evento de presença para o contato através do gateway vinculado ao ticket.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  ChatPresenceDTO  $dto  Dados estruturados da presença (delay, status).
     * @return array<string, mixed> Resposta bruta do gateway.
     *
     * @throws ValidationException Se não for possível determinar o token ou número do contato.
     */
    public function send(string $tenantId, ChatPresenceDTO $dto): array
    {
        $ticket = ChatTicket::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $dto->ticketId)
            ->with(['instance', 'contact'])
            ->firstOrFail();

        $token = $ticket->instance?->webhook_token;
        $number = $this->resolveNumber($ticket);

        if (! $token || ! $number) {
            throw ValidationException::withMessages([
                'ticket' => ['Token da instância ou número do contato ausente.'],
            ]);
        }

        $payload = [
            'number' => $number,
            'presence' => $dto->presence,
        ];

        if ($dto->delay !== null) {
            $payload['delay'] = min($dto->delay, self::MAX_DELAY_MS);
        }

        return $this->gateway->sendPresence($token, $payload);
    }

    /**
     * Resolver e higienizar o número de destino para envio de presença.
     *
     * @param  ChatTicket  $ticket  Ticket para extração dos dados de contato.
     * @return string|null Número limpo ou null se não resolvido.
     */
    private function resolveNumber(ChatTicket $ticket): ?string
    {
        $number = $ticket->phone_e164 ?? $ticket->phone ?? $ticket->remote_jid;

        if (! $number && $ticket->contact) {
            $number = $ticket->contact->whatsapp ?? $ticket->contact->phone;
        }

        if (! $number) {
            return null;
        }

        $number = str_contains($number, '@') ? Str::before($number, '@') : $number;
        $cleaned = preg_replace('/[^0-9]/', '', $number);
        $number = is_string($cleaned) ? $cleaned : '';

        return $number !== '' ? $number : null;
    }
}
