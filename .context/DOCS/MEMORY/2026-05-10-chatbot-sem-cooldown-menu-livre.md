# Memory: Chatbot sem cooldown para navegação livre de menu

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-10 |
| **Autor** | DEV agent |
| **Contexto** | Ajuste de UX no fluxo de auto-reply para permitir retorno ao menu principal a qualquer momento |
| **Tags** | api, chat, auto-reply, ux, menu |

---

## Situação
No fluxo atual, o `ChatAutoReplyResponder` aplicava cooldown por regra (inclusive na regra de boas-vindas/menu). Isso bloqueava o retrigger imediato de `menu` e atrapalhava a navegação por etapas do usuário.

---

## Decisão / Aprendizado
Desativar a aplicação de cooldown no motor de auto-reply:

- remover checagem de `inCooldown(...)` antes de responder;
- remover gravação de cooldown (`setCooldown(...)`) após resposta.

Com isso, o chatbot passa a responder sempre que houver match de trigger, permitindo retorno instantâneo ao menu principal.

---

## Alternativas Consideradas
| Alternativa | Por que descartada |
|-------------|-------------------|
| Desabilitar cooldown só na regra de welcome | Resolve o menu, mas mantém fricção em etapas/keywords subsequentes |
| Reduzir cooldown para poucos segundos | Ainda cria bloqueio temporal e inconsistência de UX |

---

## Consequências
### Positivas
- Navegação conversacional mais natural (menu sempre acessível).
- Menos confusão do usuário ao voltar para etapas anteriores.

### Negativas / Trade-offs
- Pode aumentar repetição de respostas em caso de mensagens duplicadas do usuário.

---

## Referências
- Arquivos: `api/src/Domain/Chat/Services/ChatAutoReplyResponder.php`, `api/tests/Unit/Chat/ChatAutoReplyResponderTest.php`
- Changelog: `.context/DOCS/CHANGELOG/2026-05-10.md`
