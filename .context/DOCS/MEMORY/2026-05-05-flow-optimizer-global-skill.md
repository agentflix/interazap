# Memory: Skill global flow-optimizer para execução técnica mais rápida

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisão |
| **Data** | 2026-05-05 |
| **Autor** | Codex |
| **Contexto** | Padronização de produtividade operacional no monorepo |
| **Tags** | skill, produtividade, workflow, monorepo, qualidade |

---

## Situação
> O que estava acontecendo? Qual o contexto?

As skills existentes cobriam planejamento e domínios específicos, mas faltava um fluxo transversal com técnicas objetivas para reduzir tempo de diagnóstico, pesquisa de arquivos e seleção de testes em qualquer workspace.

---

## Decisão / Aprendizado
> O que foi decidido ou aprendido?

Foi criada a skill `.claude/skills/flow-optimizer/SKILL.md` com foco em otimização de execução técnica global (api/gateway/app/electron), além do comando `/optimize-flow` para operacionalizar o uso.

---

## Alternativas Consideradas
> O que foi descartado e por quê?

| Alternativa | Por que descartada |
|-------------|-------------------|
| Manter apenas skills por domínio | não resolve ganho de velocidade transversal em tarefas técnicas gerais |
| Criar skill de velocidade máxima | aumenta risco de regressão por reduzir checkpoints críticos |

---

## Consequências
> O que muda por causa disso?

### Positivas
- Roteiro único para buscar contexto e reduzir retrabalho.
- Melhor consistência na ordem de validação (focal -> contexto -> amplo).
- Reuso imediato via slash command `/optimize-flow`.

### Negativas / Trade-offs
- Pequeno overhead inicial para manter artefatos de cache operacional.
- Disciplina necessária para manter escopo travado durante execução.

---

## Referências
- Skill: `.claude/skills/flow-optimizer/SKILL.md`
- Comando: `.claude/commands/optimize-flow.md`
- Changelog: `.context/DOCS/CHANGELOG/2026-05-05.md`
