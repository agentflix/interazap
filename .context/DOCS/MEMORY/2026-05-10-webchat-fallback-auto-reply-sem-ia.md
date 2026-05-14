# Memory: Fallback obrigatório de WebChat para Auto Reply quando IA está desabilitada

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-10 |
| **Autor** | DEV agent |
| **Contexto** | Correção de silêncio no WebChat para tenants com `ai_enabled=false` |
| **Tags** | api, chat, webchat, auto-reply, ai, multi-tenant |

---

## Situação
Após bloquear corretamente o dispatch de IA por plano no WebChat, o fluxo ficou sem resposta para tenants sem IA porque o controller não tinha branch de fallback para `ChatAutoReplyResponder`.

No WhatsApp, esse fallback já existe em `ChatWebhookRouter`, então o comportamento entre canais ficou inconsistente.

---

## Decisão / Aprendizado
Padronizar o roteamento do WebChat com o WhatsApp:

1. Se `ai_enabled=true`, disparar `ChatAutopilotResponder`.
2. Se `ai_enabled=false`, disparar `ChatAutoReplyResponder`.

Para habilitar boas-vindas na primeira interação do WebChat, calcular `isFirstInteraction` com contagem de mensagens de contato já persistidas no ticket (`count() === 1`).

---

## Alternativas Consideradas
| Alternativa | Por que descartada |
|-------------|-------------------|
| Manter apenas bloqueio de IA sem fallback | Gera silêncio total para planos sem IA, quebrando UX e regra de negócio |
| Mover todo roteamento do WebChat para `ChatWebhookRouter` imediatamente | Refactor maior para hotfix pontual, com risco e custo desnecessários no curto prazo |

---

## Consequências
### Positivas
- Remove silêncio no WebChat para planos sem IA.
- Reaproveita motor de auto-reply existente e consistente com WhatsApp.
- Mantém defesa em profundidade já aplicada (`controller` + `AiGateKeeperListener`).

### Negativas / Trade-offs
- Acrescenta uma consulta de contagem por mensagem de texto no fluxo WebChat para calcular primeira interação.

---

## Referências
- Arquivos: `api/src/Domain/Chat/Http/Controllers/WebChatMessageController.php`, `api/tests/Feature/Chat/WebChatMessageControllerTest.php`
- Changelog: `.context/DOCS/CHANGELOG/2026-05-10.md`

