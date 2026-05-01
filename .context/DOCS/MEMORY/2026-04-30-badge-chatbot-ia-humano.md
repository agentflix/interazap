# Bug: Badge de modo do chat mostrava "IA" para chatbot simples

## Resumo
O badge de modo na lista de tickets (`chat-ticket-item`) mostrava **"IA"** sempre que `is_bot_active = true`, sem distinguir entre:
- **Chatbot** (automação simples/fluxo, sem plano de IA)
- **IA** (agente de IA ativo, com `current_ai_agent_id`)
- **Humano** (status `in_progress`)

## Root Cause
O campo `current_ai_agent_id` foi adicionado na migration `2026_04_29_000001_add_current_ai_agent_id_to_chat_tickets.php` para implementar "sticky agent", mas **nunca foi exposto na API nem utilizado no frontend**.

A lógica do template só verificava `is_bot_active && status !== 'in_progress'`, o que resultava em "IA" para qualquer tipo de bot.

## Fix Aplicado

### Backend
1. **`api/src/Domain/Chat/Models/ChatTicket.php`**: Adicionado `current_ai_agent_id` ao `$fillable`
2. **`api/src/Domain/Chat/Http/Resources/ChatTicketResource.php`**: Exposto `current_ai_agent_id` no response

### Frontend
3. **`app/src/app/core/services/called.service.ts`**: Adicionado `current_ai_agent_id?: string | null` à interface `Called`
4. **`app/src/app/pages/chat/components/chat-ticket-item/chat-ticket-item.html`**: Corrigida a lógica do badge:
   - `status === 'in_progress'` → **Humano**
   - `is_bot_active && current_ai_agent_id` → **IA**
   - `is_bot_active && !current_ai_agent_id` → **Chatbot**

### Testes
5. **`chat-ticket-item.spec.ts`**: Atualizados 4 testes cobrindo os 3 cenários

## Arquivos Alterados
- `api/src/Domain/Chat/Models/ChatTicket.php`
- `api/src/Domain/Chat/Http/Resources/ChatTicketResource.php`
- `app/src/app/core/services/called.service.ts`
- `app/src/app/pages/chat/components/chat-ticket-item/chat-ticket-item.html`
- `app/src/app/pages/chat/components/chat-ticket-item/chat-ticket-item.spec.ts`

## Validação
- ✅ PHPStan: 0 erros
- ✅ Frontend tests: 17/17 passaram
- ✅ Backend tests: 30/30 passaram (ChatTicketManagerTest, ChatTicketListTest, ProfilePictureTest)
