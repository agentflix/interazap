<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Ai\Models\AiAutopilotTool;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seed tenant-scoped autopilot tools based on implemented AI tools.
 */
class AiAutopilotToolSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = PlatformTenant::query()->limit(100)->get();

        if ($tenants->isEmpty()) {
            $this->command->warn('No tenants found. Skipping AiAutopilotToolSeeder.');

            return;
        }

        $tools = [
            [
                'handler_class' => 'SearchKnowledgeTool',
                'display_name' => 'Buscar Conhecimento',
                'description' => 'Search knowledge base for relevant info',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Search query to find relevant knowledge',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of results (default: 5)',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'handler_class' => 'SendMessageTool',
                'display_name' => 'Enviar Mensagem',
                'description' => 'Send message in support ticket',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => [
                            'type' => 'string',
                            'description' => 'UUID of the ticket to send the message to',
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Content of the message',
                        ],
                        'type' => [
                            'type' => 'string',
                            'description' => 'Type of message: text, image, audio, document',
                        ],
                    ],
                    'required' => ['ticket_id', 'content'],
                ],
            ],
            [
                'handler_class' => 'ReadTicketTool',
                'display_name' => 'Ler Ticket',
                'description' => 'Read ticket details and history',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => [
                            'type' => 'string',
                            'description' => 'UUID of the ticket to read',
                        ],
                        'include_messages' => [
                            'type' => 'boolean',
                            'description' => 'Whether to include conversation history',
                        ],
                        'message_limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of messages to return (default: 20)',
                        ],
                    ],
                    'required' => ['ticket_id'],
                ],
            ],
            [
                'handler_class' => 'CloseTicketTool',
                'display_name' => 'Fechar Ticket',
                'description' => 'Close a resolved ticket',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => [
                            'type' => 'string',
                            'description' => 'UUID of the ticket to close',
                        ],
                        'reason' => [
                            'type' => 'string',
                            'description' => 'Reason for closing: resolved, abandoned, spam, duplicate',
                        ],
                        'summary' => [
                            'type' => 'string',
                            'description' => 'Summary of the resolution or conversation',
                        ],
                    ],
                    'required' => ['ticket_id', 'reason'],
                ],
            ],
            [
                'handler_class' => 'TransferToHumanTool',
                'display_name' => 'Transferir para Humano',
                'description' => 'Escalate to human agent',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'ticket_id' => [
                            'type' => 'string',
                            'description' => 'UUID of the ticket to transfer',
                        ],
                        'reason' => [
                            'type' => 'string',
                            'description' => 'Reason for the transfer',
                        ],
                        'priority' => [
                            'type' => 'string',
                            'description' => 'Priority level: low, normal, high, urgent',
                        ],
                    ],
                    'required' => ['ticket_id', 'reason'],
                ],
            ],
            [
                'handler_class' => 'DelegateToAgentTool',
                'display_name' => 'Delegar para Agente',
                'description' => 'Delegate execution to another AI agent',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'target_agent_id' => [
                            'type' => 'string',
                            'description' => 'Name or UUID of the target agent (e.g. "Vendas", "Suporte", "Qualificacao", "Reativacao")',
                        ],
                        'target_playbook_id' => [
                            'type' => 'string',
                            'description' => 'Optional target playbook UUID for child run',
                        ],
                        'return_after' => [
                            'type' => 'boolean',
                            'description' => 'If true, parent waits and consumes child result',
                        ],
                    ],
                    'required' => ['target_agent_id'],
                ],
            ],
            [
                'handler_class' => 'NotifySellerTool',
                'display_name' => 'Notificar Vendedor',
                'description' => 'Notify seller about event',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'seller_id' => [
                            'type' => 'string',
                            'description' => 'UUID of the seller to notify',
                        ],
                        'message' => [
                            'type' => 'string',
                            'description' => 'Notification message content',
                        ],
                        'reason' => [
                            'type' => 'string',
                            'description' => 'Reason for notification: high_value_lead, escalation, urgent_response, appointment_set, follow_up_reminder, general',
                        ],
                        'channel' => [
                            'type' => 'string',
                            'description' => 'Delivery channel: email (default), whatsapp',
                        ],
                        'priority' => [
                            'type' => 'string',
                            'description' => 'Priority level: low, normal, high, urgent',
                        ],
                    ],
                    'required' => ['seller_id', 'message', 'reason'],
                ],
            ],
            [
                'handler_class' => 'CreateNoteTool',
                'display_name' => 'Criar Nota',
                'description' => 'Create internal note on ticket',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'entity_type' => [
                            'type' => 'string',
                            'description' => 'Type of entity: contact, negotiation',
                        ],
                        'entity_id' => [
                            'type' => 'string',
                            'description' => 'UUID of the entity',
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'Content of the note',
                        ],
                    ],
                    'required' => ['entity_type', 'entity_id', 'content'],
                ],
            ],
            [
                'handler_class' => 'CreateTaskTool',
                'display_name' => 'Criar Tarefa',
                'description' => 'Create task for a contact',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Title of the task',
                        ],
                        'description' => [
                            'type' => 'string',
                            'description' => 'Detailed description of the task',
                        ],
                        'due_date' => [
                            'type' => 'string',
                            'description' => 'Due date in ISO 8601 format',
                        ],
                        'negotiation_id' => [
                            'type' => 'string',
                            'description' => 'UUID of the negotiation',
                        ],
                        'assigned_to' => [
                            'type' => 'string',
                            'description' => 'UUID of the user to assign the task to',
                        ],
                    ],
                    'required' => ['title', 'negotiation_id'],
                ],
            ],
            [
                'handler_class' => 'UpdateContactTagsTool',
                'display_name' => 'Atualizar Tags do Contato',
                'description' => 'Update contact classification tags',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => [
                            'type' => 'string',
                            'description' => 'UUID of the contact',
                        ],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Array of tag strings',
                        ],
                        'action' => [
                            'type' => 'string',
                            'description' => 'Action: add (default), remove, or replace',
                        ],
                    ],
                    'required' => ['contact_id', 'tags'],
                ],
            ],
            [
                'handler_class' => 'UpdateLeadScoreTool',
                'display_name' => 'Atualizar Score do Lead',
                'description' => 'Update lead qualification score',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'negotiation_id' => [
                            'type' => 'string',
                            'description' => 'UUID of the negotiation to update',
                        ],
                        'score' => [
                            'type' => 'integer',
                            'description' => 'New lead score (0-100)',
                        ],
                        'reason' => [
                            'type' => 'string',
                            'description' => 'Reason for the score change',
                        ],
                    ],
                    'required' => ['negotiation_id', 'score'],
                ],
            ],
            [
                'handler_class' => 'GetContactInfoTool',
                'display_name' => 'Obter Informações do Contato',
                'description' => 'Get full contact information',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => [
                            'type' => 'string',
                            'description' => 'UUID of the contact to retrieve',
                        ],
                    ],
                    'required' => ['contact_id'],
                ],
            ],
            [
                'handler_class' => 'MovePipelineTool',
                'display_name' => 'Mover no Pipeline',
                'description' => 'Move contact to pipeline stage',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'negotiation_id' => [
                            'type' => 'string',
                            'description' => 'UUID of the negotiation',
                        ],
                        'step_id' => [
                            'type' => 'string',
                            'description' => 'UUID of the target funnel step',
                        ],
                        'reason' => [
                            'type' => 'string',
                            'description' => 'Reason for moving the negotiation',
                        ],
                    ],
                    'required' => ['negotiation_id', 'step_id'],
                ],
            ],
            [
                'handler_class' => 'UpdateContactTool',
                'display_name' => 'Atualizar Contato',
                'description' => 'Update CRM contact information',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'string', 'description' => 'UUID of contact'],
                        'name' => ['type' => 'string', 'description' => 'Contact full name'],
                        'email' => ['type' => 'string', 'description' => 'Contact email'],
                        'phone' => ['type' => 'string', 'description' => 'Contact phone'],
                        'whatsapp' => ['type' => 'string', 'description' => 'Contact WhatsApp'],
                        'position' => ['type' => 'string', 'description' => 'Contact job position'],
                        'document' => ['type' => 'string', 'description' => 'Contact document'],
                        'notes' => ['type' => 'string', 'description' => 'Contact notes'],
                    ],
                    'required' => ['contact_id'],
                ],
            ],
            [
                'handler_class' => 'CreateContactTool',
                'display_name' => 'Criar Contato',
                'description' => 'Create CRM contact',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Contact full name'],
                        'phone' => ['type' => 'string', 'description' => 'Contact phone'],
                        'email' => ['type' => 'string', 'description' => 'Contact email'],
                        'whatsapp' => ['type' => 'string', 'description' => 'Contact WhatsApp'],
                        'position' => ['type' => 'string', 'description' => 'Contact position'],
                    ],
                    'required' => ['name', 'phone'],
                ],
            ],
            [
                'handler_class' => 'CreateCompanyTool',
                'display_name' => 'Criar Empresa',
                'description' => 'Create CRM company',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Company name'],
                        'document' => ['type' => 'string', 'description' => 'Company document'],
                        'email' => ['type' => 'string', 'description' => 'Company email'],
                        'phone' => ['type' => 'string', 'description' => 'Company phone'],
                        'address' => ['type' => 'string', 'description' => 'Company address'],
                        'city' => ['type' => 'string', 'description' => 'Company city'],
                        'state' => ['type' => 'string', 'description' => 'Company state'],
                    ],
                    'required' => ['name'],
                ],
            ],
            [
                'handler_class' => 'CreateNegotiationTool',
                'display_name' => 'Criar Negociação',
                'description' => 'Create CRM negotiation/deal',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Negotiation title'],
                        'amount' => ['type' => 'number', 'description' => 'Negotiation amount'],
                        'contact_id' => ['type' => 'string', 'description' => 'Contact UUID'],
                        'step_id' => ['type' => 'string', 'description' => 'Funnel step UUID'],
                    ],
                    'required' => ['title', 'step_id'],
                ],
            ],
            [
                'handler_class' => 'CloseNegotiationTool',
                'display_name' => 'Fechar Negociação',
                'description' => 'Close CRM negotiation as won/lost',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'negotiation_id' => ['type' => 'string', 'description' => 'Negotiation UUID'],
                        'outcome' => ['type' => 'string', 'description' => 'Outcome: won or lost'],
                        'reason' => ['type' => 'string', 'description' => 'Reason text'],
                        'reason_loss_id' => ['type' => 'string', 'description' => 'Reason loss UUID'],
                    ],
                    'required' => ['negotiation_id', 'outcome'],
                ],
            ],
            [
                'handler_class' => 'ScheduleEventTool',
                'display_name' => 'Agendar Evento',
                'description' => 'Schedule CRM event',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Event title'],
                        'type' => ['type' => 'string', 'description' => 'Event type'],
                        'starts_at' => ['type' => 'string', 'description' => 'Start datetime'],
                        'ends_at' => ['type' => 'string', 'description' => 'End datetime'],
                        'contact_id' => ['type' => 'string', 'description' => 'Contact UUID'],
                        'description' => ['type' => 'string', 'description' => 'Event description'],
                    ],
                    'required' => ['title', 'starts_at'],
                ],
            ],
            [
                'handler_class' => 'GetNegotiationInfoTool',
                'display_name' => 'Obter Informações da Negociação',
                'description' => 'Get full negotiation details and relations',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'negotiation_id' => ['type' => 'string', 'description' => 'Negotiation UUID'],
                    ],
                    'required' => ['negotiation_id'],
                ],
            ],
            [
                'handler_class' => 'AddProductToNegotiationTool',
                'display_name' => 'Adicionar Produto à Negociação',
                'description' => 'Add product/item to negotiation',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'negotiation_id' => ['type' => 'string', 'description' => 'Negotiation UUID'],
                        'product_id' => ['type' => 'string', 'description' => 'Product UUID'],
                        'name' => ['type' => 'string', 'description' => 'Custom item name'],
                        'qty' => ['type' => 'integer', 'description' => 'Quantity'],
                        'unit_price' => ['type' => 'number', 'description' => 'Unit price'],
                    ],
                    'required' => ['negotiation_id'],
                ],
            ],
            [
                'handler_class' => 'QualifyLeadTool',
                'display_name' => 'Qualificar Lead',
                'description' => 'Update score, tags and pipeline step in one call',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'negotiation_id' => ['type' => 'string', 'description' => 'Negotiation UUID'],
                        'score' => ['type' => 'integer', 'description' => 'Lead score 0-100'],
                        'tags' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Contact tags'],
                        'step_id' => ['type' => 'string', 'description' => 'Target step UUID'],
                    ],
                    'required' => ['negotiation_id'],
                ],
            ],
            [
                'handler_class' => 'SearchContactsTool',
                'display_name' => 'Buscar Contatos',
                'description' => 'Search contacts by phone/email/name',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Fuzzy query'],
                        'limit' => ['type' => 'integer', 'description' => 'Maximum contacts'],
                    ],
                    'required' => ['query'],
                ],
            ],
            [
                'handler_class' => 'ListFunnelStepsTool',
                'display_name' => 'Listar Etapas de Funil',
                'description' => 'List available funnels and steps',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'funnel_id' => ['type' => 'string', 'description' => 'Optional funnel UUID'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'handler_class' => 'ListProductsTool',
                'display_name' => 'Listar Produtos',
                'description' => 'List CRM products catalog',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'active_only' => ['type' => 'boolean', 'description' => 'Only active products'],
                        'limit' => ['type' => 'integer', 'description' => 'Maximum products'],
                    ],
                    'required' => [],
                ],
            ],
            [
                'handler_class' => 'CheckAvailabilityTool',
                'display_name' => 'Verificar Disponibilidade',
                'description' => 'Check calendar availability in date range',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'date_from' => ['type' => 'string', 'description' => 'Range start datetime'],
                        'date_to' => ['type' => 'string', 'description' => 'Range end datetime'],
                    ],
                    'required' => ['date_from', 'date_to'],
                ],
            ],
            [
                'handler_class' => 'UpdateCompanyTool',
                'display_name' => 'Atualizar Empresa',
                'description' => 'Update CRM company information',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'company_id' => ['type' => 'string', 'description' => 'Company UUID'],
                        'name' => ['type' => 'string', 'description' => 'Company name'],
                        'document' => ['type' => 'string', 'description' => 'Company document'],
                        'email' => ['type' => 'string', 'description' => 'Company email'],
                        'phone' => ['type' => 'string', 'description' => 'Company phone'],
                        'address' => ['type' => 'string', 'description' => 'Company address'],
                        'city' => ['type' => 'string', 'description' => 'Company city'],
                        'state' => ['type' => 'string', 'description' => 'Company state'],
                    ],
                    'required' => ['company_id'],
                ],
            ],
            [
                'handler_class' => 'CreateProposalTool',
                'display_name' => 'Criar Proposta',
                'description' => 'Create proposal with line items',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'negotiation_id' => ['type' => 'string', 'description' => 'Negotiation UUID'],
                        'title' => ['type' => 'string', 'description' => 'Proposal title'],
                        'items' => ['type' => 'array', 'description' => 'Proposal line items'],
                        'valid_until' => ['type' => 'string', 'description' => 'Expiration date'],
                    ],
                    'required' => ['negotiation_id', 'title', 'items'],
                ],
            ],
            [
                'handler_class' => 'LinkContactToCompanyTool',
                'display_name' => 'Vincular Contato à Empresa',
                'description' => 'Link existing contact to company',
                'parameters_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'contact_id' => ['type' => 'string', 'description' => 'Contact UUID'],
                        'company_id' => ['type' => 'string', 'description' => 'Company UUID'],
                    ],
                    'required' => ['contact_id', 'company_id'],
                ],
            ],
        ];

        $created = 0;

        foreach ($tenants as $tenant) {
            foreach ($tools as $toolData) {
                AiAutopilotTool::query()->updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'handler_class' => $toolData['handler_class'],
                    ],
                    [
                        'name' => Str::snake(str_replace('Tool', '', $toolData['handler_class'])),
                        'display_name' => $toolData['display_name'],
                        'description' => $toolData['description'],
                        'parameters_schema' => $toolData['parameters_schema'],
                        'is_system' => true,
                        'is_active' => true,
                    ]
                );

                $created++;
            }
        }

        $this->command->info(sprintf('AI Autopilot Tools seeded: %d', $created));
    }
}
