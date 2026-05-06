# Memory: Histórico de conversa da Sofia deve ser enviado como turnos reais

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-05 |
| **Autor** | Codex |
| **Contexto** | Correção do loop da Sofia em classificação inicial |
| **Tags** | gateway, ai, orchestration, prompt, atendimento |

---

## Situação

O histórico da conversa estava sendo enviado apenas dentro de `context:{...}` em system message. Em novas runs sem `request.messages`, o modelo não tratava isso como turnos anteriores e repetia perguntas de qualificação.

---

## Decisão / Aprendizado

Converter `conversation_history` para mensagens estruturadas (`role: user|assistant`) e inseri-las antes do `inputText` atual na montagem inicial de mensagens.

Também remover `conversation_history` do JSON de contexto para evitar duplicidade de informação.

---

## Alternativas Consideradas

| Alternativa | Por que descartada |
|-------------|-------------------|
| Manter histórico só no `context:` serializado | Modelo interpreta como metadado e perde noção de turnos conversacionais |
| Persistir apenas `request.messages` entre runs | Não resolve cenários em que a execução começa sem `messages` preenchidas |

---

## Consequências

### Positivas
- Reduz repetição da pergunta de roteamento da Sofia.
- Melhora consistência de delegação em 1-2 trocas com contexto real de diálogo.

### Negativas / Trade-offs
- A montagem inicial ganha transformação adicional de parsing (`User:` / `Agent:`), exigindo teste dedicado para evitar regressão.

---

## Referências
- Task: ajuste solicitado em conversa
- Arquivo: `gateway/src/domains/ai/services/orchestration/message-builder.service.ts`
