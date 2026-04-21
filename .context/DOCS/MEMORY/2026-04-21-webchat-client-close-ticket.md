# Memory: Encerramento pelo cliente no webchat externo

## Metadados

| Campo        | Valor                                           |
| ------------ | ----------------------------------------------- |
| **Tipo**     | Decisão                                         |
| **Data**     | 2026-04-21                                      |
| **Autor**    | ORCHESTRATOR                                    |
| **Contexto** | Planejamento do FEAT-042                        |
| **Tags**     | webchat, fechamento, realtime, angular, laravel |

---

## Decisão / Aprendizado

Para o fluxo de encerramento iniciado pelo cliente no webchat público:

- o backend deve expor um endpoint público específico autenticado pelo JWT da sessão webchat, em vez de reutilizar diretamente o controller interno autenticado por atendente;
- o fechamento deve reaproveitar o modo normal já existente em `UpdateChatTicketAction`, preservando a mensagem automática `end_service_message`;
- o cliente que iniciou o encerramento deve atualizar a UI pelo retorno HTTP do endpoint, sem depender de um novo canal websocket;
- o atendente deve ser sincronizado por `ticket.updated` com `status=closed`, para que a conversa aberta passe imediatamente ao estado fechado.

---

## Alternativas Consideradas

| Alternativa                                                           | Por que descartada                                                                     |
| --------------------------------------------------------------------- | -------------------------------------------------------------------------------------- |
| Reusar o endpoint interno `/chat/tickets/{id}/close`                  | Mistura autenticação de visitante público com autorização de usuário interno.          |
| Criar sincronização websocket dedicada para o próprio cliente público | Desnecessário para o requisito atual; o retorno HTTP já resolve o estado do iniciador. |
| Criar migration para persistir origem de fechamento                   | Aumenta escopo sem necessidade para esta iteração.                                     |

---

## Referências

- `app/src/app/pages/webchat/services/webchat.service.ts`
- `app/src/app/pages/webchat/components/chat-window/chat-window.component.ts`
- `api/src/Domain/Chat/Actions/UpdateChatTicketAction.php`
- `api/src/Domain/Chat/Routes/webchat.php`
- `app/src/app/pages/chat/store/chat.store.ts`
