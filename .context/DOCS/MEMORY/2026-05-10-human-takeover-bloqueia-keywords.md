# Memory: Atendimento humano bloqueia processamento de keywords no chatbot

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-10 |
| **Autor** | DEV agent |
| **Contexto** | Regra de negócio para WebChat: ao entrar em atendimento humano, chatbot não deve mais pesquisar palavras |
| **Tags** | api, chat, webchat, auto-reply, human-takeover, regras |

---

## Situação
Após habilitar retrigger de menu por keyword, foi definido que o sistema deve interromper totalmente a pesquisa de palavras-chave quando o ticket estiver em atendimento humano.

Sem esse bloqueio, jobs já enfileirados ou novos dispatches poderiam continuar executando respostas automáticas mesmo com agente humano no controle.

---

## Decisão / Aprendizado
Aplicar bloqueio em duas camadas:

1. **Entrada WebChat**: controller não despacha nenhuma automação quando `human_takeover_at` está ativo.
2. **Execução de Auto Reply**: responder revalida `human_takeover_at` antes de processar keywords, protegendo também jobs já enfileirados.

---

## Alternativas Consideradas
| Alternativa | Por que descartada |
|-------------|-------------------|
| Bloquear apenas no controller | Não protege jobs pendentes já enviados para a fila |
| Bloquear apenas no responder | Ainda gera dispatch desnecessário na entrada do WebChat |

---

## Consequências
### Positivas
- Atendimento humano passa a ter prioridade total sobre automação.
- Evita respostas automáticas concorrendo com agente humano.
- Reduz ruído operacional em tickets em takeover.

### Negativas / Trade-offs
- Mais uma checagem de ticket no responder por job processado.

---

## Referências
- Arquivos: `api/src/Domain/Chat/Http/Controllers/WebChatMessageController.php`, `api/src/Domain/Chat/Services/ChatAutoReplyResponder.php`
- Testes: `api/tests/Feature/Chat/WebChatMessageControllerTest.php`, `api/tests/Unit/Chat/ChatAutoReplyResponderTest.php`
- Changelog: `.context/DOCS/CHANGELOG/2026-05-10.md`

