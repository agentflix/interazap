# Memory: Skill canonical-crm-flow para bugfix/refactor de contrato CRM

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-05 |
| **Autor** | Codex |
| **Contexto** | Otimização do fluxo de trabalho após correções em negociação/contato CRM |
| **Tags** | skill, fluxo, crm, qualidade, produtividade |

---

## Situação
> O que estava acontecendo? Qual o contexto?

Correções de contrato no CRM exigiram múltiplos ciclos de leitura, patch e validação entre frontend/backend, com risco de retrabalho por falta de cache de contexto e seleção de testes pouco focada.

---

## Decisão / Aprendizado
> O que foi decidido ou aprendido?

Foi criada a skill `.claude/skills/canonical-crm-flow/SKILL.md` para padronizar um fluxo operacional de bugfix/refactor canônico com:
- cache explícito de contexto
- lock de escopo por bounded context
- sequência de validação incremental (focado -> contexto -> amplo)
- checklist de fechamento PREVC

---

## Alternativas Consideradas
> O que foi descartado e por quê?

| Alternativa | Por que descartada |
|-------------|-------------------|
| Manter fluxo ad-hoc por tarefa | aumenta variabilidade, retrabalho e risco de omissão de validações |
| Criar comando único rígido para todos os casos | reduz flexibilidade para diferenças entre bugfix, refactor e migração estrita |

---

## Consequências
> O que muda por causa disso?

### Positivas
- Menor tempo de diagnóstico e validação em bugs de contrato.
- Redução de regressões por padronização de gates.
- Melhor rastreabilidade técnica (cache de contexto e decisões).

### Negativas / Trade-offs
- Overhead inicial de registrar cache e checklist.
- Necessidade de disciplina para seguir o fluxo em tasks rápidas.

---

## Referências
- Skill: `.claude/skills/canonical-crm-flow/SKILL.md`
- Changelog: `.context/DOCS/CHANGELOG/2026-05-05.md`
