# Feature: Decomposição do Host `chat.ts`

> Feature doc — Documentação antes da implementação

---

## Metadados

| Campo                  | Valor                                                          |
| ---------------------- | -------------------------------------------------------------- |
| **ID**                 | FEAT-049                                                       |
| **Nome**               | Decomposição do host `chat.ts` em camadas (container/services) |
| **Bounded Context**    | Chat (Frontend)                                                |
| **Complexidade**       | G                                                              |
| **Prioridade**         | Should                                                         |
| **Status**             | 🟡 Parcialmente concluída (CA-001 fora da meta)                |
| **Criada em**          | 2026-04-29                                                     |
| **Última atualização** | 2026-04-29                                                     |

---

## Resumo

Decompor o host `app/src/app/pages/chat/chat.ts` (1.333 linhas) em um container fino e serviços de apoio especializados, seguindo o padrão estabelecido na FE-CHAT-002 (refactor anterior do `user-chat`). A meta é eliminar o god component, reduzir acoplamento e habilitar testes unitários por responsabilidade.

---

## Objetivo

O host de chat acumulou responsabilidades cruzadas: gerenciamento de tickets, transferência, fechamento, gravação de áudio, upload de mídia, listeners de realtime, sincronização de rota e modais. Isso:

- Quebra Single Responsibility — qualquer alteração em um sub-fluxo exige reler o arquivo inteiro
- Dificulta testes (apenas 6 testes para 1.333 linhas)
- Concentra risco de regressão em um único módulo crítico de atendimento
- Esconde bugs (ex.: subscriptions aninhadas em `onTransferConfirmed` e `handleRecordingCompleted` já corrigidas via switchMap)

---

## Escopo

### Dentro do Escopo ✅

- [ ] Extrair listeners de realtime (`message.received`, `chat.activity`, `chat.ticket.new`) para `ChatRealtimeListenerService`
- [ ] Extrair fluxo de transferência (`onTransferConfirmed`, abrir/fechar modal, estado) para `ChatTicketTransferService`
- [ ] Extrair fluxo de fechamento (`confirmCloseSelectedTicket`, modal, otimismo) para `ChatTicketCloseService`
- [ ] Extrair handler de gravação completada (`handleRecordingCompleted`) para `ChatRecordingDispatcher`
- [ ] Reduzir `chat.ts` para ≤ 700 linhas (container fino: ciclo de vida, signals do template, delegação a services)
- [ ] Manter contrato público do template `chat.html` inalterado
- [ ] Cobertura mínima: pelo menos 1 spec por service novo

### Fora do Escopo ❌

- Refatoração do template `chat.html` (mantém estrutura)
- Migração para Signal Store / NgRx
- Reescrita do fluxo de upload de mídia (`mediaBatchService` já é externo)
- Decomposição do `chat-sidebar` ou `chat-conversation` (já são componentes próprios)
- Mudança de comportamento ou novos features

---

## Dependências

| Feature/Sistema         | Tipo          | Status | Blocker |
| ----------------------- | ------------- | ------ | ------- |
| `ChatStore` (existente) | É reutilizado | Pronto | Não     |
| `ChatTicketListService` | É reutilizado | Pronto | Não     |
| `ChatRecorderService`   | É reutilizado | Pronto | Não     |
| `RealtimeService`       | É reutilizado | Pronto | Não     |

---

## Critérios de Aceite

| ID     | Critério                                                                                    | Verificável                     | Status                   |
| ------ | ------------------------------------------------------------------------------------------- | ------------------------------- | ------------------------ |
| CA-001 | `chat.ts` passa de 1.333 linhas para ≤ 700 linhas                                           | `wc -l`                         | ⚠️ Parcial: 1.078 (-19%) |
| CA-002 | Comportamento idêntico: testes existentes (`chat.spec.ts`) continuam passando sem alteração | `pnpm vitest chat.spec.ts`      | ✅ 6/6                   |
| CA-003 | Cada service novo tem ao menos 1 spec verde                                                 | `pnpm vitest <service>.spec.ts` | ✅ 17/17                 |
| CA-004 | Lint sem regressões em `chat.ts` e arquivos novos                                           | `pnpm lint`                     | ✅ 0 erros (10 lint corrigidos pós-review) |
| CA-005 | Template `chat.html` permanece byte-equivalente (sem mudança visual/semântica)              | `git diff chat.html` vazio      | ✅                       |
| CA-006 | Todos componentes/services com `OnPush` ou `providedIn:'root'` conforme convenção           | Code review                     | ✅                       |

---

## Tasks

| Task ID    | Descrição                                  | Status |
| ---------- | ------------------------------------------ | ------ |
| TASK-049.1 | Extrair `ChatRealtimeListenerService`      | ✅     |
| TASK-049.2 | Extrair `ChatTicketTransferService`        | ✅     |
| TASK-049.3 | Extrair `ChatTicketCloseService`           | ✅     |
| TASK-049.4 | Extrair `ChatRecordingDispatcher`          | ✅     |
| TASK-049.5 | Reduzir `chat.ts` integrando os 4 services | ✅     |
| TASK-049.6 | Specs para cada service extraído           | ✅     |

---

## Notas

- O refactor anterior FE-CHAT-002 (user-chat, 60% redução) é o template aspiracional, mas o `chat.ts` aqui já delega upload de mídia, fila de envio, gravação base e cache para services existentes. O foco desta feature foi remover o acoplamento que sobrou.
- Bugs ALTA já corrigidos antes desta feature (subscriptions aninhadas em `onTransferConfirmed`/`handleRecordingCompleted` via `switchMap`) foram preservados nos services extraídos.
- **CA-001 fora da meta**: A redução foi de 1.333 → 1.078 (-19%, não os ~47% almejados). O remanescente concentra-se em fluxos de mídia/anexos (drag‑n‑drop, file picker, preview) e route‑sync — candidatos a uma `FEAT-050` follow-up.
