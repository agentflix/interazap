# Memory: Quebra prematura de linha no balão do webchat externo

## Metadados

| Campo        | Valor                                          |
| ------------ | ---------------------------------------------- |
| **Tipo**     | Aprendizado                                    |
| **Data**     | 2026-04-21                                     |
| **Autor**    | DEBUG                                          |
| **Contexto** | Correção de bug visual no chat externo público |
| **Tags**     | webchat, angular, ui, tailwind, chat-bubble    |

---

## Situação

Mensagens digitadas em uma linha no webchat externo apareciam visualmente quebradas em duas linhas, sem o usuário pressionar Enter.

---

## Decisão / Aprendizado

A causa raiz estava no layout da linha de mensagem: o container era apenas `flex` (sem largura explícita), gerando cálculo shrink-to-fit.
Com isso, o `max-w-[85%]` do balão era calculado sobre uma largura menor que a área real do chat, antecipando a quebra de linha.

Correção aplicada: garantir largura total da linha de mensagem no template com `class="flex w-full"`.

---

## Alternativas Consideradas

| Alternativa                            | Por que descartada                                                                   |
| -------------------------------------- | ------------------------------------------------------------------------------------ |
| Remover `whitespace-pre-wrap` do balão | Perderia suporte legítimo a quebras de linha inseridas via Shift+Enter.              |
| Alterar apenas `max-w-[85%]` do balão  | Não corrige a raiz (container shrink-to-fit), só mascara em alguns tamanhos de tela. |

---

## Consequências

### Positivas

- Mensagens de uma linha deixam de quebrar visualmente antes do necessário.
- Comportamento mais previsível entre desktop e widget embed.

### Negativas / Trade-offs

- Nenhum impacto funcional esperado; apenas ajuste de layout.

---

## Referências

- `app/src/app/pages/webchat/components/chat-window/chat-window.component.html`
- `app/src/app/pages/webchat/components/chat-window/chat-window.component.spec.ts`
- Changelog: `.context/DOCS/CHANGELOG/2026-04-21.md`
