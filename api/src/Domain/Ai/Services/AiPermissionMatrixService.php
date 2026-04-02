<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Enums\AiAgentRole;

/**
 * Serviço de matriz de permissões Role × Tool.
 */
class AiPermissionMatrixService
{
    /**
     * Matriz de permissões: role => [tools permitidas].
     *
     * @var array<string, list<string>>
     */
    private const array MATRIX = [
        'sales_qualifier' => [
            \Domain\Ai\Enums\AiToolEnum::UPDATE_LEAD_SCORE,
            \Domain\Ai\Enums\AiToolEnum::CREATE_NOTE,
            \Domain\Ai\Enums\AiToolEnum::CREATE_TASK,
            \Domain\Ai\Enums\AiToolEnum::NOTIFY_SELLER,
            \Domain\Ai\Enums\AiToolEnum::TRANSFER_TO_HUMAN,
            // \Domain\Ai\Enums\AiToolEnum::CLOSE_TICKET => NÃO permitido
            \Domain\Ai\Enums\AiToolEnum::SEARCH_KNOWLEDGE,
            \Domain\Ai\Enums\AiToolEnum::GET_CONTACT_INFO,
            \Domain\Ai\Enums\AiToolEnum::UPDATE_CONTACT_TAGS,
            \Domain\Ai\Enums\AiToolEnum::READ_TICKET,
            \Domain\Ai\Enums\AiToolEnum::SEND_MESSAGE,
            \Domain\Ai\Enums\AiToolEnum::MOVE_PIPELINE,
            \Domain\Ai\Enums\AiToolEnum::UPDATE_CONTACT,
            \Domain\Ai\Enums\AiToolEnum::CREATE_CONTACT,
            \Domain\Ai\Enums\AiToolEnum::CREATE_COMPANY,
            \Domain\Ai\Enums\AiToolEnum::CREATE_NEGOTIATION,
            \Domain\Ai\Enums\AiToolEnum::CLOSE_NEGOTIATION,
            \Domain\Ai\Enums\AiToolEnum::SCHEDULE_EVENT,
            \Domain\Ai\Enums\AiToolEnum::GET_NEGOTIATION_INFO,
            \Domain\Ai\Enums\AiToolEnum::ADD_PRODUCT_TO_NEGOTIATION,
            \Domain\Ai\Enums\AiToolEnum::QUALIFY_LEAD,
            \Domain\Ai\Enums\AiToolEnum::SEARCH_CONTACTS,
            \Domain\Ai\Enums\AiToolEnum::LIST_FUNNEL_STEPS,
            \Domain\Ai\Enums\AiToolEnum::LIST_PRODUCTS,
            \Domain\Ai\Enums\AiToolEnum::CHECK_AVAILABILITY,
            \Domain\Ai\Enums\AiToolEnum::UPDATE_COMPANY,
            \Domain\Ai\Enums\AiToolEnum::CREATE_PROPOSAL,
            \Domain\Ai\Enums\AiToolEnum::LINK_CONTACT_TO_COMPANY,
            \Domain\Ai\Enums\AiToolEnum::DELEGATE_TO_AGENT,
        ],
        'support_l1' => [
            // \Domain\Ai\Enums\AiToolEnum::UPDATE_LEAD_SCORE => NÃO permitido
            \Domain\Ai\Enums\AiToolEnum::CREATE_NOTE,
            \Domain\Ai\Enums\AiToolEnum::CREATE_TASK,
            \Domain\Ai\Enums\AiToolEnum::NOTIFY_SELLER,
            \Domain\Ai\Enums\AiToolEnum::TRANSFER_TO_HUMAN,
            \Domain\Ai\Enums\AiToolEnum::CLOSE_TICKET,
            \Domain\Ai\Enums\AiToolEnum::SEARCH_KNOWLEDGE,
            \Domain\Ai\Enums\AiToolEnum::GET_CONTACT_INFO,
            // \Domain\Ai\Enums\AiToolEnum::UPDATE_CONTACT_TAGS => NÃO permitido
            \Domain\Ai\Enums\AiToolEnum::READ_TICKET,
            \Domain\Ai\Enums\AiToolEnum::SEND_MESSAGE,
            // \Domain\Ai\Enums\AiToolEnum::MOVE_PIPELINE => NÃO permitido
            \Domain\Ai\Enums\AiToolEnum::SCHEDULE_EVENT,
            \Domain\Ai\Enums\AiToolEnum::SEARCH_CONTACTS,
            \Domain\Ai\Enums\AiToolEnum::LIST_PRODUCTS,
            \Domain\Ai\Enums\AiToolEnum::CHECK_AVAILABILITY,
            \Domain\Ai\Enums\AiToolEnum::DELEGATE_TO_AGENT,
        ],
        'cs_retention' => [
            \Domain\Ai\Enums\AiToolEnum::UPDATE_LEAD_SCORE,
            \Domain\Ai\Enums\AiToolEnum::CREATE_NOTE,
            \Domain\Ai\Enums\AiToolEnum::CREATE_TASK,
            \Domain\Ai\Enums\AiToolEnum::NOTIFY_SELLER,
            \Domain\Ai\Enums\AiToolEnum::TRANSFER_TO_HUMAN,
            \Domain\Ai\Enums\AiToolEnum::CLOSE_TICKET,
            \Domain\Ai\Enums\AiToolEnum::SEARCH_KNOWLEDGE,
            \Domain\Ai\Enums\AiToolEnum::GET_CONTACT_INFO,
            \Domain\Ai\Enums\AiToolEnum::UPDATE_CONTACT_TAGS,
            \Domain\Ai\Enums\AiToolEnum::READ_TICKET,
            \Domain\Ai\Enums\AiToolEnum::SEND_MESSAGE,
            \Domain\Ai\Enums\AiToolEnum::MOVE_PIPELINE,
            \Domain\Ai\Enums\AiToolEnum::UPDATE_CONTACT,
            \Domain\Ai\Enums\AiToolEnum::CREATE_CONTACT,
            \Domain\Ai\Enums\AiToolEnum::CREATE_COMPANY,
            \Domain\Ai\Enums\AiToolEnum::CREATE_NEGOTIATION,
            \Domain\Ai\Enums\AiToolEnum::CLOSE_NEGOTIATION,
            \Domain\Ai\Enums\AiToolEnum::SCHEDULE_EVENT,
            \Domain\Ai\Enums\AiToolEnum::GET_NEGOTIATION_INFO,
            \Domain\Ai\Enums\AiToolEnum::ADD_PRODUCT_TO_NEGOTIATION,
            \Domain\Ai\Enums\AiToolEnum::QUALIFY_LEAD,
            \Domain\Ai\Enums\AiToolEnum::SEARCH_CONTACTS,
            \Domain\Ai\Enums\AiToolEnum::LIST_FUNNEL_STEPS,
            \Domain\Ai\Enums\AiToolEnum::LIST_PRODUCTS,
            \Domain\Ai\Enums\AiToolEnum::CHECK_AVAILABILITY,
            \Domain\Ai\Enums\AiToolEnum::UPDATE_COMPANY,
            \Domain\Ai\Enums\AiToolEnum::CREATE_PROPOSAL,
            \Domain\Ai\Enums\AiToolEnum::LINK_CONTACT_TO_COMPANY,
            \Domain\Ai\Enums\AiToolEnum::DELEGATE_TO_AGENT,
        ],
        'post_sales' => [
            // \Domain\Ai\Enums\AiToolEnum::UPDATE_LEAD_SCORE => NÃO permitido
            \Domain\Ai\Enums\AiToolEnum::CREATE_NOTE,
            \Domain\Ai\Enums\AiToolEnum::CREATE_TASK,
            \Domain\Ai\Enums\AiToolEnum::NOTIFY_SELLER,
            \Domain\Ai\Enums\AiToolEnum::TRANSFER_TO_HUMAN,
            \Domain\Ai\Enums\AiToolEnum::CLOSE_TICKET,
            \Domain\Ai\Enums\AiToolEnum::SEARCH_KNOWLEDGE,
            \Domain\Ai\Enums\AiToolEnum::GET_CONTACT_INFO,
            // \Domain\Ai\Enums\AiToolEnum::UPDATE_CONTACT_TAGS => NÃO permitido
            \Domain\Ai\Enums\AiToolEnum::READ_TICKET,
            \Domain\Ai\Enums\AiToolEnum::SEND_MESSAGE,
            // \Domain\Ai\Enums\AiToolEnum::MOVE_PIPELINE => NÃO permitido
            \Domain\Ai\Enums\AiToolEnum::UPDATE_CONTACT,
            \Domain\Ai\Enums\AiToolEnum::CREATE_CONTACT,
            \Domain\Ai\Enums\AiToolEnum::SCHEDULE_EVENT,
            \Domain\Ai\Enums\AiToolEnum::GET_NEGOTIATION_INFO,
            \Domain\Ai\Enums\AiToolEnum::SEARCH_CONTACTS,
            \Domain\Ai\Enums\AiToolEnum::LIST_PRODUCTS,
            \Domain\Ai\Enums\AiToolEnum::CHECK_AVAILABILITY,
            \Domain\Ai\Enums\AiToolEnum::DELEGATE_TO_AGENT,
        ],
        'appointment' => [
            // \Domain\Ai\Enums\AiToolEnum::UPDATE_LEAD_SCORE => NÃO permitido
            \Domain\Ai\Enums\AiToolEnum::CREATE_NOTE,
            \Domain\Ai\Enums\AiToolEnum::CREATE_TASK,
            \Domain\Ai\Enums\AiToolEnum::NOTIFY_SELLER,
            \Domain\Ai\Enums\AiToolEnum::TRANSFER_TO_HUMAN,
            // \Domain\Ai\Enums\AiToolEnum::CLOSE_TICKET => NÃO permitido
            \Domain\Ai\Enums\AiToolEnum::SEARCH_KNOWLEDGE,
            \Domain\Ai\Enums\AiToolEnum::GET_CONTACT_INFO,
            // \Domain\Ai\Enums\AiToolEnum::UPDATE_CONTACT_TAGS => NÃO permitido
            \Domain\Ai\Enums\AiToolEnum::READ_TICKET,
            \Domain\Ai\Enums\AiToolEnum::SEND_MESSAGE,
            // \Domain\Ai\Enums\AiToolEnum::MOVE_PIPELINE => NÃO permitido
            \Domain\Ai\Enums\AiToolEnum::SCHEDULE_EVENT,
            \Domain\Ai\Enums\AiToolEnum::SEARCH_CONTACTS,
            \Domain\Ai\Enums\AiToolEnum::CHECK_AVAILABILITY,
            \Domain\Ai\Enums\AiToolEnum::DELEGATE_TO_AGENT,
        ],
        'general' => [
            \Domain\Ai\Enums\AiToolEnum::UPDATE_LEAD_SCORE,
            \Domain\Ai\Enums\AiToolEnum::CREATE_NOTE,
            \Domain\Ai\Enums\AiToolEnum::CREATE_TASK,
            \Domain\Ai\Enums\AiToolEnum::NOTIFY_SELLER,
            \Domain\Ai\Enums\AiToolEnum::TRANSFER_TO_HUMAN,
            \Domain\Ai\Enums\AiToolEnum::CLOSE_TICKET,
            \Domain\Ai\Enums\AiToolEnum::SEARCH_KNOWLEDGE,
            \Domain\Ai\Enums\AiToolEnum::GET_CONTACT_INFO,
            \Domain\Ai\Enums\AiToolEnum::UPDATE_CONTACT_TAGS,
            \Domain\Ai\Enums\AiToolEnum::READ_TICKET,
            \Domain\Ai\Enums\AiToolEnum::SEND_MESSAGE,
            \Domain\Ai\Enums\AiToolEnum::MOVE_PIPELINE,
            \Domain\Ai\Enums\AiToolEnum::UPDATE_CONTACT,
            \Domain\Ai\Enums\AiToolEnum::CREATE_CONTACT,
            \Domain\Ai\Enums\AiToolEnum::CREATE_COMPANY,
            \Domain\Ai\Enums\AiToolEnum::CREATE_NEGOTIATION,
            \Domain\Ai\Enums\AiToolEnum::CLOSE_NEGOTIATION,
            \Domain\Ai\Enums\AiToolEnum::SCHEDULE_EVENT,
            \Domain\Ai\Enums\AiToolEnum::GET_NEGOTIATION_INFO,
            \Domain\Ai\Enums\AiToolEnum::ADD_PRODUCT_TO_NEGOTIATION,
            \Domain\Ai\Enums\AiToolEnum::QUALIFY_LEAD,
            \Domain\Ai\Enums\AiToolEnum::SEARCH_CONTACTS,
            \Domain\Ai\Enums\AiToolEnum::LIST_FUNNEL_STEPS,
            \Domain\Ai\Enums\AiToolEnum::LIST_PRODUCTS,
            \Domain\Ai\Enums\AiToolEnum::CHECK_AVAILABILITY,
            \Domain\Ai\Enums\AiToolEnum::UPDATE_COMPANY,
            \Domain\Ai\Enums\AiToolEnum::CREATE_PROPOSAL,
            \Domain\Ai\Enums\AiToolEnum::LINK_CONTACT_TO_COMPANY,
            \Domain\Ai\Enums\AiToolEnum::DELEGATE_TO_AGENT,
        ],
    ];

    /**
     * Lista de todas as ferramentas disponíveis.
     *
     * @var list<string>
     */
    private const array ALL_TOOLS = [
        \Domain\Ai\Enums\AiToolEnum::UPDATE_LEAD_SCORE,
        \Domain\Ai\Enums\AiToolEnum::CREATE_NOTE,
        \Domain\Ai\Enums\AiToolEnum::CREATE_TASK,
        \Domain\Ai\Enums\AiToolEnum::NOTIFY_SELLER,
        \Domain\Ai\Enums\AiToolEnum::TRANSFER_TO_HUMAN,
        \Domain\Ai\Enums\AiToolEnum::CLOSE_TICKET,
        \Domain\Ai\Enums\AiToolEnum::SEARCH_KNOWLEDGE,
        \Domain\Ai\Enums\AiToolEnum::GET_CONTACT_INFO,
        \Domain\Ai\Enums\AiToolEnum::UPDATE_CONTACT_TAGS,
        \Domain\Ai\Enums\AiToolEnum::READ_TICKET,
        \Domain\Ai\Enums\AiToolEnum::SEND_MESSAGE,
        \Domain\Ai\Enums\AiToolEnum::MOVE_PIPELINE,
        \Domain\Ai\Enums\AiToolEnum::UPDATE_CONTACT,
        \Domain\Ai\Enums\AiToolEnum::CREATE_CONTACT,
        \Domain\Ai\Enums\AiToolEnum::CREATE_COMPANY,
        \Domain\Ai\Enums\AiToolEnum::CREATE_NEGOTIATION,
        \Domain\Ai\Enums\AiToolEnum::CLOSE_NEGOTIATION,
        \Domain\Ai\Enums\AiToolEnum::SCHEDULE_EVENT,
        \Domain\Ai\Enums\AiToolEnum::GET_NEGOTIATION_INFO,
        \Domain\Ai\Enums\AiToolEnum::ADD_PRODUCT_TO_NEGOTIATION,
        \Domain\Ai\Enums\AiToolEnum::QUALIFY_LEAD,
        \Domain\Ai\Enums\AiToolEnum::SEARCH_CONTACTS,
        \Domain\Ai\Enums\AiToolEnum::LIST_FUNNEL_STEPS,
        \Domain\Ai\Enums\AiToolEnum::LIST_PRODUCTS,
        \Domain\Ai\Enums\AiToolEnum::CHECK_AVAILABILITY,
        \Domain\Ai\Enums\AiToolEnum::UPDATE_COMPANY,
        \Domain\Ai\Enums\AiToolEnum::CREATE_PROPOSAL,
        \Domain\Ai\Enums\AiToolEnum::LINK_CONTACT_TO_COMPANY,
        \Domain\Ai\Enums\AiToolEnum::DELEGATE_TO_AGENT,
    ];

    /**
     * Retorna as ferramentas disponíveis para um papel.
     *
     * @return list<string>
     */
    public function getAvailableTools(AiAgentRole $role): array
    {
        return self::MATRIX[$role->value];
    }

    /**
     * Retorna todos os nomes de ferramentas.
     *
     * @return list<string>
     */
    public function getAllToolNames(): array
    {
        return self::ALL_TOOLS;
    }
}
