# Memory: Gates de IA por plano no WebChat e no listener central

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-10 |
| **Autor** | DEV agent |
| **Contexto** | Correção de bypass de IA para tenants com plano sem IA no fluxo de WebChat |
| **Tags** | api, chat, ai, platform, billing, multi-tenant |

---

## Situação
Tenants com plano `ai_enabled=false` estavam recebendo respostas de IA no WebChat porque o controller não verificava plano antes de disparar `AiRunRequested`.

Além disso, o `AiGateKeeperListener` documentava a checagem de plano, mas não implementava esse gate; e dois pontos de fallback exibiam IA como habilitada quando `currentPlan` era `null`.

---

## Decisão / Aprendizado
Aplicar defesa em profundidade em duas camadas:

1. Gate no sender (`WebChatMessageController`) para evitar dispatch desnecessário quando IA está desabilitada.
2. Gate no listener central (`AiGateKeeperListener`) para garantir bloqueio mesmo se novos senders esquecerem de validar plano.

Padronizar fallback de IA para `false` quando não há plano corrente disponível em payloads tenant-facing.

---

## Alternativas Consideradas
| Alternativa | Por que descartada |
|-------------|-------------------|
| Corrigir apenas o controller de WebChat | Não protege caminhos futuros que publiquem `AiRunRequested` sem verificação |
| Corrigir apenas o listener | Resolve segurança, mas mantém custo desnecessário de dispatch no WebChat |
| Manter fallback `true` sem plano | Comportamento inseguro e inconsistente com `PlatformPlanEnforcementService::isAiEnabled()` |

---

## Consequências
### Positivas
- Elimina bypass de IA no WebChat para planos sem IA.
- Centraliza garantia de negócio no listener (`AiGateKeeperListener`).
- Evita comportamento permissivo quando plano está ausente/inconsistente.
- Aumenta previsibilidade entre WhatsApp e WebChat.

### Negativas / Trade-offs
- Acrescenta uma consulta de plano no fluxo de WebChat (trade-off aceitável por segurança de regra comercial).

---

## Referências
- Arquivos: `api/src/Domain/Chat/Http/Controllers/WebChatMessageController.php`, `api/src/Domain/Ai/Listeners/AiGateKeeperListener.php`, `api/src/Domain/Billing/Http/Controllers/BillingSubscriptionController.php`, `api/src/Domain/Platform/Http/Resources/PlatformTenantDetailsResource.php`
- Testes: `api/tests/Feature/Chat/WebChatMessageControllerTest.php`, `api/tests/Unit/Ai/AiGateKeeperListenerTest.php`, `api/tests/Feature/BillingSubscriptionTest.php`, `api/tests/Feature/PlatformTenantDetailsTest.php`
- Changelog: `.context/DOCS/CHANGELOG/2026-05-10.md`

