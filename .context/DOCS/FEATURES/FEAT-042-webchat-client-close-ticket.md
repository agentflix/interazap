# FEAT-042 — Encerramento pelo Cliente no Webchat Externo

## Metadados

| Campo                  | Valor                                        |
| ---------------------- | -------------------------------------------- |
| **ID**                 | FEAT-042                                     |
| **Nome**               | Encerramento pelo cliente no webchat externo |
| **Bounded Context**    | Chat                                         |
| **Complexidade**       | M                                            |
| **Prioridade**         | Must                                         |
| **Status**             | ✅ Concluída                                 |
| **Criada em**          | 2026-04-21                                   |
| **Última atualização** | 2026-04-21                                   |

---

## Resumo

Adicionar ao webchat público um botão para o visitante encerrar o chamado em andamento. Quando o fechamento for confirmado, o histórico deve permanecer visível no lado cliente, o composer deve ser omitido e o atendente com a conversa aberta deve receber a atualização de status imediatamente, com a conversa passando a se comportar como ticket fechado.

---

## Objetivo

Reduzir dependência operacional do atendente para encerrar atendimentos já concluídos do ponto de vista do cliente e alinhar o estado do ticket entre webchat público e console interno sem refresh manual.

---

## Escopo

### Dentro do Escopo ✅

- [x] Exibir ação `Encerrar chamado` no cabeçalho do webchat enquanto o ticket estiver aberto.
- [x] Solicitar confirmação explícita antes de executar o encerramento.
- [x] Criar endpoint público específico para encerramento autenticado pelo token da sessão webchat.
- [x] Reutilizar o fluxo de fechamento normal já existente no backend, incluindo a mensagem automática de encerramento já configurada.
- [x] Atualizar o estado do cliente público após o fechamento sem limpar o histórico.
- [x] Atualizar o atendente em tempo real com `ticket.updated` para refletir `status=closed` na conversa aberta.
- [x] Garantir cobertura de testes backend e frontend para o novo fluxo.

### Fora do Escopo ❌

- Sincronização de múltiplas abas públicas da mesma sessão webchat.
- Reabertura do ticket diretamente a partir do webchat público.
- Novo template de mensagem de encerramento exclusivo para "encerrado pelo cliente".
- Alterações de schema/migration para armazenar uma nova coluna de origem de fechamento.

---

## Dependências

| Feature/Sistema                                                  | Tipo     | Status | Blocker |
| ---------------------------------------------------------------- | -------- | ------ | ------- |
| FEAT-040 WebChat Widget                                          | Bloqueia | Pronta | Sim     |
| Fluxo interno de fechamento de ticket (`UpdateChatTicketAction`) | Bloqueia | Pronto | Sim     |
| Realtime do atendente via `ticket.updated`                       | Bloqueia | Pronto | Sim     |

---

## Arquitetura

```
┌──────────────────────────────────────────────────────────────┐
│                     WEBCHAT PÚBLICO (Angular)                │
│  ChatWindowComponent                                         │
│  - Botão no cabeçalho                                        │
│  - Modal de confirmação                                      │
│  - Estado final: histórico visível + composer oculto         │
│  WebChatService                                              │
│  - POST /api/webchat/close                                   │
│  - Estado local do ticket (open/closed, isClosing)           │
└──────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────┐
│                      BACKEND (Laravel)                       │
│  WebChatCloseController (novo)                               │
│  - Valida JWT da sessão                                      │
│  - Resolve session_id / tenant_id / ticket_id                │
│  - Chama UpdateChatTicketAction::updateStatus(..., closed)   │
│  UpdateChatTicketAction (existente)                          │
│  - Reusa fechamento normal + end_service_message             │
│  - Passa a publicar ticket.updated no fechamento público     │
└──────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────┐
│                   REALTIME DO ATENDENTE                      │
│  ChatActivityBroadcastService / ws.events                    │
│  - Emite ticket.updated com ticket fechado                   │
│  Frontend Chat Store                                         │
│  - Mescla status=closed no ticket selecionado                │
│  - UI passa a ocultar composer e ações de envio              │
└──────────────────────────────────────────────────────────────┘
```

### Decisão de arquitetura

O cliente público que inicia o encerramento será atualizado pelo retorno HTTP do endpoint de fechamento, e não por um novo canal websocket dedicado. Isso mantém o escopo mínimo e evita dependência adicional de rooms de sessão para cumprir o requisito atual. O atendente continua sincronizado por realtime através de `ticket.updated`.

---

## Contrato Proposto

### Endpoint público

| Método | Rota                 | Descrição                                   | Auth          |
| ------ | -------------------- | ------------------------------------------- | ------------- |
| POST   | `/api/webchat/close` | Encerra o ticket vinculado à sessão webchat | JWT da sessão |

### Request

```json
{
    "token": "jwt-da-sessao"
}
```

### Response

```json
{
    "success": true,
    "message": "Ticket fechado",
    "data": {
        "ticketId": "uuid-do-ticket",
        "status": "closed",
        "closedAt": "2026-04-21T18:30:00Z"
    }
}
```

### Regras comportamentais

- Se o ticket já estiver fechado, a resposta deve ser idempotente e retornar `status=closed` sem erro funcional.
- O fechamento usa modo normal para reaproveitar a mensagem automática existente de encerramento.
- O frontend público deve assumir o novo estado com base no retorno HTTP, mesmo que o realtime do atendente falhe.

---

## Fluxo Funcional

### 1. Cliente público

1. Visitante clica em `Encerrar chamado` no cabeçalho.
2. Modal pede confirmação.
3. Frontend envia `POST /api/webchat/close` com o token da sessão.
4. Ao retornar sucesso, o estado local do ticket muda para `closed`.
5. O histórico continua visível.
6. O composer deixa de ser renderizado.
7. A mensagem automática de encerramento já existente permanece como registro final da conversa.

### 2. Atendente

1. Backend fecha o ticket e publica `ticket.updated` com o ticket atualizado.
2. O `chat.store` mescla `status=closed` no ticket aberto.
3. A interface interna passa a se comportar como ticket fechado, omitindo composer e ações incompatíveis.
4. A mensagem de encerramento automática aparece como registro visível na timeline.

---

## Critérios de Aceite

| ID     | Critério                                                                                                   | Verificável | Status |
| ------ | ---------------------------------------------------------------------------------------------------------- | ----------- | ------ |
| CA-001 | O webchat público exibe botão de encerramento no cabeçalho apenas para sessão com ticket aberto            | [x]         | ✅     |
| CA-002 | Após confirmação e sucesso do endpoint, o histórico permanece visível e o composer some do webchat público | [x]         | ✅     |
| CA-003 | O fechamento público reaproveita a mensagem automática de encerramento já configurada no backend           | [x]         | ✅     |
| CA-004 | Se o atendente estiver com a conversa aberta, o ticket muda para `closed` sem refresh manual               | [x]         | ✅     |
| CA-005 | O fluxo é idempotente para ticket já fechado e retorna estado final consistente                            | [x]         | ✅     |

---

## Tasks

| Task ID      | Descrição                                                        | Status |
| ------------ | ---------------------------------------------------------------- | ------ |
| TASK-3.042.1 | Criar endpoint público de encerramento do webchat                | ✅     |
| TASK-3.042.2 | Publicar atualização realtime de ticket fechado para o atendente | ✅     |
| TASK-3.042.3 | Cobrir backend com testes de fechamento público                  | ✅     |
| TASK-4.042.1 | Estender estado e serviço do webchat para fechamento público     | ✅     |
| TASK-4.042.2 | Implementar UI pública com botão, modal e estado final           | ✅     |
| TASK-4.042.3 | Garantir sincronização do atendente com o fechamento remoto      | ✅     |
| TASK-4.042.4 | Cobrir frontend público com testes do novo estado                | ✅     |
| TASK-5.042.1 | Validar fluxo ponta a ponta e gates relevantes                   | ✅     |

---

## Riscos e Mitigações

| Risco                                                                   | Mitigação                                                                                             |
| ----------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| O fechamento público altera o ticket mas o atendente não recebe refresh | Emitir `ticket.updated` no mesmo fluxo de fechamento e cobrir com teste de contrato do store/realtime |
| Sessão expirada gera UX confusa                                         | Retornar erro claro no endpoint e exibir estado de sessão inválida no frontend público                |
| Duplicidade de clique fecha duas vezes                                  | Controlar `isClosing` no frontend e manter endpoint idempotente                                       |

---

## Notas

- A decisão de não criar migration nesta iteração foi intencional: a origem do fechamento será tratada pelo fluxo público e pelo registro visível em conversa, sem aumentar o schema.
- Cross-tab sync do webchat público fica explicitamente fora do escopo para manter YAGNI.
