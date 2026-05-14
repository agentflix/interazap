# Memory: Fila `auto-reply` precisa de supervisor dedicado no Horizon

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-10 |
| **Autor** | DEV agent |
| **Contexto** | Incidente de WebChat sem resposta de menu para `teste@teste.com.br` (plano starter) |
| **Tags** | api, chat, webchat, auto-reply, queue, horizon, operacao |

---

## Situação
Mesmo com fallback WebChat → Auto Reply implementado, a conta `teste@teste.com.br` continuava sem resposta do menu.

Diagnóstico mostrou:
- Regras de auto-reply ativas no tenant.
- Mensagens WebChat inbound persistindo normalmente.
- Job `ChatAutoReplyRespondJob` sendo enfileirado.
- Fila `auto-reply` sem consumo contínuo e backlog.

---

## Decisão / Aprendizado
Adicionar `supervisor-auto-reply` no Horizon para consumo dedicado da fila `auto-reply`, separando chatbot/menu das filas genéricas e evitando starvation operacional.

---

## Alternativas Consideradas
| Alternativa | Por que descartada |
|-------------|-------------------|
| Incluir `auto-reply` no supervisor genérico junto de `default/sentiment/media` | Maior risco de latência variável em horários de pico |
| Manter processamento manual com `queue:work` sob demanda | Não resolve operação contínua em produção |

---

## Consequências
### Positivas
- Respostas de menu/chatbot voltam a sair automaticamente no WebChat sem intervenção manual.
- Reduz backlog da fila `auto-reply`.
- Dá previsibilidade de latência para o fluxo de onboarding (boas-vindas/menu).

### Negativas / Trade-offs
- Mais um supervisor para monitorar em operação.

---

## Referências
- Arquivo: `api/config/horizon.php`
- Changelog: `.context/DOCS/CHANGELOG/2026-05-10.md`

