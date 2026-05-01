# FEAT-050 — Meta WhatsApp: Gestão de Templates + Composer com Janela 24h

> **Status:** 📋 Planejada
> **Bounded Context:** Chat (api), Gateway (gateway), App (frontend)
> **Depende de:** FEAT-039 (Meta WhatsApp Business API Integration)

---

## 1. Objetivo

Habilitar usuários administradores a **criar, editar, excluir e administrar templates de mensagens da Meta WhatsApp Business API**, com visibilidade do status (`PENDING/APPROVED/REJECTED/PAUSED/DISABLED`) e do motivo de rejeição. Aplicar templates dentro da tela de chat:

- Quando o canal selecionado é Meta **e** a janela de 24h está expirada → **omitir o textarea** e exigir envio via template.
- Quando o canal é Meta **e** a janela está aberta → permitir texto livre **ou** template (botão).
- Quando o canal não é Meta → comportamento atual (texto livre apenas).

---

## 2. Escopo

### Dentro do escopo
- CRUD local + sincronização bidirecional com Meta para templates.
- Página administrativa `/chat/templates` para gestão.
- Rota e Action para enviar mensagem via template a partir de um ticket aberto.
- Guard server-side de janela 24h em envios para canal Meta.
- Atualização automática de status via webhook `message_template_status_update`.
- UI condicional do composer da tela de chat baseada em provider + janela.

### Fora do escopo
- Templates com `HEADER` rich-media (imagem/vídeo/documento) — somente `BODY` + `FOOTER` + `BUTTONS QUICK_REPLY` na v1.
- Categorização avançada (Marketing/Utility/Authentication com pricing) — apenas exibição.
- Multi-language em runtime (template é por language code, não traduzido on-the-fly).

---

## 3. Arquitetura

### 3.1 Diagrama

```
┌───────────────────────────────────────────────────────────────┐
│                      FRONTEND (Angular)                        │
│  /chat/templates (admin)                                       │
│   ├─ TemplateListPage (status badges + sync button)           │
│   ├─ TemplateFormPage (criar/editar)                          │
│   └─ TemplateService (CRUD via api/chat/message-templates)    │
│                                                                │
│  /chat (composer)                                              │
│   ├─ ChatStore.composerMode = computed(provider + window)     │
│   ├─ free | mixed | template-only                             │
│   └─ TemplateSelectorComponent (shared) — picker inline       │
└───────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌───────────────────────────────────────────────────────────────┐
│                      BACKEND (Laravel)                         │
│  ChatMessageTemplateController                                 │
│   ├─ index | show | store | update | destroy | sync           │
│  ChatMessageController::sendTemplate (NOVA)                   │
│   POST /api/chat/tickets/{id}/messages/template               │
│  Listener: MetaTemplateStatusUpdatedListener                  │
│  Guard: SendChatMessageAction → bloqueia free-text se Meta+24h│
└───────────────────────────────────────────────────────────────┘
                              │  HTTP (GATEWAY_SECRET)
                              ▼
┌───────────────────────────────────────────────────────────────┐
│                      GATEWAY (NestJS)                          │
│  ChannelsController                                           │
│   ├─ GET    /channels/:id/templates?include_all=bool          │
│   ├─ POST   /channels/:id/templates                           │
│   ├─ DELETE /channels/:id/templates/:name                     │
│   └─ POST   /channels/:id/templates/sync                      │
│  MetaWebhookController                                        │
│   └─ field='message_template_status_update' → publish stream  │
└───────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    META GRAPH API v18.0
                    /message_templates
```

### 3.2 Modelo de dados

Extensão da tabela `chat_message_templates` (migration nova, sem destruir dados):

| Coluna | Tipo | Descrição |
|--------|------|-----------|
| `chat_instance_id` | uuid NULL FK chat_instances | Canal dono. NULL = template legado local. |
| `provider` | varchar(20) NOT NULL DEFAULT 'local' | `local` ou `meta` |
| `external_id` | varchar(255) NULL | id do template na Meta |
| `language` | varchar(10) NOT NULL DEFAULT 'pt_BR' | language code Meta |
| `status` | varchar(20) NOT NULL DEFAULT 'approved' | pending/approved/rejected/paused/disabled |
| `rejected_reason` | text NULL | motivo retornado pela Meta |
| `components_json` | jsonb NULL | header/body/footer/buttons + parâmetros |
| `last_synced_at` | timestamp NULL | última sync com Meta |

Constraint: `UNIQUE (chat_instance_id, name, language)` quando `chat_instance_id IS NOT NULL`.

### 3.3 Estados do composer (frontend)

```
provider == 'meta'  &&  canSendFreeText == false  → 'template-only'
provider == 'meta'  &&  canSendFreeText == true   → 'mixed'
provider != 'meta'                                → 'free'
```

---

## 4. Critérios de Aceitação

| ID | Cenário | Esperado |
|----|---------|----------|
| AC-01 | Admin abre `/chat/templates` | Lista paginada com status + filtro por canal |
| AC-02 | Admin cria template novo | Row criada local com `status='pending'`, requisição enviada à Meta |
| AC-03 | Meta aprova template (webhook) | Status local muda para `approved` sem ação manual |
| AC-04 | Meta rejeita template | Status `rejected`, `rejected_reason` exibida na lista |
| AC-05 | Admin clica "Sincronizar" | GET no Gateway, faz upsert, atualiza tudo |
| AC-06 | Admin exclui template Meta | `DELETE` na Meta + soft-delete local |
| AC-07 | Atendente abre ticket Meta com janela aberta | Vê textarea + botão "📋 Template" |
| AC-08 | Atendente abre ticket Meta com janela expirada | Textarea oculto; só `<app-template-selector>` visível |
| AC-09 | Atendente envia template | Mensagem aparece na conversa com badge "via template" |
| AC-10 | Atendente tenta texto livre fora da janela via API direta | API responde 422 `WINDOW_24H_EXPIRED` |
| AC-11 | Cliente responde dentro do ticket Meta expirado | Composer reabre automaticamente (modo `free`/`mixed`) |
| AC-12 | Ticket de canal não-Meta (uazapi/zapi) | Composer ignora janela, comporta-se como hoje |

---

## 5. Tasks

Veja [.context/DOCS/TASKS/FEAT-050-tasks.md](../TASKS/FEAT-050-tasks.md) para a decomposição T.A.C.E.

---

## 6. Riscos

| Risco | Severidade | Mitigação |
|-------|-----------|-----------|
| Aprovação Meta demora 24-48h | M | UI exibe `pending` claramente; permite reenvio |
| Webhook de status update perdido | M | Botão sync manual + cron diário |
| Cache Redis vs DB local divergir | B | Invalidar cache em todo write |
| Atendente envia free-text milisegundos antes do limite | B | Guard server-side (defesa em profundidade) |
| Templates legados (sem `chat_instance_id`) | B | Migration mantém retrocompatibilidade (`provider='local'`) |

---

## 7. Decisão

ADR registrada em `.context/DOCS/MEMORY/2026-04-29-feat-049-template-management.md`.
