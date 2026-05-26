<?php

declare(strict_types=1);

namespace Domain\Ai\Tools;

use Domain\Ai\Contracts\AiToolInterface;
use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;
use Domain\Ai\Enums\AiNotificationChannel;
use Domain\Ai\Enums\AiNotificationReason;
use Domain\Ai\Models\AiSellerNotification;
use Domain\Ai\Services\AiToolEntityResolver;
use Domain\Chat\Actions\SendChatMessageAction;
use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\CRM\Models\CRMNegotiationTask;
use Domain\CRM\Models\CRMNote;
use Illuminate\Support\Str;

/**
 * Ferramenta de IA para registrar interesse comercial e acionar o time de vendas.
 *
 * Input esperado: message_to_customer (obrigatório); ticket_id, plan, team_size, urgency, intent, seller_id e negotiation_id opcionais.
 * Output produzido: ticket_id, message_id, notification_id, seller_id, negotiation_id e avisos recuperáveis.
 * Quando usar: cliente demonstrar interesse em adquirir um plano ou solicitar contato comercial.
 * Resolve vendedor e negociação automaticamente; cria artefatos de acompanhamento e envia mensagem ao cliente.
 */
final class RegisterSalesInterestTool implements AiToolInterface
{
    public function __construct(
        private readonly AiToolEntityResolver $entityResolver,
        private readonly SendChatMessageAction $sendMessageAction,
    ) {}

    /** Executa o registro de interesse comercial e cria todos os artefatos de acompanhamento. */
    public function handle(ToolInputDTO $input): ToolResultDTO
    {
        $tenantId = (string) ($input->context['tenant_id'] ?? '');
        $ticketId = (string) ($input->parameters['ticket_id'] ?? $input->context['ticket_id'] ?? '');

        if ($tenantId === '') {
            return ToolResultDTO::failure('Tenant ID is required');
        }

        if ($ticketId === '' || ! Str::isUuid($ticketId)) {
            return ToolResultDTO::failure('Ticket ID is required', [
                'error_code' => 'ticket_id_required',
                'recoverable' => true,
            ]);
        }

        $ticket = ChatTicket::query()
            ->where('tenant_id', $tenantId)
            ->with('contact')
            ->find($ticketId);

        if (! $ticket instanceof ChatTicket) {
            return ToolResultDTO::failure('Ticket not found', [
                'error_code' => 'ticket_not_found',
                'recoverable' => false,
            ]);
        }

        $messageToCustomer = trim((string) ($input->parameters['message_to_customer'] ?? ''));
        if ($messageToCustomer === '') {
            $messageToCustomer = 'Perfeito, registrei seu interesse e vou acionar nosso time comercial para continuar com você.';
        }

        $seller = $this->entityResolver->resolveSeller($tenantId, $input->parameters, $ticketId);
        $negotiation = $this->entityResolver->resolveNegotiation(
            $tenantId,
            $input->parameters,
            $ticketId,
            $ticket->contact_id ? (string) $ticket->contact_id : null,
        );
        $createdNegotiation = false;

        if ($negotiation === null && $ticket->contact_id !== null) {
            $negotiation = $this->createNegotiationFromTicket($tenantId, $input->parameters, $ticket, $seller?->id);
            $createdNegotiation = true;
        }

        $notification = null;
        if ($seller !== null) {
            $notification = AiSellerNotification::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'seller_id' => (string) $seller->id,
                'notifiable_type' => $negotiation ? $negotiation::class : ChatTicket::class,
                'notifiable_id' => $negotiation ? (string) $negotiation->id : (string) $ticket->id,
                'message' => $this->buildSellerMessage($input->parameters, $ticket),
                'reason' => AiNotificationReason::HOT_LEAD->value,
                'channel' => AiNotificationChannel::EMAIL->value,
                'priority' => (string) ($input->parameters['priority'] ?? 'high'),
                'metadata' => [
                    'source' => 'autopilot_tool',
                    'tool' => $this->getName(),
                    'plan' => $input->parameters['plan'] ?? null,
                    'team_size' => $input->parameters['team_size'] ?? null,
                    'urgency' => $input->parameters['urgency'] ?? null,
                    'intent' => $input->parameters['intent'] ?? null,
                ],
            ]);
        }

        $task = null;
        if ($negotiation !== null) {
            $task = CRMNegotiationTask::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'crm_negotiation_id' => (string) $negotiation->id,
                'auth_user_id' => $seller?->id,
                'title' => 'Follow-up comercial: '.($input->parameters['plan'] ?? 'InteraZap'),
                'description' => $this->buildSellerMessage($input->parameters, $ticket),
                'due_date' => now()->addDay(),
                'status' => 'pending',
            ]);
        } elseif ($ticket->contact_id !== null) {
            CRMNote::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'entity_type' => 'contact',
                'entity_id' => (string) $ticket->contact_id,
                'content' => $this->buildSellerMessage($input->parameters, $ticket),
                'auth_user_id' => null,
            ]);
        }

        $message = $this->sendMessageAction->create($tenantId, ChatMessageDTO::fromArray([
            'ticket_id' => (string) $ticket->id,
            'contact_id' => $ticket->contact_id ? (string) $ticket->contact_id : null,
            'content' => $messageToCustomer,
            'type' => 'text',
            'direction' => 'outgoing',
            'is_from_contact' => false,
            'source' => 'ai',
            'metadata' => ['tool' => $this->getName()],
        ]));

        return ToolResultDTO::success('Sales interest registered successfully', [
            'ticket_id' => (string) $ticket->id,
            'message_id' => (string) $message->id,
            'notification_id' => $notification ? (string) $notification->id : null,
            'seller_id' => $seller ? (string) $seller->id : null,
            'negotiation_id' => $negotiation ? (string) $negotiation->id : null,
            'negotiation_created' => $createdNegotiation,
            'task_id' => $task ? (string) $task->id : null,
            'recoverable_warnings' => [
                ...($seller === null ? ['seller_not_found'] : []),
                ...($negotiation === null ? ['negotiation_not_found'] : []),
            ],
        ]);
    }

    /** Retorna o nome único da ferramenta. */
    public function getName(): string
    {
        return \Domain\Ai\Enums\AiToolEnum::REGISTER_SALES_INTEREST;
    }

    /** Retorna a descrição da ferramenta para o LLM. */
    public function getDescription(): string
    {
        return 'Registers a sales interest, resolves the seller/negotiation when possible, creates follow-up artifacts, and always sends a customer-facing message.';
    }

    /**
     * Retorna os parâmetros esperados pela ferramenta.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getParameters(): array
    {
        return [
            'ticket_id' => ['type' => 'string', 'required' => false, 'description' => 'Ticket UUID. Defaults to current tool context ticket_id.'],
            'plan' => ['type' => 'string', 'required' => false, 'description' => 'Interested plan name, e.g. Starter, Professional, Business.'],
            'team_size' => ['type' => 'integer', 'required' => false, 'description' => 'Number of attendants/users mentioned by the customer.'],
            'urgency' => ['type' => 'string', 'required' => false, 'description' => 'Customer urgency, e.g. this_week, today, no_urgency.'],
            'intent' => ['type' => 'string', 'required' => false, 'description' => 'Commercial intent, e.g. close_deal, schedule_demo, ask_proposal.'],
            'message_to_customer' => ['type' => 'string', 'required' => true, 'description' => 'Message that must be sent to the customer after registering interest.'],
            'seller' => ['type' => 'string', 'required' => false, 'description' => 'Optional seller name or alias.'],
            'seller_id' => ['type' => 'string', 'required' => false, 'description' => 'Optional seller UUID.'],
            'negotiation_id' => ['type' => 'string', 'required' => false, 'description' => 'Optional negotiation UUID.'],
        ];
    }

    /**
     * Monta a mensagem interna de notificação para o vendedor.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function buildSellerMessage(array $parameters, ChatTicket $ticket): string
    {
        $parts = [
            'Cliente demonstrou interesse comercial no InteraZap.',
            'Ticket: '.(string) ($ticket->protocol ?? $ticket->id),
        ];

        foreach (['plan' => 'Plano', 'team_size' => 'Tamanho do time', 'urgency' => 'Urgência', 'intent' => 'Intenção'] as $key => $label) {
            if (isset($parameters[$key]) && trim((string) $parameters[$key]) !== '') {
                $parts[] = "{$label}: ".(string) $parameters[$key];
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Cria automaticamente uma negociação a partir do ticket quando nenhuma existir.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function createNegotiationFromTicket(
        string $tenantId,
        array $parameters,
        ChatTicket $ticket,
        ?string $sellerId,
    ): CRMNegotiation {
        $step = CRMNegotiationFunnelStep::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('order')
            ->first();

        if (! $step instanceof CRMNegotiationFunnelStep) {
            $funnel = CRMNegotiationFunnel::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'name' => 'Comercial',
                'description' => 'Funil criado automaticamente para oportunidades do Autopilot.',
                'is_active' => true,
            ]);

            $step = CRMNegotiationFunnelStep::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'crm_negotiation_funnel_id' => (string) $funnel->id,
                'name' => 'Novo lead',
                'color' => '#3B82F6',
                'is_active' => true,
                'order' => 1,
            ]);
        }

        $plan = trim((string) ($parameters['plan'] ?? 'InteraZap'));
        $title = 'Interesse comercial - '.$plan;
        $amount = $this->estimateAmountFromPlan($plan);

        return CRMNegotiation::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'auth_user_id' => $sellerId,
            'crm_contact_id' => (string) $ticket->contact_id,
            'crm_negotiation_funnel_id' => (string) $step->crm_negotiation_funnel_id,
            'crm_negotiation_funnel_step_id' => (string) $step->id,
            'title' => $title,
            'amount' => $amount,
            'notes' => $this->buildSellerMessage($parameters, $ticket),
            'status' => 'open',
            'lead_score' => 80,
            'position' => 0,
        ]);
    }

    private function estimateAmountFromPlan(string $plan): float
    {
        return match (Str::lower(Str::ascii($plan))) {
            'starter' => 197.0,
            'professional', 'profissional' => 297.0,
            'business' => 497.0,
            default => 0.0,
        };
    }
}
