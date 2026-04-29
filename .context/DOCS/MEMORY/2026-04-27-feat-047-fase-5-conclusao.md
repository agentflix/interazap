# Memory: FEAT-047 Fase 5 — Critério de conclusão com ressalvas explícitas

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | 📚 Aprendizado |
| **Data** | 2026-04-27 |
| **Autor** | DOC (Copilot) |
| **Contexto** | FEAT-047 / TASK-047.10 / TASK-047.11 / fechamento da Fase 5 |
| **Tags** | feat-047, push, qa, escopo, ressalvas |

---

## Situação
> O que estava acontecendo? Qual o contexto?

A implementação da Fase 5 (backend + frontend de push notifications) estava concluída e com testes de escopo aprovados, mas havia dois pontos que podiam gerar fechamento inconsistente: uma falha global de PSR-4 strict preexistente fora do escopo da task e a ausência de evidência manual em devices reais para cenários de push em background.

---

## Decisão / Aprendizado
> O que foi decidido ou aprendido?

Para fechamento de fase, foi adotado o critério:

1. **Conclusão por escopo com rastreabilidade explícita**: TASK-047.10 e TASK-047.11 podem ser marcadas como concluídas quando os testes de escopo passam, desde que ressalvas fora de escopo sejam registradas formalmente no TASK e no CHANGELOG.
2. **Push mobile exige evidência manual complementar**: testes automatizados (incluindo re-registro de token) não substituem validação em device real para recebimento em background e ação de toque.

---

## Alternativas Consideradas
> O que foi descartado e por quê?

| Alternativa | Por que descartada |
|------------|-------------------|
| Bloquear conclusão da fase por falha PSR-4 strict global preexistente | Mistura débito histórico global com critério de aceite da task e impede avanço de escopo já validado |
| Concluir fase sem registrar ressalvas | Perde transparência operacional e dificulta auditoria de risco residual |
| Aceitar apenas testes automatizados para push | Não cobre comportamento real de background/tap em iOS/Android |

---

## Consequências
> O que muda por causa disso?

### Positivas
- Fechamento de fase fica auditável sem esconder risco residual
- Critério de escopo evita bloqueio por problemas globais não introduzidos pela task
- Pendência de validação manual fica visível para a próxima etapa de QA

### Negativas / Trade-offs
- A fase pode ser considerada concluída com itens manuais ainda pendentes
- Exige disciplina de documentação para não normalizar ressalvas indefinidamente

---

## Referências
- Feature: .context/DOCS/FEATURES/FEAT-047-mobile-app-capacitor.md
- Task: .context/DOCS/TASKS/FEAT-047-tasks.md
- Changelog: .context/DOCS/CHANGELOG/2026-04-27.md
