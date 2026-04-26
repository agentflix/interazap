# Memory: Refatoração de Contexto e Skills dos Agents

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | 🧠 Decisão |
| **Data** | 2026-04-26 |
| **Autor** | ORCHESTRATOR / DOC |
| **Contexto** | Otimização de consumo de tokens em sessões agentic-heavy |
| **Tags** | agents, skills, tace, context, tokens, plan-template |

---

## Situação

Sessões de desenvolvimento com múltiplos subagentes estavam consumindo tokens excessivamente porque:
1. A regra 14 do AGENTS.md exigia que **todos** os 13 agents carregassem `senior-cognition` — mas a skill estava **vazia** (pasta `.claude/skills/senior-cognition/` sem `SKILL.md`). A regra era inerte e causava confusão.
2. Não havia instrução de escopo de contexto: subagentes liam arquivos de tasks inteiros (ex: FEAT-046-tasks com 451 linhas) mesmo precisando de apenas uma task específica.
3. O campo C do T.A.C.E permitia prosa vaga — tasks bem escritas eram resultado do autor, não exigência do framework.
4. O único PLAN existente (FEAT-042, 408 linhas) continha código de implementação completo, duplicando o conteúdo das tasks T.A.C.E.

---

## Decisão / Aprendizado

Quatro correções aplicadas simultaneamente:

### Decisão 1 — Remover regra 14 (`senior-cognition`)
A skill `senior-cognition` foi originalmente criada para integração com Minimax no Claude Code e estava vazia. A regra 14 foi removida e substituída por uma tabela de níveis de skills por agente. Subagentes de execução (BACKEND, FRONTEND, DBA, DOC, GIT_COMMIT) não carregam skills de raciocínio porque as tasks T.A.C.E já são prescritivas o suficiente.

### Decisão 2 — Contexto mínimo por agente
Adicionada seção `📦 Contexto Mínimo por Agente` ao AGENTS.md com tabela explícita do que cada agent lê sempre, lê se relevante, e **nunca lê**. O ORCHESTRATOR é o único com visão completa do PLAN.

### Decisão 3 — T.A.C.E prescritivo obrigatório
O campo C do template T.A.C.E foi expandido com:
- Regras por camada (Backend PHP, Frontend Angular, DBA)
- Template estruturado obrigatório (ANTES / DEPOIS / RESTRIÇÕES / EXEMPLO DE CHAMADA)
- Tabela de exemplos válido vs. inválido

### Decisão 4 — PLAN-TEMPLATE separado de implementação
Criado `.context/WORKFLOW/PLAN-TEMPLATE.md`. O PLAN passa a definir **contratos de interface** (apenas assinaturas) e **O QUÊ**. Código de implementação fica exclusivamente nas tasks T.A.C.E.

---

## Alternativas Consideradas

| Alternativa | Por que descartada |
|------------|-------------------|
| Criar conteúdo para `senior-cognition` e manter para todos os agents | Skill estava vazia e era legado de integração Minimax; o custo de tokens de carregar para todos não se justificava |
| Manter `senior-cognition` apenas para ORCHESTRATOR/ARCHITECT/REVIEWER | Descartada em favor de remoção total — overhead sem benefício claro dado que a skill nunca existiu com conteúdo |
| Tornar PLAN obrigatório para todas as features | Mantido como opcional; features P (simples) vão direto para tasks T.A.C.E sem necessidade de PLAN |

---

## Consequências

### Positivas
- Redução estimada de ~40-60% de tokens por subagente de execução (eliminação de skills desnecessárias + escopo de contexto)
- Redução estimada de ~30% de retrabalho por ambiguidade (T.A.C.E prescritivo)
- Redução estimada de ~25% de tokens em PLANs futuros (sem duplicação de código)
- Regras explícitas: agents sabem exatamente o que carregar sem improviso
- Caminho do T.A.C.E corrigido (era `tace-framework.md`, passou a `SKILL.md`)

### Negativas / Trade-offs
- FEAT-042-plan.md existente não foi retroativamente convertido (mantido como está)
- A tabela de contexto mínimo é uma diretriz — não há gate técnico que impeça um agent de ler mais do que deveria
- PLAN-TEMPLATE é um arquivo novo; equipe precisa adotá-lo conscientemente nas próximas features M/G

---

## Referências
- `AGENTS.md` — regras absolutas, tabela de agents, skills por agent, contexto mínimo
- `.claude/skills/tace-framework/SKILL.md` — template C prescritivo
- `.context/WORKFLOW/PLAN-TEMPLATE.md` — template de PLAN novo
- `FEAT-042-plan.md` — exemplo de PLAN anterior com código completo (padrão a ser abandonado)
