# MEMORY — FEAT-050: Meta Template Management + 24h Composer

**Data:** 2026-04-29
**Autor:** ARCHITECT
**Status:** Decisão registrada

---

## Decisões

### ADR-049-1 — Templates como entidade local espelhada da Meta

**Contexto:** Hoje só `getTemplates(APPROVED)` cacheado em Redis 15min. Admin não vê PENDING/REJECTED nem motivo de rejeição.

**Decisão:** Espelhar templates em `chat_message_templates` com colunas `status`, `rejected_reason`, `external_id`, `components_json`, `last_synced_at`. Redis continua sendo cache de leitura para o picker no chat (rápido), mas o painel admin lê do DB.

**Alternativas:**
- Apenas Redis com TTL maior — descartada: sem histórico, sem busca.
- Tabela separada `meta_templates` — descartada: duplica conceito de "template de mensagem" que já existe.

**Consequências:**
- Migração precisa preservar templates locais legados (`provider='local'`).
- Sync diário (cron) + botão manual + webhook Meta mantém consistência.

---

### ADR-049-2 — Sync bidirecional via Job assíncrono

**Decisão:** Create/Delete chamam Meta via Job (queue), não inline. Status inicial `pending`. Webhook `message_template_status_update` atualiza para final.

**Por quê:** Meta API pode demorar; UI precisa responder rápido; webhook é a fonte da verdade do approval.

---

### ADR-049-3 — Guard 24h em ambas as camadas

**Decisão:** Frontend bloqueia textarea (UX) **e** backend rejeita texto livre fora da janela (segurança). Não confiar em só uma.

**Por quê:** API direta poderia burlar UI. Padrão "defense in depth".

**Implementação:** `SendChatMessageAction` reusa `VerifyContactWindowAction` antes de gravar/dispatchar quando `provider='meta'`.

---

### ADR-049-4 — Composer modes: free | mixed | template-only

**Decisão:** Computed signal no `ChatStore` baseado em (provider, canSendFreeText).

| provider | canSendFreeText | mode |
|----------|----------------|------|
| meta | true | mixed (textarea + botão template) |
| meta | false | template-only (só selector) |
| outros | n/a | free (textarea) |

---

### ADR-049-5 — Templates por `chat_instance_id`, não por tenant

**Decisão:** Cada `ChatInstance` Meta = uma WABA. Templates pertencem ao canal.

**Por quê:** Tenant pode ter múltiplas WABAs (uma por marca). Templates locais legados ficam com `chat_instance_id=NULL` e `provider='local'`.

---

### ADR-049-6 — `TemplateSelectorComponent` movido para shared

**Decisão:** Extrair de `new-conversation-modal/components/` para `shared/components/`.

**Por quê:** Reuso obrigatório no composer da tela de chat. Evita drift de UX/lógica.

---

## Aprendizados / Armadilhas

- **Meta não permite update de template aprovado.** Update só edita campos locais (`is_active`, `shortcut`); mudança real exige delete + create.
- **Cache Redis precisa ter chaves separadas** por modo (`approved` vs `all`) para não vazar PENDING para o picker do atendente.
- **Templates locais legados** (FEAT-039 e anteriores) têm `chat_instance_id=NULL` — toda query de Meta deve filtrar `WHERE provider='meta'` explicitamente.
- **Janela 24h conta da última inbound do contato** (`is_from_contact=true`), já implementado em `VerifyContactWindowAction`. Não recriar.
- **Reabertura automática:** ao chegar inbound do cliente, invalidar cache + recheckar status no front (TASK-D7).

---

## Referências

- Feature: `.context/DOCS/FEATURES/FEAT-050-meta-message-templates.md`
- Tasks: `.context/DOCS/TASKS/FEAT-050-tasks.md`
- Predecessora: FEAT-039 (Meta WhatsApp integration)
- Meta docs: <https://developers.facebook.com/docs/whatsapp/business-management-api/message-templates>
