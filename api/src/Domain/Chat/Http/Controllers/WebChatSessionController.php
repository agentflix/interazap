<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Models\ChatSession;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatActivityBroadcastService;
use Domain\Chat\Services\WebChatJwtService;
use Domain\CRM\Models\CRMContact;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Controlador de Sessões Webchat.
 *
 * Gerencia a criação e recuperação de sessões webchat para visitantes,
 * incluindo criação automática de contato e ticket.
 *
 * @category Controllers
 */
final class WebChatSessionController extends BaseController
{
    public function __construct(
        private readonly WebChatJwtService $jwtService,
        private readonly ChatActivityBroadcastService $activityBroadcast,
    ) {}

    /**
     * Criar ou buscar sessão webchat existente.
     *
     * POST /api/webchat/sessions
     *
     * @return JsonResponse Session bootstrap payload.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateStoreRequest($request);

        $tenantId = $validated['tenant_id'];
        $visitorName = $validated['visitor_name'];
        $visitorEmail = $validated['visitor_email'];
        $visitorPhone = $validated['visitor_phone'];
        $clientInfo = $validated['client_info'];

        // Tentar encontrar sessão ativa pelo token (idempotência)
        if (isset($validated['token']) && $validated['token'] !== '') {
            $tokenPayload = $this->jwtService->validateToken($validated['token']);

            $existingSession = ChatSession::query()
                ->when(
                    is_array($tokenPayload),
                    fn ($query) => $query->where('id', (string) ($tokenPayload['session_id'] ?? '')),
                    fn ($query) => $query->where('token', $validated['token'])
                )
                ->where('tenant_id', $tenantId)
                ->first();

            if ($existingSession) {
                $existingSession->touchLastActivity();
                $existingSession->loadMissing(['contact', 'ticket']);

                $token = $this->jwtService->generateToken(
                    sessionId: (string) $existingSession->id,
                    tenantId: (string) $existingSession->tenant_id,
                    contactId: (string) $existingSession->contact_id,
                    ticketId: (string) $existingSession->ticket_id,
                );

                return $this->success([
                    'token' => $token,
                    'sessionId' => (string) $existingSession->id,
                    'ticketId' => (string) $existingSession->ticket_id,
                    'contactName' => $existingSession->contact?->name ?? $visitorName,
                    'contactPhone' => $existingSession->contact?->phone ?? $visitorPhone,
                    'protocol' => $existingSession->ticket?->protocol ?? '',
                ], 'Sessão recuperada', 200);
            }
        }

        // Encontrar ou criar contato
        $contactId = $this->findOrCreateContact($tenantId, $visitorName, $visitorEmail, $visitorPhone);

        // Encontrar ou criar ticket webchat
        $ticket = $this->findOrCreateTicket($tenantId, $contactId, $visitorName, $clientInfo);

        // Criar sessão
        $session = ChatSession::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'contact_id' => $contactId,
            'ticket_id' => (string) $ticket->id,
            'token' => (string) Str::uuid(),
            'client_info' => $clientInfo,
            'last_activity_at' => now(),
        ]);

        $token = $this->jwtService->generateToken(
            sessionId: (string) $session->id,
            tenantId: (string) $session->tenant_id,
            contactId: (string) $session->contact_id,
            ticketId: (string) $session->ticket_id,
        );

        Log::info('[WebChat] Nova sessão criada', [
            'session_id' => (string) $session->id,
            'ticket_id' => (string) $ticket->id,
            'contact_id' => $contactId,
            'tenant_id' => $tenantId,
        ]);

        return $this->created([
            'token' => $token,
            'sessionId' => (string) $session->id,
            'ticketId' => (string) $ticket->id,
            'contactName' => $visitorName,
            'contactPhone' => $visitorPhone,
            'protocol' => $ticket->protocol ?? '',
        ], 'Sessão criada');
    }

    /**
     * Obter sessão webchat por ID.
     *
     * GET /api/webchat/sessions/{id}
     *
     * @param  string  $id  UUID da sessão
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $token = $request->query('token');
        if (! is_string($token) || $token === '') {
            return $this->unauthorized('Token inválido ou expirado');
        }

        $payload = $this->jwtService->validateToken($token);
        if ($payload === null) {
            return $this->unauthorized('Token inválido ou expirado');
        }

        $sessionId = $payload['session_id'] ?? null;
        $tenantId = $payload['tenant_id'] ?? null;
        $ticketId = $payload['ticket_id'] ?? null;

        if (
            ! is_string($sessionId) || $sessionId === ''
            || ! is_string($tenantId) || $tenantId === ''
            || ! is_string($ticketId) || $ticketId === ''
        ) {
            return $this->unauthorized('Token inválido: claims incompletas');
        }

        if ($sessionId !== $id) {
            return $this->unauthorized('Sessão não corresponde ao token');
        }

        $session = ChatSession::query()
            ->where('id', $sessionId)
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $ticketId)
            ->with(['contact', 'ticket.contact'])
            ->first();

        if (! $session) {
            return $this->notFound('Sessão não encontrada');
        }

        $session->touchLastActivity();

        return $this->success([
            'id' => (string) $session->id,
            'ticketId' => (string) $session->ticket_id,
            'contactId' => $session->contact_id,
            'clientInfo' => $session->client_info,
            'lastActivityAt' => $session->last_activity_at?->toIso8601String(),
            'createdAt' => $session->created_at?->toIso8601String(),
            'contact' => $session->relationLoaded('contact') && $session->contact ? [
                'id' => (string) $session->contact->id,
                'name' => $session->contact->name,
                'email' => $session->contact->email,
                'phone' => $session->contact->phone,
            ] : null,
            'ticket' => $session->relationLoaded('ticket') && $session->ticket ? [
                'id' => (string) $session->ticket->id,
                'status' => $session->ticket->status,
                'protocol' => $session->ticket->protocol,
            ] : null,
        ], 'Sessão obtida');
    }

    /**
     * Validar request de criação de sessão.
     *
     * @return array<string, mixed>
     */
    private function validateStoreRequest(Request $request): array
    {
        $tenantId = $request->input('tenant_id');
        if (! is_string($tenantId) || $tenantId === '') {
            abort(400, 'tenant_id é obrigatório');
        }

        return [
            'tenant_id' => $tenantId,
            'token' => $request->input('token'),
            'visitor_name' => $request->input('visitor_name', 'Visitante Web'),
            'visitor_email' => $request->input('visitor_email'),
            'visitor_phone' => $request->input('visitor_phone'),
            'client_info' => $request->input('client_info') ? (array) $request->input('client_info') : null,
        ];
    }

    /**
     * Encontrar ou criar contato para o visitante webchat dentro de uma transação.
     */
    private function findOrCreateContact(
        string $tenantId,
        string $name,
        ?string $email,
        ?string $phone,
    ): ?string {
        return DB::transaction(function () use ($tenantId, $name, $email, $phone): ?string {
            $cleanPhone = $phone ? preg_replace('/[^0-9]/', '', $phone) : null;

            if ($email || $cleanPhone) {
                $contact = CRMContact::query()
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->where(function ($q) use ($email, $cleanPhone): void {
                        if ($email) {
                            $q->orWhere('email', $email);
                        }
                        if ($cleanPhone) {
                            $q->orWhere('phone', 'like', "%{$cleanPhone}%");
                            $q->orWhere('whatsapp', 'like', "%{$cleanPhone}%");
                        }
                    })
                    ->lockForUpdate()
                    ->first();

                if ($contact) {
                    return $contact->id;
                }
            }

            $newContact = CRMContact::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'is_active' => true,
            ]);

            return $newContact->id;
        });
    }

    /**
     * Encontrar ticket webchat ativo ou criar novo.
     */
    private function findOrCreateTicket(
        string $tenantId,
        ?string $contactId,
        string $visitorName,
        ?array $clientInfo,
    ): ChatTicket {
        // Buscar ticket webchat ativo mais recente do contato
        if ($contactId) {
            $existingTicket = ChatTicket::query()
                ->where('tenant_id', $tenantId)
                ->where('contact_id', $contactId)
                ->where('channel', 'web')
                ->whereIn('status', ['pending', 'open', 'in_progress'])
                ->orderByDesc('created_at')
                ->first();

            if ($existingTicket) {
                $existingTicket->last_message_at = now();
                $existingTicket->save();

                return $existingTicket;
            }
        }

        // Criar novo ticket
        $ticketNumber = $this->generateTicketNumber($tenantId);

        $ticket = ChatTicket::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'contact_id' => $contactId,
            'channel' => 'web',
            'protocol' => $ticketNumber,
            'status' => 'pending',
            'push_name' => $visitorName,
            'metadata' => ['source' => 'webchat', 'client_info' => $clientInfo],
            'last_message_at' => now(),
        ]);

        // Broadcast ticket.new para que apareça na lista do atendente em tempo real
        $ticket->load(['contact']);
        $this->activityBroadcast->emit(
            (string) $ticket->id,
            [
                [
                    'type' => 'ticket.new',
                    'data' => [
                        'ticket_id' => (string) $ticket->id,
                        'tenant_id' => (string) $tenantId,
                        'ticket' => $ticket->toArray(),
                    ],
                ],
            ],
            (string) $tenantId,
        );

        return $ticket;
    }

    /**
     * Gerar número de protocolo sequencial para ticket webchat.
     */
    private function generateTicketNumber(string $tenantId): string
    {
        $prefix = 'WC-';
        $date = now()->format('ymd');

        $lastTicket = ChatTicket::query()
            ->where('tenant_id', $tenantId)
            ->where('protocol', 'like', $prefix.$date.'%')
            ->orderByDesc('protocol')
            ->first();

        if ($lastTicket && preg_match('/^WC-\d{6}(\d{4})$/', (string) $lastTicket->protocol, $matches)) {
            $nextSeq = (int) $matches[1] + 1;

            return $prefix.$date.str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
        }

        return $prefix.$date.'0001';
    }
}
