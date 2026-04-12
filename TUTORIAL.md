# Tutorial: Do Brainstorm à Implementação

> Guia completo do workflow PREVC para desenvolvimento com IA no InteraZap.

---

## Índice

1. [Introdução](#1-introdução)
2. [Conceitos Fundamentais](#2-conceitos-fundamentais)
3. [Fases do Workflow PREVC](#3-fases-do-workflow-prevc)
4. [Templates e Seus Propósitos](#4-templates-e-seus-propósitos)
5. [Exemplo Prático Completo](#5-exemplo-prático-completo)
6. [Quick Reference](#6-quick-reference)

---

## 1. Introdução

Este tutorial explica como usar o framework AI-First do ideação à implementação.

### Pré-requisitos

- Claude Code configurado com o projeto
- Estrutura `.claude/` e `.context/` criada
- AGENTS.md disponível (via CLAUDE.md symlink)

### O que você vai conseguir

Ao final deste tutorial, você saberá:
- Como transformar uma ideia em feature documentada
- Como decompor uma feature em tasks implementáveis
- Como seguir o workflow PREVC do início ao fim
- Como usar os agents especializados

---

## 2. Conceitos Fundamentais

### 2.1 Agents

Agents são especialidades que auxiliam em diferentes aspectos do desenvolvimento:

| Agent | Responsabilidade | Fase PREVC |
|-------|-----------------|------------|
| @PM | Feature docs, escopo, prioridades | Planning, Confirm |
| @ARCHITECT | Decisões de arquitetura | Planning, Review |
| @REVIEWER | Code e doc review | Review |
| @BACKEND | Laravel/PHP | Execution |
| @FRONTEND | Angular/TypeScript | Execution |
| @DEV | Cross-camada | Execution |
| @DBA | PostgreSQL, migrations | Execution |
| @QA | Gates, validação | Validation |
| @DEBUG | Bug investigation | Execution |
| @DOC | CHANGELOG, MEMORY, docs | Confirm |
| @GIT_COMMIT | Commits semânticos | Confirm |
| @DESIGNER | UI/UX | Planning |
| @ORCHESTRATOR | Coordenação | Todas |

### 2.2 Workflow PREVC

```
PLANNING → REVIEW → EXECUTION → VALIDATION → CONFIRM
```

Cada fase tem responsáveis, outputs e registros específicos.

### 2.3 Framework T.A.C.E

| Letra | Significado | Pergunta |
|-------|-------------|----------|
| **T** | Tarefa | O QUE fazer? |
| **A** | Arquivo | ONDE fazer? |
| **C** | Comportamento | COMO funciona? |
| **E** | Evidência | COMO SABER que está pronto? |

### 2.4 Templates

| Template | Propósito | Fase |
|----------|-----------|------|
| Feature Doc | Documentar uma feature completa | Planning |
| PRD | Requisitos detalhados de produto | Planning |
| Tasks | Decomposição T.A.C.E | Review |
| CHANGELOG | Registro de mudanças | Confirm |
| MEMORY | Decisões e aprendizados | Confirm |

---

## 3. Fases do Workflow PREVC

### Fase 1: PLANNING

**Responsável:** @PM ou @ARCHITECT

**Objetivo:** Criar documentação clara ANTES de qualquer código.

**Passos:**

1. **Identificar o que resolver**
   - Discussão com stakeholders
   - Levantamento de requisitos
   - Análise de problema

2. **Criar Feature Doc**
   ```bash
   /new-feature [nome-da-feature]
   ```

3. **O que incluir no Feature Doc:**
   - Nome e descrição claros
   - Bounded Context afetado
   - Escopo (incluído + fora)
   - Dependências identificadas
   - Critérios de aceite verificáveis
   - Complexidade estimada (P/M/G)

**Output:** Feature doc em `.context/DOCS/FEATURES/`

---

### Fase 2: REVIEW

**Responsável:** @REVIEWER ou @ARCHITECT

**Objetivo:** Validar feature doc e gerar tasks.

**Passos:**

1. **Revisar Feature Doc**
   ```bash
   /review-feature [nome-da-feature]
   ```

2. **Verificar completeness:**
   - [ ] Todos os campos preenchidos?
   - [ ] Escopo bem definido?
   - [ ] Dependências identificadas?
   - [ ] Critérios de aceite verificáveis?

3. **Se aprovado → Decompor em Tasks**
   ```bash
   /decompose [nome-da-feature]
   ```

4. **Validar Tasks**
   ```bash
   /validate-tasks [nome-da-feature]
   ```

**Output:** Tasks em `.context/DOCS/TASKS/`

---

### Fase 3: EXECUTION

**Responsável:** @DEV, @BACKEND, @FRONTEND, @DBA

**Objetivo:** Implementar as tasks.

**Passos:**

1. **Ler a task completa**
   ```bash
   cat .context/DOCS/TASKS/[feature]-tasks.md
   ```

2. **Implementar Task**
   ```bash
   /implement-task [feature] [TASK-NNN]
   ```

3. **Seguir rigorosamente T.A.C.E:**
   - **T:** Implementar exatamente o descrito
   - **A:** Modificar APENAS os arquivos listados
   - **C:** Garantir comportamento antes→depois
   - **E:** Preparar evidências (testes, outputs)

4. **Atualizar status** da task para 🔄 Em Progresso

**Output:** Código + Testes

---

### Fase 4: VALIDATION

**Responsável:** @QA ou @REVIEWER

**Objetivo:** Verificar qualidade.

**Passos:**

1. **Executar Validation**
   ```bash
   /validate [feature] [TASK-NNN]
   ```

2. **Verificar Gates:**
   - Tests passando (100%)
   - Lint clean (0 warnings)
   - Build succeeds
   - Type check clean

3. **Verificar Critérios de Aceite:**
   - Seção E (Evidência) da task 100% atendida

4. **Se FALHAR → Voltar para EXECUTION**

**Output:** Gates ✅ + Critérios ✅

---

### Fase 5: CONFIRM

**Responsável:** @PM ou @DOC

**Objetivo:** Registrar e encerrar.

**Passos:**

1. **Confirmar Task**
   ```bash
   /confirm-task [feature] [TASK-NNN]
   ```

2. **Registrar no CHANGELOG:**
   - Abrir `.context/DOCS/CHANGELOG/YYYY-MM-DD.md`
   - Adicionar entrada da mudança

3. **Atualizar MEMORY (se aplicável):**
   - Decisão técnica tomada? → Registrar
   - Bug difícil resolvido? → Registrar como armadilha
   - Padrão novo? → Registrar

4. **Atualizar project-state.yaml:**
   - Incrementar tasks_completed
   - Atualizar métricas

5. **Verificar se feature completa:**
   - Todas tasks ✅? → Feature completa!

**Output:** Task done + CHANGELOG + MEMORY + Métricas

---

## 4. Templates e Seus Propósitos

### 4.1 Feature Doc

**Quando usar:** Nova feature, mudança significativa

**Local:** `.context/DOCS/FEATURES/[nome].md`

**Seções principais:**
- Metadados (ID, nome, contexto, complexidade)
- Resumo e objetivo
- Escopo (incluído + fora)
- Dependências
- Critérios de aceite
- Tasks (após decomposição)

### 4.2 PRD

**Quando usar:** Feature complexa, alto impacto, múltiplas partes interessadas

**Local:** `.context/DOCS/PRDS/[nome].md`

**Seções principais:**
- Visão geral e objetivos
- Problema detalhado
- Solução proposta
- Personas e jornadas
- Requisitos funcionais e não-funcionais
- Wireframes
- Riscos e cronograma

### 4.3 Tasks (T.A.C.E) — Estrutura Hierárquica

**Quando usar:** Após feature doc aprovada

**Local:** `.context/DOCS/TASKS/[feature]-tasks.md`

**Estrutura:** FASE → FEATURE → ETAPA (ex: TASK-3.2.1)

| Nível | Significado | Exemplo |
|-------|-------------|---------|
| X | Fase do PREVC | 3=Backend, 4=Frontend, 5=Integration |
| Y | Feature dentro da fase | 1, 2, 3... |
| Z | Etapa de codificação | 1, 2, 3... |

### 4.4 CHANGELOG

**Quando usar:** Ao confirmar cada task

**Local:** `.context/DOCS/CHANGELOG/YYYY-MM-DD.md`

**Formato:**
```
- [HH:MM] [TIPO] [escopo]: Descrição
  - Detalhes
  - Ref: TASK-NNN
```

### 4.5 MEMORY

**Quando usar:** Decisão técnica, aprendizado, armadilha

**Local:** `.context/DOCS/MEMORY/YYYY-MM-DD-titulo.md`

**Tipos:**
- 🧠 Decisão
- 📚 Aprendizado
- ⚠️ Armadilha
- 💡 Insight

---

## 5. Exemplo Prático Completo

### Situação

"Quero adicionar importação de contatos via CSV"

### Passo 1: PLANNING

```bash
/new-feature importacao-csv-contatos
```

**Feature Doc criado:**
```markdown
# Feature: Importação CSV de Contatos

## Metadados
- Bounded Context: CRM
- Complexidade: M
- Status: 🟡 Em Planning

## Escopo
### Incluído
- [ ] Upload de arquivo CSV
- [ ] Validação de formato
- [ ] Relatório de erros

### Fora de Escopo
- [ ] Importação de empresas
```

### Passo 2: REVIEW

```bash
/review-feature importacao-csv-contatos
# Resultado: ✅ Aprovada

/decompose importacao-csv-contatos
/validate-tasks importacao-csv-contatos
```

### Passo 3: EXECUTION

```bash
/implement-task importacao-csv-contatos TASK-3.1.1
/implement-task importacao-csv-contatos TASK-3.1.2
```

### Passo 4: VALIDATION

```bash
/validate importacao-csv-contatos TASK-3.1.1
/validate importacao-csv-contatos TASK-3.1.2
```

### Passo 5: CONFIRM

```bash
/confirm-task importacao-csv-contatos TASK-3.1.1
/confirm-task importacao-csv-contatos TASK-3.1.2
# ✅ CRM feature completa!
```

---

## 6. Quick Reference

### Estrutura Hierárquica de Tasks

```
TASK-X.Y.Z
├── X = Fase (3=Backend, 4=Frontend, 5=Integration)
├── Y = Feature dentro da fase
└── Z = Etapa de codificação

Exemplo: TASK-3.2.1
├── 3 = Backend (Fase)
├── 2 = Domain (Feature)
└── 1 = Criar Entity (Etapa)
```

### Fases do Workflow

| Fase | Nome | Responsável | Descrição |
|------|------|-------------|-----------|
| 1 | Planning | @PM | Feature doc, escopo |
| 2 | Design | @DESIGNER | Wireframes, componentes |
| 3 | Backend | @BACKEND/@DBA | API, Domain, DB |
| 4 | Frontend | @FRONTEND | Componentes, páginas |
| 5 | Integration | @DEV | E2E, validação |

### Comandos Disponíveis

| Comando | Fase | Uso |
|---------|------|-----|
| `/new-feature [nome]` | Planning | Criar feature doc |
| `/review-feature [nome]` | Review | Validar feature doc |
| `/decompose [nome]` | Review | Gerar tasks hierárquicas |
| `/validate-tasks [nome]` | Review | Validar tasks |
| `/implement-task [f] [T]` | Execution | Implementar (ex: TASK-3.2.1) |
| `/validate [f] [T]` | Validation | Validar gates |
| `/confirm-task [f] [T]` | Confirm | Fechar task + CL + Memory |
| `/feature-status [nome]` | Qualquer | Ver progresso |

### Estrutura de Pastas

```
.context/
├── DOCS/
│   ├── FEATURES/     # Feature docs
│   ├── TASKS/        # Tasks T.A.C.E hierárquicas
│   ├── PRDS/         # Requisitos de produto
│   ├── CHANGELOG/    # Registro diário
│   └── MEMORY/       # Decisões e aprendizados
├── WORKFLOW/         # PREVC e validation
└── ARCHITECTURE/     # Arquitetura e estado
```

### Status de Feature

- 🟡 Em Planning
- 🟡 Em Review
- 🔄 Em Execução
- ✅ Concluída

### Status de Task

| Status | Significado |
|--------|-------------|
| ⏳ Pendente | Aguardando |
| 🔄 Em Progresso | Em desenvolvimento |
| ✅ Concluída | Passou validation |
| ❌ Reprovada | Falhou validation |

### Tipos de Entrada CHANGELOG

- `FEAT` — Nova funcionalidade
- `FIX` — Correção
- `REFACTOR` — Refatoração
- `DOCS` — Documentação
- `TEST` — Testes
- `CHORE` — Configuração

### Tipos de MEMORY

- 🧠 Decisão
- 📚 Aprendizado
- ⚠️ Armadilha
- 💡 Insight

---

## Próximos Passos

1. Revise AGENTS.md para entender os agents
2. Explore `.context/DOCS/` para ver templates
3. Use `/new-feature [nome]` para criar sua primeira feature
4. Siga o workflow PREVC do início ao fim

Boa implementação! 🚀
