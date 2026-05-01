# Regra de Negócio: Modos de Atendimento no Chat

## Badge de Modo no Chat Ticket Item

A lista de tickets (`chat-ticket-item`) exibe um badge indicando o modo de atendimento. A lógica deve ser:

1. **Humano** (`status === 'in_progress'`): Sempre prioridade máxima. Quando um humano está atendendo, mostra "Humano".
2. **IA** (`is_bot_active === true && current_ai_agent_id !== null`): Agente de IA específico está respondendo (sticky agent).
3. **Chatbot** (`is_bot_active === true && current_ai_agent_id === null`): Automação simples/fluxo automático, sem IA.

## Campo `current_ai_agent_id`

- Adicionado em: migration `2026_04_29_000001_add_current_ai_agent_id_to_chat_tickets.php`
- Propósito: Sticky agent (quando um agente IA delega para um especialista, as mensagens seguintes vão direto ao especialista)
- Deve ser exposto no `ChatTicketResource` e consumido no frontend
- Resetado em: `transfer_to_human`, `releaseToAi`, ticket fechado

## Antipadrão a Evitar

Nunca assumir que `is_bot_active = true` significa "IA". O bot pode ser:
- Um chatbot simples (fluxo automático, respostas rápidas, sem LLM)
- Um agente de IA (autopilot, com reasoning e tools)

## Arquivos-chave
- `app/src/app/pages/chat/components/chat-ticket-item/chat-ticket-item.html`
- `api/src/Domain/Chat/Http/Resources/ChatTicketResource.php`
- `api/src/Domain/Chat/Models/ChatTicket.php`
