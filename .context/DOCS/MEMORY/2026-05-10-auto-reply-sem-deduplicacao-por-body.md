# Memory: Auto-reply sem deduplicação por body para permitir repetição de menu/opções

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-10 |
| **Autor** | DEV agent |
| **Contexto** | Ajuste final para permitir que o usuário repita `menu` e opções (`1`, `2`, etc.) sem bloqueio temporal |
| **Tags** | api, chat, queue, auto-reply, ux |

---

## Situação
Mesmo após remover cooldown no `ChatAutoReplyResponder`, ainda havia bloqueio quando o usuário repetia o mesmo texto rapidamente no mesmo ticket.

A causa foi a deduplicação no nível da fila: `ChatAutoReplyRespondJob` implementava `ShouldBeUnique` com `uniqueId = ticketId + sha1(body)` e janela de 60s.

---

## Decisão / Aprendizado
Remover deduplicação por conteúdo no job de auto-reply:

- remover `ShouldBeUnique`;
- remover `uniqueFor` e `uniqueId()`.

Com isso, mensagens idênticas consecutivas passam a gerar jobs independentes, preservando navegação conversacional livre.

---

## Alternativas Consideradas
| Alternativa | Por que descartada |
|-------------|-------------------|
| Reduzir `uniqueFor` para poucos segundos | Ainda cria bloqueio temporal perceptível para o usuário |
| Incluir timestamp no `uniqueId` | Na prática elimina dedupe e adiciona complexidade sem benefício |

---

## Consequências
### Positivas
- `menu` e opções podem ser repetidos livremente.
- Comportamento consistente com requisito de chatbot por etapas.

### Negativas / Trade-offs
- Menor proteção contra mensagens duplicadas acidentais no mesmo segundo.

---

## Referências
- Arquivos: `api/src/Domain/Chat/Jobs/ChatAutoReplyRespondJob.php`, `api/tests/Unit/Chat/ChatAutoReplyResponderTest.php`
- Changelog: `.context/DOCS/CHANGELOG/2026-05-10.md`
