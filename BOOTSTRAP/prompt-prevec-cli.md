# PREVEC — AI-First com Workflow PREVC

## SUA MISSÃO

Configurar a estrutura AI-First neste projeto.
Agents gerados com base no contexto REAL — nunca genéricos.
Siga TODOS os passos na ordem. Não pule nenhum.

**PRINCÍPIO CENTRAL:** Não invente conteúdo inline. Leia os arquivos em `prevec-assets/` antes de cada passo relevante e siga o que eles mandam.

> `prevec-assets/` está na mesma pasta deste prompt (`BOOTSTRAP/`). Todos os paths de assets são relativos a este diretório.

---

## PASSO 0 — DETECÇÃO DO PROJETO

### 0.1 — Identificar cenário

```bash
echo "=== DETECÇÃO DO PROJETO ==="

echo "--- Buscando spec.md ---"
find . -maxdepth 3 -name "spec.md" -o -name "SPEC.md" -o -name "spec.yaml" 2>/dev/null

echo "--- Buscando manifesto de dependências ---"
ls package.json composer.json pyproject.toml Cargo.toml go.mod pom.xml build.gradle *.csproj 2>/dev/null

echo "--- Buscando estrutura existente ---"
ls -la .claude/ .codex/ .opencode/ .context/ 2>/dev/null
```

Registre quais dessas pastas já existem — usadas no Passo 1.

### 0.2 — Extrair contexto do projeto

**Cenário A (spec.md encontrado):** leia o spec.md completamente e extraia:
`PROJECT_NAME`, `PROJECT_DESCRIPTION`, `BACKEND_LANG`, `BACKEND_FRAMEWORK`, `FRONTEND_LANG`, `FRONTEND_FRAMEWORK`, `DATABASE`, `ARCHITECTURE`, `ARCHITECTURE_LAYERS`, `ARCHITECTURE_RULES`, `MODULES`, `CONVENTIONS`, `BUSINESS_RULES`, `TESTING_STACK`, `INFRA`

**Cenário B (código sem spec.md):** analise dependências, estrutura de pastas, patterns, testes, linters e infira as mesmas variáveis.

**Cenário C (já tem .claude/, .codex/, .opencode/ ou .context/):**
- NÃO sobrescreva agents com conteúdo especializado existente
- APENAS adicione o que falta
- Renomeie PLANS → FEATURES se existir

### 0.3 — Registrar contexto detectado

```
CONTEXTO DETECTADO:
- Cenário: [A/B/C]
- PROJECT_NAME: [valor]
- BACKEND: [LANG + FRAMEWORK]
- FRONTEND: [LANG + FRAMEWORK]
- DATABASE: [valor]
- ARCHITECTURE: [padrão + camadas]
- ARCHITECTURE_RULES: [regras invioláveis]
- MODULES: [lista]
- TESTING: [stack]
- CONVENTIONS: [padrões]
- BUSINESS: [regras de negócio]
```

### 0.4 — Perguntas obrigatórias

Pergunte ao usuário ANTES de continuar:

1. **CHANGELOG:** Quer usar registro diário de mudanças? (sim/não)
2. **MEMORY:** Quer usar memória persistente de decisões e aprendizados? (sim/não)
3. **Ferramentas ativas:** Quais ferramentas de AI o projeto usa? (determina onde criar symlinks para `.context/agents/` e `.context/skills/`)
   - `.claude/` (Claude Code)
   - `.opencode/`
   - `.codex/`
   - Todas

Registre as respostas. Elas condicionam os passos seguintes.

---

## PASSO 1 — ESTRUTURA DE PASTAS

Crie as pastas que ainda não existem. Baseie-se nas pastas detectadas no Passo 0.

```bash
# Fonte única de agents e skills — todas as ferramentas apontam aqui via symlink
mkdir -p .context/agents
mkdir -p .context/skills

# .claude/ — sempre criada; agents e skills são symlinks para .context/
mkdir -p .claude
if [ -d ".claude/agents" ] && [ ! -L ".claude/agents" ]; then
  # Cenário C: mover agents existentes para .context/agents antes de symlinkar
  cp -rn .claude/agents/. .context/agents/ 2>/dev/null || true
  rm -rf .claude/agents
fi
ln -sf ../.context/agents .claude/agents
ln -sf ../.context/skills .claude/skills

# .codex/ — criar se detectado ou escolhido no Passo 0.4
if [ -d ".codex" ] || [[ "$TOOLS_ACTIVE" == *".codex"* ]]; then
  mkdir -p .codex
  if [ -d ".codex/agents" ] && [ ! -L ".codex/agents" ]; then
    cp -rn .codex/agents/. .context/agents/ 2>/dev/null || true
    rm -rf .codex/agents
  fi
  ln -sf ../.context/agents .codex/agents
  ln -sf ../.context/skills .codex/skills
fi

# .opencode/ — criar se detectado ou escolhido no Passo 0.4
if [ -d ".opencode" ] || [[ "$TOOLS_ACTIVE" == *".opencode"* ]]; then
  mkdir -p .opencode
  if [ -d ".opencode/agents" ] && [ ! -L ".opencode/agents" ]; then
    cp -rn .opencode/agents/. .context/agents/ 2>/dev/null || true
    rm -rf .opencode/agents
  fi
  ln -sf ../.context/agents .opencode/agents
  ln -sf ../.context/skills .opencode/skills
fi

# .context/ — sempre criada
mkdir -p .context/ARCHITECTURE
mkdir -p .context/DOCS/FEATURES
mkdir -p .context/DOCS/TASKS
mkdir -p .context/DOCS/PRDS
mkdir -p .context/DESIGN
mkdir -p .context/WORKFLOW
mkdir -p .context/.session

# Condicional CHANGELOG
if [[ "$USE_CHANGELOG" == "sim" ]]; then
  mkdir -p .context/DOCS/CHANGELOG
fi

# Condicional MEMORY
if [[ "$USE_MEMORY" == "sim" ]]; then
  mkdir -p .context/DOCS/MEMORY
fi

# Migrar PLANS se existir
if [ -d ".context/DOCS/PLANS" ]; then
  cp -rn .context/DOCS/PLANS/* .context/DOCS/FEATURES/ 2>/dev/null || true
  mv .context/DOCS/PLANS ".context/DOCS/PLANS_BACKUP_$(date +%Y%m%d)"
fi
```

### 1.1 — ORCHESTRATOR como agent padrão

O Claude Code aceita `agent` em `.claude/settings.json` — aplica o system prompt, as tools e o
modelo daquele agent à thread principal. Sem isso o ORCHESTRATOR só entra quando invocado à mão.

Crie (ou faça merge em) `.claude/settings.json` — arquivo de projeto, commitável:

```json
{
  "agent": "ORCHESTRATOR"
}
```

Regras:
- **Merge, nunca sobrescreva** — se o arquivo já existir, adicione só a chave `agent`.
- Não use `.claude/settings.local.json` para isso: a configuração vale para o time todo.
- O valor deve bater com o `name:` do frontmatter em `.context/agents/ORCHESTRATOR.md`.
- Reflita a decisão no AGENTS.md, na seção "Agent padrão" (ver Passo 5).

---

## PASSO 2 — ARQUIVOS DE ARCHITECTURE

Gere com base no contexto do Passo 0. Substitua TODAS as variáveis por dados reais.

Arquivos a criar em `.context/ARCHITECTURE/`:

| Arquivo | Conteúdo |
|---|---|
| `architecture.md` | Diagrama Mermaid das camadas reais (bloco ```mermaid``` em markdown) |
| `modules.yaml` | Definição dos módulos/bounded contexts reais |
| `modules.md` | Mapa visual das dependências entre módulos (bloco ```mermaid``` em markdown) |
| `dependencies.yaml` | Regras de dependência entre módulos e camadas — **apenas regras bloqueantes**, sem comentários ou exemplos. Agents carregam este arquivo inteiro; manter compacto (< 80 linhas) |
| `project-state.yaml` | Estado atual: stack, métricas, módulos |
| `project-brain.yaml` | Metadados para IA: identidade, decisões, regras de negócio |
| `context-version.yaml` | Versionamento dos arquivos de contexto |
| `user-flow.md` | Fluxos principais do usuário (bloco ```mermaid``` em markdown) |
| `context-snapshot.md` | **Cache lean** — campos mais lidos de todos os arquivos acima em ~40 linhas |

**Gerar `context-snapshot.md` por último**, após todos os outros arquivos, usando este formato exato:

```markdown
# Context Snapshot
> Cache gerado pelo bootstrap. Regenerar quando context-version.yaml mudar.
> Fonte: project-brain.yaml + architecture.md + modules.yaml + dependencies.yaml

## Stack
Backend: [BACKEND_LANG + BACKEND_FRAMEWORK] | Frontend: [FRONTEND_LANG + FRONTEND_FRAMEWORK]
Database: [DATABASE] | Testes: [TESTING_STACK]
Arquitetura: [ARCHITECTURE] | Camadas: [LAYER_A → LAYER_B → LAYER_C → LAYER_D]

## Regras Invioláveis
1. [regra real do projeto]
2. [regra real do projeto]
[N regras — TODAS as regras de project-brain.yaml sem omitir nenhuma: arquitetura, contratos de performance, idempotência, segurança, convenções obrigatórias]

## Módulos e Dependências
| Módulo | Pode importar | Proibido |
|---|---|---|
| [módulo-a] | [módulo-b, shared] | [módulo-c] |

## Convenções
- [convenção de código real — ex: PSR-12, Angular Style Guide]
- [padrão de nomenclatura]
```

**Formato dos arquivos de diagrama:** sempre `.md` com o diagrama dentro de um bloco de código mermaid:

````markdown
# [Título do Diagrama]

> [Descrição curta]

```mermaid
[conteúdo do diagrama]
```
````

**Importante:** adapte o diagrama de arquitetura para o padrão REAL detectado (DDD, MVC, Clean, Hexagonal). Nunca use placeholders quando tiver dados reais.

---

## PASSO 3 — TEMPLATES CHANGELOG E MEMORY

**Execute apenas se o usuário respondeu sim no Passo 0.4.**

### CHANGELOG (se sim)

Criar em `.context/DOCS/CHANGELOG/`:
- `_TEMPLATE.md` — template de entrada diária
- `README.md` — convenções e instruções
- `[DATA-HOJE].md` — changelog inicial do setup

Formato de entrada:
```
- [HH:MM] [TIPO] [[escopo]]: Descrição concisa
  - Detalhes relevantes
  - Arquivos: path/to/file
  - Ref: FEAT-NNN / TASK-NNN
```

Tipos: `FEAT`, `FIX`, `REFACTOR`, `DOCS`, `TEST`, `CHORE`, `BREAKING`

### MEMORY (se sim)

Criar em `.context/DOCS/MEMORY/`:
- `_TEMPLATE.md` — template de decisão/aprendizado
- `README.md` — tipos e convenções
- `[DATA-HOJE]-setup-ai-first.md` — memory inicial do setup

Campos obrigatórios: Tipo, Data, Autor, Contexto, Tags, Situação, Decisão, Alternativas Consideradas, Consequências.
Tipos: Decisão, Aprendizado, Armadilha, Insight.

---

## PASSO 4 — GERAR AGENTS ESPECIALIZADOS

**Antes de criar cada agent:**
1. Leia o template correspondente em `prevec-assets/agents/{NOME}.md`
2. Substitua TODAS as variáveis por dados REAIS do Passo 0
3. Formato de saída: `.claude/agents/{NOME}.md`

**Cenário C:** não sobrescreva agents existentes com conteúdo especializado.

### Agents a gerar (4 principais + 6 subagents)

| Agent | Template | Fase PREVC | Modelo | Papel |
|---|---|---|---|---|
| `ORCHESTRATOR.md` | `prevec-assets/agents/ORCHESTRATOR.md` | Todas | sonnet | Ponto de entrada — classifica e delega |
| `PLANNER.md` | `prevec-assets/agents/PLANNER.md` | Pré-Planning + Planning | sonnet | BRANDING + PM + ARCHITECT + DESIGNER |
| `BUILDER.md` | `prevec-assets/agents/BUILDER.md` | Execution | sonnet | Router de implementação |
| `REVIEWER.md` | `prevec-assets/agents/REVIEWER.md` | Review + Validation + Confirm | sonnet | Router de revisão |

**BUILDER e REVIEWER são thin routers** — não executam diretamente. Gere também os subagents,
cada um com `name`, `model`, `description` e `tools` no frontmatter:

| Subagent | Modelo | Tools | Quando |
|---|---|---|---|
| `builder-explore.md` | haiku | Read, Bash, Glob | Mapear código e canônicos antes de escrever |
| `builder-write.md` | sonnet | Read, Write, Edit, Bash, Glob | Implementar com plano claro |
| `builder-debug.md` | opus | todas | Causa raiz, bug multifatorial, `builder-write` falhou |
| `reviewer-doc.md` | haiku | Read, Bash | Validar feature docs e tasks T.A.C.E |
| `reviewer-code.md` | sonnet | Read, Bash, Agent | 7 subagents + gates completos |
| `reviewer-confirm.md` | haiku | Read, Write, Edit, Bash | Fechar task — MEMORY, state files, commit |

> Modelo por custo: exploração e confirmação em Haiku, implementação e review em Sonnet,
> debugging em Opus. O router nunca herda o modelo do subagent.

### Processo de geração

Para cada agent:
1. Ler o template em `prevec-assets/agents/{NOME}.md`
2. Ler as "Instruções de preenchimento" no final do template
3. Substituir todas as variáveis `[VARIAVEL]` por dados reais do Passo 0
4. Substituir a variável de path de skills (os templates usam `[SKILLS_DEST]` como placeholder):
   - `[SKILLS_DEST]` → `.context/skills`
5. Gerar `.context/agents/{NOME}.md` com o conteúdo final (symlinks em `.claude/agents/`, `.codex/agents/`, `.opencode/agents/` já apontam para esta pasta)
   > Nota: os templates referenciam `.claude/agents/` em seu cabeçalho — via symlink é equivalente a `.context/agents/`.
6. Verificar: nenhuma variável `[...]` deve permanecer no arquivo gerado

---

## PASSO 5 — CRIAR AGENTS.md

**AGENTS.md é carregado em TODA conversa — deve ter no máximo 100 linhas.**
Regra: links e referências, nunca conteúdo inline duplicado dos agent files.

Ao finalizar, contar linhas:
```bash
wc -l AGENTS.md
```
Se > 100: cortar nesta ordem de prioridade (do menos ao mais importante):
1. Descrições longas na tabela de Architecture → reduzir a 2-3 palavras
2. Tabela de Architecture → substituir por link único: `> Ver detalhes: .context/ARCHITECTURE/`
3. Descrições na tabela de Agents → 1 palavra por coluna Absorve
4. Regras absolutas redundantes com os arquivos de agent → remover

Crie `AGENTS.md` na raiz com dados REAIS. Inclua:

- Identidade do projeto (nome, stack, arquitetura)
- Regras absolutas (incluindo: "REVIEWER executa `code-review-confiavel` ao final de toda task" e "Todo agent mostra o próximo comando com argumentos reais ao final de cada ação concluída")
- Mapa de contexto (`.context/agents/`, `.context/skills/`, `.context/ARCHITECTURE/`, `.context/DESIGN/`, `.context/DOCS/`, `.context/WORKFLOW/`)
- Tabela PREVC atualizada (REVIEWER cobre Review + Validation)
- Seções CHANGELOG e MEMORY (condicionais ao Passo 0.4)
- Tabela de agents (4 agents consolidados):
- Framework T.A.C.E
- **Índice de Architecture** — tabela com link e descrição de cada arquivo:

```markdown
## 🏗️ Architecture

| Arquivo | Descrição |
|---|---|
| `.context/ARCHITECTURE/architecture.md` | Diagrama de camadas da arquitetura |
| `.context/ARCHITECTURE/modules.md` | Mapa de módulos e dependências |
| `.context/ARCHITECTURE/user-flow.md` | Fluxos principais do usuário |
| `.context/ARCHITECTURE/modules.yaml` | Definição dos módulos/bounded contexts |
| `.context/ARCHITECTURE/dependencies.yaml` | Regras de dependência entre módulos |
| `.context/ARCHITECTURE/project-state.yaml` | Estado atual: stack, métricas, módulos |
| `.context/ARCHITECTURE/project-brain.yaml` | Metadados para IA: identidade, decisões, regras |
| `.context/ARCHITECTURE/context-version.yaml` | Versionamento dos arquivos de contexto |

> Antes de qualquer decisão técnica: consulte `project-brain.yaml` e `architecture.md`.

## 🎨 Design

| Pasta | Descrição |
|---|---|
| `.context/DESIGN/` | Wireframes, mockups, specs de UI e design system |

> **OBRIGATÓRIO para tasks de Frontend:** consultar `.context/DESIGN/` antes de implementar qualquer componente, página ou fluxo visual.
```

```markdown
## 🤖 Agents

| Agent | Fase PREVC | Modelo | Papel |
|---|---|---|---|
| ORCHESTRATOR | Todas | Sonnet | Ponto de entrada — classifica e delega, nunca implementa |
| PLANNER | Planning | Sonnet | BRANDING + PM + ARCHITECT + DESIGNER |
| BUILDER | Execution | Sonnet | Router — explore / write / debug |
| REVIEWER | Review + Validation + Confirm | Sonnet | Router — doc / code / confirm |
```

### Seção obrigatória: Agent padrão

O AGENTS.md deve abrir (logo após a identidade) com a tabela de roteamento do ORCHESTRATOR:

```markdown
## 🧭 Agent padrão

**ORCHESTRATOR é o ponto de entrada de toda sessão** (`agent: ORCHESTRATOR` em `.claude/settings.json`).

| Escopo | Rota |
|---|---|
| Ideia, feature, decisão de arquitetura | PLANNER — `/prevec-decompose-plan` |
| Task T.A.C.E já definida | BUILDER — `/prevec-execute-task` |
| 1–2 arquivos, um bounded context, sem arquivo novo | VIBE-CODER |
| Código pronto aguardando revisão | REVIEWER — `/prevec-phase-close` |
```

Remova qualquer referência a: `symlink CLAUDE.md`, `.claude/hooks/`, agentes antigos (QA, PM, ARCHITECT, BACKEND, FRONTEND, DEV, DBA, DEBUG, DOC, GIT_COMMIT, DESIGNER, BRANDING).
A única menção legítima a `settings.json` é a chave `agent: ORCHESTRATOR` do Passo 1.1.

---

## PASSO 6 — WORKFLOW PREVC

Crie `.context/WORKFLOW/PREVC.md` com as fases completas.

O arquivo deve abrir com a tabela de roteamento do ORCHESTRATOR e detalhar as fases
Planning → Review → Execution → Phase-close.

### Regra inviolável: REVIEWER aprova antes do CONFIRM

Nenhuma task chega ao CONFIRM sem aprovação explícita do REVIEWER — sem exceção, sem atalho.

**Por quê:**
- Detecta alucinações do BUILDER (imports inexistentes, assinaturas erradas, dead code)
- Verifica quebra de contrato entre camadas (API ↔ frontend, Domain ↔ Infrastructure)
- Garante que o código implementado bate com a task T.A.C.E (grounding)
- Reduz falsos positivos via meta-review dos 7 revisores
- Captura regressões antes do commit

O review roda em **subagent distinto** para não contaminar o contexto do BUILDER.

### Granularidade: por fase, não por task

Testes e review não rodam a cada task — rodam no fechamento da fase, via `/prevec-phase-close`:
menos ciclos de gate, 1 commit por fase, review completo concentrado na última fase.

### Checklist de PHASE-CLOSE (obrigatório)

- [ ] Nenhuma task da fase ainda ⏳ Pendente
- [ ] BUILDER Log preenchido no session file para cada task
- [ ] Testes da fase passando (só a camada tocada)
- [ ] 1 commit por fase, Conventional Commits
- [ ] Entrada no CHANGELOG do dia *(se CHANGELOG ativo)*
- [ ] MEMORY escrito se houve decisão, armadilha ou aprendizado *(se MEMORY ativo)*
- [ ] `project-state.yaml` atualizado (métricas)
- [ ] **Última fase:** `code-review-confiavel` com 7 revisores + gates completos + PR

> Fase não fecha com gate vermelho — nunca commitar por cima de teste quebrado.

---

## PASSO 7 — VALIDATION FLOW

Crie `.context/WORKFLOW/validation-flow.md` com gates REAIS da stack detectada.

- Use comandos reais do projeto — não placeholders
- Substitua qualquer referência a `@QA` por `@REVIEWER`
- Include: como executar `code-review-confiavel` neste projeto

---

## PASSO 8 — INSTALAR SKILLS EXTERNAS

Skills vão direto para `.context/skills/` — fonte única. Symlinks criados no Passo 1 propagam automaticamente para `.claude/skills/`, `.codex/skills/`, `.opencode/skills/`.

```bash
cp -r prevec-assets/skills/code-review-confiavel/ .context/skills/
cp -r prevec-assets/skills/brainstorming/ .context/skills/
```

Copiar também o modelo de contexto do ORCHESTRATOR e o session model:

```bash
cp prevec-assets/orchestrator-context-model.md .context/orchestrator-context-model.md
cp prevec-assets/session-model.md .context/.session/_TEMPLATE.md
cp prevec-assets/planning-session-model.md .context/.session/_PLANNING_TEMPLATE.md
```

Confirme ao usuário: "Skills instaladas em .context/skills/ (acessíveis via symlinks em todas as ferramentas ativas)."

---

## PASSO 9 — TEMPLATES

### Features e Tasks

Criar em `.context/DOCS/FEATURES/` e `.context/DOCS/TASKS/`:
- `_TEMPLATE.md` — template com campos obrigatórios
- `README.md` — convenções e fluxo

### PRDs

Criar em `.context/DOCS/PRDS/`:
- `_TEMPLATE.md` — PRD completo com seções: Visão Geral, Problema, Solução, Usuários, Requisitos Funcionais, Requisitos Não-Funcionais, Critérios de Aceite, Wireframes, Dependências, Riscos, Cronograma, Revisões
- `README.md` — convenções e fluxo

**Convenção de nome de PRD:** `0001-PRD-<topic-kebab>.md` (numeração sequencial)

### Design

Copiar template de `prevec-assets/design/_TEMPLATE.md` para `.context/DESIGN/`:

```bash
cp prevec-assets/design/_TEMPLATE.md .context/DESIGN/_TEMPLATE.md
```

Criar `.context/DESIGN/README.md` com:
- Como usar o template (1 arquivo por tipo: wireframe, component-spec, ux-flow)
- Convenção de nome: `[feature]-[tipo].md` (ex: `importacao-csv-wireframe.md`)
- Regra: todo arquivo aqui deve ser aprovado antes de BUILDER iniciar tasks Frontend
- Link para o `_TEMPLATE.md`

---

## PASSO 10 — SKILLS PREVEC

Instalar as 6 skills do workflow PREVEC em `.context/skills/` — symlinks criados no Passo 1 propagam automaticamente.

```bash
for s in prevec-new-plan prevec-decompose-plan prevec-decompose-task \
         prevec-execute-task prevec-phase-close prevec-review-execution \
         prevec-finalize-execution skill-architect; do
  cp -r "prevec-assets/skills/$s" ".context/skills/$s" 2>/dev/null || true
done
```

| Skill | Fase PREVC | Descrição |
|---|---|---|
| `prevec-decompose-plan` | Planning | **Ponto de entrada** — pergunta o que gerar (PRD, feature doc, tasks) e executa só o pedido |
| `prevec-execute-task` | Execution | Implementa uma task T.A.C.E via BUILDER, sem rodar testes |
| `prevec-phase-close` | Validation + Confirm | Testes da fase + 1 commit por fase; última fase: 7 revisores, gates, fix loop, PR |
| `skill-architect` | Meta | Cria novas skills seguindo DISCOVERY → CRAFT → VALIDATE |

Atalhos para etapas isoladas, fora do fluxo de fases:

| Skill | Uso |
|---|---|
| `prevec-new-plan` | Só a etapa de ideia → PRD |
| `prevec-decompose-task` | Só a etapa de feature doc → tasks |
| `prevec-review-execution` | Revisar uma task solta |
| `prevec-finalize-execution` | Fechar uma task solta |

**Fluxo completo:**
```
/prevec-decompose-plan [ideia|prd]        ← PRD → feature doc → tasks T.A.C.E por fase
  → reviewer-doc valida as tasks
    → Para cada task da fase:
        /prevec-execute-task [feature] TASK-X.Y.Z
      → Ao fim de cada fase:
        /prevec-phase-close [feature] [N]
        → última fase: 7 subagents + gates completos + fix loop + PR
```

> Testes não rodam por task — rodam no fechamento da fase. Fase não fecha com gate vermelho.

---

## PASSO 11 — .gitignore

```bash
if ! grep -q "prevec-assets" .gitignore 2>/dev/null; then
  echo "" >> .gitignore
  echo "# Assets do bootstrap AI-First (podem ser commitados se desejado)" >> .gitignore
  echo "# prevec-assets/" >> .gitignore
fi

if ! grep -q ".context/.session" .gitignore 2>/dev/null; then
  echo "" >> .gitignore
  echo "# PREVEC session files — comunicação temporária entre agents" >> .gitignore
  echo ".context/.session/" >> .gitignore
fi
```

---

## PASSO 12 — VERIFICAÇÃO FINAL

```bash
echo "=== VERIFICAÇÃO FINAL ==="

echo "Raiz:"
test -f AGENTS.md && echo "  OK AGENTS.md" || echo "  FALTA AGENTS.md"

echo "Agents (fonte: .context/agents/):"
for f in ORCHESTRATOR PLANNER BUILDER REVIEWER \
         builder-explore builder-write builder-debug \
         reviewer-doc reviewer-code reviewer-confirm; do
  test -f ".context/agents/$f.md" && echo "  OK $f.md" || echo "  FALTA $f.md"
done

echo "Agent padrão:"
grep -q '"agent"[[:space:]]*:[[:space:]]*"ORCHESTRATOR"' .claude/settings.json 2>/dev/null \
  && echo "  OK agent: ORCHESTRATOR em .claude/settings.json" \
  || echo "  FALTA agent: ORCHESTRATOR em .claude/settings.json"

echo "Symlinks agents:"
test -L ".claude/agents" && echo "  OK .claude/agents → $(readlink .claude/agents)" || echo "  FALTA symlink .claude/agents"

echo "Skills externas (fonte: .context/skills/):"
for f in code-review-confiavel brainstorming; do
  test -d ".context/skills/$f" && echo "  OK $f" || echo "  FALTA $f"
done

echo "Symlinks skills:"
test -L ".claude/skills" && echo "  OK .claude/skills → $(readlink .claude/skills)" || echo "  FALTA symlink .claude/skills"

echo "Workflow:"
test -f ".context/WORKFLOW/PREVC.md" && echo "  OK PREVC.md" || echo "  FALTA PREVC.md"
test -f ".context/WORKFLOW/validation-flow.md" && echo "  OK validation-flow.md" || echo "  FALTA validation-flow.md"

echo "Architecture:"
for f in architecture.md modules.yaml modules.md dependencies.yaml project-state.yaml project-brain.yaml context-version.yaml user-flow.md context-snapshot.md; do
  test -f ".context/ARCHITECTURE/$f" && echo "  OK $f" || echo "  FALTA $f"
done

echo "Skills PREVEC (fonte: .context/skills/):"
for f in prevec-new-plan prevec-decompose-plan prevec-decompose-task prevec-execute-task prevec-review-execution prevec-finalize-execution skill-architect; do
  test -f ".context/skills/$f/SKILL.md" && echo "  OK $f/SKILL.md" || echo "  FALTA $f"
done

echo "Assets:"
test -d "prevec-assets" && echo "  OK prevec-assets/" || echo "  FALTA prevec-assets/"
test -f "prevec-assets/orchestrator-context-model.md" && echo "  OK orchestrator-context-model.md" || echo "  FALTA orchestrator-context-model.md"
test -f "prevec-assets/session-model.md" && echo "  OK session-model.md" || echo "  FALTA session-model.md"
test -f "prevec-assets/planning-session-model.md" && echo "  OK planning-session-model.md" || echo "  FALTA planning-session-model.md"

echo "Design:"
test -f ".context/DESIGN/_TEMPLATE.md" && echo "  OK _TEMPLATE.md" || echo "  FALTA _TEMPLATE.md"

echo "Session:"
test -d ".context/.session" && echo "  OK .context/.session/" || echo "  FALTA .context/.session/"
test -f ".context/.session/_TEMPLATE.md" && echo "  OK _TEMPLATE.md" || echo "  FALTA _TEMPLATE.md"
test -f ".context/.session/_PLANNING_TEMPLATE.md" && echo "  OK _PLANNING_TEMPLATE.md" || echo "  FALTA _PLANNING_TEMPLATE.md"

echo "=== FIM ==="
```

---

## PASSO 13 — RELATÓRIO FINAL

Apresente:

```
## Relatório de Setup AI-First + PREVC V7

### Cenário Detectado
[A/B/C] — [descrição]

### Stack
- Backend: [real]
- Frontend: [real]
- Database: [real]
- Arquitetura: [real]

### Opções Escolhidas
- CHANGELOG: [sim/não]
- MEMORY: [sim/não]
- Skills instaladas em: [pastas]

### Agents Criados
[lista com check]

### Skills Criadas
[lista com check]

### Skills Externas Instaladas
[lista com paths]

### Próximos Passos
1. Revisar AGENTS.md — ajustar dados não detectados automaticamente
2. Revisar agents em .claude/agents/ — refinar Inviolable Rules
3. Para uma nova ideia: `/prevec-new-plan [ideia]`
4. Para uma feature já documentada: `/prevec-decompose-plan [prd]`
5. Fluxo completo: new-plan → decompose-plan → decompose-task → execute-task → review-execution → finalize-execution
```

---

## EXECUTE AGORA

Comece pelo Passo 0.
Leia `prevec-assets/` antes de cada passo que referencia arquivos externos.
Substitua TODAS as variáveis por valores reais.
Se não conseguir detectar um valor, pergunte ao usuário.
