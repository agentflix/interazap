# Memory: Webchat exibindo JSON literal de send_message

## Metadados

| Campo        | Valor                                                         |
| ------------ | ------------------------------------------------------------- |
| **Tipo**     | ⚠️ Armadilha                                                  |
| **Data**     | 2026-04-23                                                    |
| **Autor**    | DEBUG Agent                                                   |
| **Contexto** | Diagnostico de resposta de IA aparecendo como JSON no webchat |
| **Tags**     | gateway, webchat, tool-calls, send_message, regressao         |

---

## Situação

> O que estava acontecendo? Qual o contexto?

No webchat, algumas respostas do "Atendente" apareciam como JSON literal no formato:
{"name":"send_message","arguments":{"ticket_id":"...","content":"...","type":"text"}}

---

## Decisão / Aprendizado

> O que foi decidido ou aprendido?

O tratamento deve acontecer na borda do Gateway antes do envio ao cliente:

1. Parser de tool-calls precisa aceitar payload em array e objeto unico.
2. Finalizacao da run precisa extrair texto humano quando a resposta vier serializada como envelope de `send_message`.

Sem essa normalizacao, o fluxo de envio implicito replica o JSON bruto para o ticket/webchat.

---

## Alternativas Consideradas

> O que foi descartado e por quê?

| Alternativa                                       | Por que descartada                                              |
| ------------------------------------------------- | --------------------------------------------------------------- |
| Corrigir apenas no frontend (mascarar JSON)       | Trataria sintoma e manteria dado incorreto no backend/ticket.   |
| Refatorar pipeline completo de tool orchestration | Escopo alto para bug pontual, risco de regressao desnecessaria. |

---

## Consequências

> O que muda por causa disso?

### Positivas

- Evita mensagem JSON literal para cliente no webchat.
- Mantem compatibilidade com formatos variados de tool-call (array/objeto).
- Protege caminho de envio implicito quando LLM retornar envelope textual.

### Negativas / Trade-offs

- Adiciona normalizacao extra no caminho de finalizacao da run.

---

## Referências

- `gateway/src/domains/ai/services/orchestration/tool-call-loop.service.ts`
- `gateway/src/domains/ai/services/orchestration/run-completion.service.ts`
- `gateway/src/domains/ai/services/orchestration/tool-call-loop.service.spec.ts`
- `gateway/src/domains/ai/services/orchestration/run-completion.service.spec.ts`
