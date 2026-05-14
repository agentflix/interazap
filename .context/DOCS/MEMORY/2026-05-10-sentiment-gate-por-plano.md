# Memory: Gate de plano para análise de sentimento no fechamento de ticket

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-10 |
| **Autor** | DEV agent |
| **Contexto** | Correção de execução indevida de `AiAnalyzeSentimentJob` para tenant sem IA habilitada |
| **Tags** | api, chat, ai, billing, plano, multi-tenant |

---

## Situação
Mesmo após bloquear resposta de IA no WebChat e no gatekeeper de autopilot, o sistema ainda executava análise de sentimento no fechamento do ticket (`AiAnalyzeSentimentJob`) para tenants com `ai_enabled=false`.

O dispatch era feito em `UpdateChatTicketAction::dispatchFinalSentimentAnalysis()` sem checagem de plano.

---

## Decisão / Aprendizado
Aplicar gate de plano no próprio sender do job de sentimento:

- Antes de coletar a última mensagem inbound e despachar `AiAnalyzeSentimentJob`, validar `PlatformPlanEnforcementService::isAiEnabled($tenantId)`.
- Se IA estiver desabilitada no plano, retornar cedo sem dispatch.

Complemento de defesa em profundidade:

- `AiAnalyzeSentimentJob::handle()` também valida `isAiEnabled($tenantId)` e aborta imediatamente quando o plano não permite IA.
- Isso cobre backlog antigo (jobs enfileirados antes da correção no sender).

Essa decisão mantém consistência com a regra comercial “sem IA, sem processamento de IA”, reduz custo de fila e evita efeitos colaterais em relatórios de sentimento.

---

## Alternativas Consideradas
| Alternativa | Por que descartada |
|-------------|-------------------|
| Bloquear só dentro do job `AiAnalyzeSentimentJob` | Evita execução final, mas ainda gera enfileiramento desnecessário e ruído operacional |
| Não bloquear sentimento (tratar como recurso separado) | Inconsistente com expectativa do cliente e com `ai_enabled` já aplicado no restante do fluxo de IA |

---

## Consequências
### Positivas
- Elimina dispatch de sentimento para planos sem IA.
- Reduz custo e ruído na fila `sentiment`.
- Mantém comportamento de IA coerente entre resposta automática e análises assíncronas.

### Negativas / Trade-offs
- Acoplamento adicional de `UpdateChatTicketAction` ao `PlatformPlanEnforcementService` (aceitável por consistência de negócio).

---

## Referências
- Arquivos: `api/src/Domain/Chat/Actions/UpdateChatTicketAction.php`, `api/tests/Unit/Chat/ChatTicketActionsTest.php`, `api/tests/Unit/Chat/Actions/UpdateChatTicketActionTest.php`
- Changelog: `.context/DOCS/CHANGELOG/2026-05-10.md`
