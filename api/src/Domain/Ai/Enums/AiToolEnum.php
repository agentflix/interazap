<?php

declare(strict_types=1);

namespace Domain\Ai\Enums;

/**
 * Available tools/functions for AI agents.
 *
 * Defines the list of tools that can be invoked by AI agents
 * during autonomous task execution.
 */
class AiToolEnum
{
    public const ADD_PRODUCT_TO_NEGOTIATION = 'add_product_to_negotiation';

    public const CHECK_AVAILABILITY = 'check_availability';

    public const CLOSE_NEGOTIATION = 'close_negotiation';

    public const CLOSE_TICKET = 'close_ticket';

    public const CREATE_COMPANY = 'create_company';

    public const CREATE_CONTACT = 'create_contact';

    public const CREATE_NEGOTIATION = 'create_negotiation';

    public const CREATE_NOTE = 'create_note';

    public const CREATE_PROPOSAL = 'create_proposal';

    public const CREATE_TASK = 'create_task';

    public const DELEGATE_TO_AGENT = 'delegate_to_agent';

    public const GET_CONTACT_INFO = 'get_contact_info';

    public const GET_NEGOTIATION_INFO = 'get_negotiation_info';

    public const LINK_CONTACT_TO_COMPANY = 'link_contact_to_company';

    public const LIST_FUNNEL_STEPS = 'list_funnel_steps';

    public const LIST_PRODUCTS = 'list_products';

    public const MOVE_PIPELINE = 'move_pipeline';

    public const NOTIFY_SELLER = 'notify_seller';

    public const QUALIFY_LEAD = 'qualify_lead';

    public const READ_TICKET = 'read_ticket';

    public const SCHEDULE_EVENT = 'schedule_event';

    public const SEARCH_CONTACTS = 'search_contacts';

    public const SEARCH_KNOWLEDGE = 'search_knowledge';

    public const SEND_MESSAGE = 'send_message';

    public const TRANSFER_TO_HUMAN = 'transfer_to_human';

    public const UPDATE_COMPANY = 'update_company';

    public const UPDATE_CONTACT_TAGS = 'update_contact_tags';

    public const UPDATE_CONTACT = 'update_contact';

    public const UPDATE_LEAD_SCORE = 'update_lead_score';
}
