# Memory: Setup da Estrutura AI-First com PREVC

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | 🧠 Decisão |
| **Data** | 2026-04-11 |
| **Autor** | Setup automático |
| **Contexto** | Configuração inicial do projeto para desenvolvimento com IA |
| **Tags** | setup, estrutura, prevc, ai-first |

---

## Situação
Projeto InteraZap precisava de uma estrutura que permitisse desenvolvimento eficiente com IA,
com contexto adequado, workflow definido e qualidade garantida.

---

## Decisão
Adotar estrutura AI-First com:
- **AGENTS.md** como fonte da verdade (symlink via CLAUDE.md)
- **PREVC** como workflow obrigatório (Planning → Review → Execution → Validation → Confirm)
- **T.A.C.E** como framework de decomposição de tarefas
- **Agents especializados** gerados para a stack: Laravel 12 + Angular 20 + PostgreSQL
- **Hooks** com router.js para roteamento automático de tarefas
- **CHANGELOG** diário para registro factual
- **MEMORY** para decisões e aprendizados persistentes

---

## Consequências

### Positivas
- IA sempre tem contexto adequado via AGENTS.md
- Tasks nunca são vagas (T.A.C.E garante especificidade)
- Qualidade garantida via gates inegociáveis
- Conhecimento não se perde (MEMORY)
- Histórico rastreável (CHANGELOG)

### Trade-offs
- Setup inicial requer investimento de tempo
- Disciplina necessária para manter docs atualizados
- Overhead de processo para mudanças muito pequenas
