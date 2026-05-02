# MEMORY — Auto-fechamento de Tickets por Inatividade

**Data:** 2026-05-01  
**Feature:** FEAT-053  
**Spec:** `docs/superpowers/specs/2026-05-01-auto-close-inatividade-design.md`

---

## Decisões Arquiteturais

### 1. Batch SQL vs Job por Ticket

**Decisão:** Usar batch SQL (SELECT + UPDATE em massa) em vez de Job individual por ticket.

**Alternativa considerada:** Disparar um Job para cada ticket expirado (modelo anterior do MVP).

**Justificativa:**
- Apenas 2 queries por execução, independente do volume
- Sem lock prolongado (UPDATE batch rápido com índice)
- Escalável: mesma performance para 100 ou 100.000 tickets
- Mensagens enviadas via queue assíncrona (não bloqueia o fechamento)

### 2. Denormalização de Timestamps por Direção

**Decisão:** Adicionar `last_customer_message_at` e `last_agent_message_at` como colunas em `chat_tickets`, em vez de buscar via JOIN com `chat_messages`.

**Justificativa:**
- Filtrar por target (cliente vs atendente) sem JOIN custoso na tabela de mensagens
- Atualizado em tempo real pelo listener `UpdateTicketActivityTimestampsListener`
- `MessagePersisted` refatorado para disparar em ambas direções (incoming + outgoing)

### 3. Reutilização do TicketClosedEvent

**Decisão:** Adicionar parâmetro `$closedMode` ao evento existente em vez de criar um novo evento.

**Justificativa:**
- `TicketClosedEvent` já invalida cache, dispara broadcast, notifica webchat
- `closedMode` permite filtrar comportamento no listener (`auto_inactivity` → suprime notificações, envia mensagem específica)
- Evita duplicação de lógica de broadcast/notificação

### 4. Configuração por Canal via Colunas Dedicadas

**Decisão:** Usar colunas dedicadas em `chat_instances` (não `settings_json`) para configurações de auto-close por canal.

**Justificativa:**
- Regra de negócio queryável pelo cron (batch SQL precisa filtrar por canal)
- Segue padrão existente do projeto (`evaluation_enabled`, `evaluation_cutoff_score`)
- Semântica de herança: `null` na coluna do canal = herda do tenant (sem flag extra)

### 5. Substituição de end_service_message

**Decisão:** Quando `closedMode='auto_inactivity'`, a mensagem de auto-close SUBSTITUI a `end_service_message`.

**Justificativa:** Evita spam de 2 mensagens de encerramento para o cliente.

### 6. Campos Legacy Mantidos (Deprecados)

**Decisão:** NÃO remover `auto_close_queue_after_minutes` e `auto_close_in_progress_after_minutes` de `chat_tickets_extended`.

**Justificativa:** Existem em tabela core; remover agora pode quebrar BI/scripts externos. Apenas deprecar.

---

## Armadilhas e Aprendizados

### Bug: Cast do Eloquent convertia null → false/0

**Problema:** `auto_close_enabled` e `auto_close_after_minutes` em `ChatInstance` tinham casts `boolean` e `integer`, respectivamente. O Eloquent converte `(bool) null = false` e `(int) null = 0`, destruindo a semântica "null = herda do tenant".

**Solução:** Removidos ambos os casts. Valores raw do PostgreSQL preservam `null` corretamente.

### Bug: Early-return do enabled global impedia override de canal

**Problema:** `CloseInactiveTicketsAction` retornava vazio imediatamente quando `auto_close_inactivity_enabled` global era `false`, antes de consultar canais. Canais com `auto_close_enabled=true` nunca chegavam a ser processados.

**Solução:** Removido o early-return. O `enabled` global agora atua como default (não kill-switch), e cada canal decide via `getEffectiveAutoCloseConfig()`.

### Evento TicketClosedEvent em módulo Configuration

**Observação:** O evento de fechamento de ticket está em `Domain\Configuration\Events` (não `Domain\Chat\Events`). Único ponto de dispatch está em `UpdateChatTicketAction`.

### Testes frontend com falha sistêmica

**Observação:** Os 153 arquivos de teste frontend falham com "No test suite found" — problema pré-existente no `tsconfig.spec.json` (não relacionado a esta feature).

---

## Artefatos

| Tipo | Caminho |
|------|---------|
| Spec | `docs/superpowers/specs/2026-05-01-auto-close-inatividade-design.md` |
| CHANGELOG | `.context/DOCS/CHANGELOG/2026-05-01.md` |
| Backend tests | `api/tests/Feature/Chat/CloseInactiveTicketsTest.php` (16 testes) |
