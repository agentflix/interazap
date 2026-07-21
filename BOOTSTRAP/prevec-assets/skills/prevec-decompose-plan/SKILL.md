---
name: prevec-decompose-plan
description: Ponto de entrada unificado do planejamento PREVEC. Pergunta o que gerar — PRD, Feature doc, Tasks ou qualquer combinação — e executa apenas o solicitado. Absorve prevec-new-plan e prevec-decompose-task. Triggers: "decompor plano", "criar feature", "gerar tasks", "novo plano", "prevec-decompose-plan", "quero planejar". Do NOT use para implementar código (use prevec-execute-task).
metadata:
  author: prevec
  version: '2.0.0'
---

# prevec-decompose-plan

Ponto de entrada unificado de planejamento. Pergunta o que você precisa e executa apenas isso.

## Input

```
/prevec-decompose-plan [contexto opcional]
/prevec-decompose-plan [nome] --fast
```

`--fast`: apenas para `Complexidade: P` — gera feature doc + tasks em uma invocação.

## Passo 0 — Perguntar o que gerar

Antes de qualquer ação, perguntar ao usuário:

```
O que deseja gerar?

[ ] PRD — amadurecer ideia via brainstorming
[ ] Feature doc — criar feature doc a partir do PRD
[ ] Tasks T.A.C.E — decompor feature em tasks executáveis

Selecione um ou mais. Pode selecionar todos para o fluxo completo.
```

Registrar as seleções. Executar apenas as fases selecionadas, na ordem: PRD → Feature → Tasks.

Regras de dependência:
- **Tasks** selecionado sem **Feature**: feature doc deve existir em `.context/DOCS/FEATURES/`
- **Feature** selecionado sem **PRD**: PRD deve existir em `.context/DOCS/PRDS/` ou será criado no passo de Feature
- **PRD** + **Feature** + **Tasks**: fluxo completo (equivalente ao fluxo prevec-new-plan → decompose-plan → decompose-task anterior)

---

## FASE A — Gerar PRD (se selecionado)

### A.1 — Capturar ideia

Argumento passado no input: usar como ponto de partida.
Sem argumento: perguntar "Qual é a ideia que quer desenvolver?"

### A.2 — Criar planning session

```bash
mkdir -p .context/.session
```

Verificar se já existe `.context/.session/planning-[feature].md`:
- Existe: ler e continuar (Architecture Snapshot já capturado)
- Não existe: criar seguindo `prevec-assets/planning-session-model.md`

Preencher **Architecture Snapshot**: copiar `.context/ARCHITECTURE/context-snapshot.md` inteiro.
Este é o ÚNICO momento em que se lê arquivos de arquitetura nesta cadeia.

Se `context-snapshot.md` ausente: ler `project-brain.yaml` diretamente e avisar para regenerar.

Atualizar `Status: BRAINSTORMING`.

### A.3 — Executar brainstorming

Ler skill `brainstorming` instalada (`.context/skills/brainstorming/SKILL.md` ou equivalente).

Usar **Architecture Snapshot do session** — não ler `project-brain.yaml` diretamente.

Conduzir:
- Perguntas uma a uma para refinar escopo
- Propor 2-3 abordagens com trade-offs
- Apresentar design por seções e obter aprovação

### A.4 — Criar PRD

Determinar número sequencial:
```bash
ls .context/DOCS/PRDS/*.md 2>/dev/null | grep -E '[0-9]{4}-PRD' | sort | tail -1
```
Incrementar. Se não existir: começar em `0001`.

Salvar em `.context/DOCS/PRDS/NNNN-PRD-<topic-kebab>.md` usando template em `.context/DOCS/PRDS/_TEMPLATE.md`.

Self-review antes de apresentar:
- Sem "TBD" ou seções vazias
- Critérios de aceite verificáveis
- Escopo delimitado (incluído + fora)
- Sem contradições internas

Atualizar seção **PRD** do session: path, objetivo, critérios, complexidade, fora de escopo. `Status: PRD`.

### A.5 — Aprovação do usuário

Apresentar path do PRD e aguardar confirmação antes de continuar.

---

## FASE B — Gerar Feature doc (se selecionado)

### B.1 — Carregar contexto

Se planning session existe (`.context/.session/planning-[feature].md`): ler — Architecture Snapshot + PRD já estão lá.
Se session ausente: ler PRD em `.context/DOCS/PRDS/` + `context-snapshot.md` diretamente.

### B.2 — Consultar MEMORY (se existir)

```bash
ls .context/DOCS/MEMORY/ 2>/dev/null
```

Ler apenas entradas relacionadas ao tema — não ler tudo.

### B.3 — Criar feature doc

Salvar em `.context/DOCS/FEATURES/[nome-kebab].md`:

```markdown
# Feature: [Nome]

## Metadados
- ID: FEAT-[NNN]
- PRD: .context/DOCS/PRDS/[NNNN]-PRD-[nome].md
- Bounded Context: [módulo do Architecture Snapshot]
- Complexidade: [P / M / G]
- Status: 🟡 Em Planning

## Resumo
[2-3 frases do objetivo]

## Escopo
### Incluído
- [ ] [item]

### Fora de Escopo
- [item]

## Dependências
- Features: [lista ou "nenhuma"]
- Módulos: [lista do Architecture Snapshot]
- Externas: [lista]

## Critérios de Aceite
- [ ] [critério verificável]

## Fases Estimadas
- [x] **Fase 1 — Planning** ✅
- [ ] **Fase 2 — Design** (UI/UX, fluxo de tela, wireframe)
- [ ] **Fase 3 — Backend** (domain, service, API, eventos)
- [ ] **Fase 4 — Frontend** (componentes, páginas, estado)
- [ ] **Fase 5 — Integration** (E2E, contrato cross-camada)

## Tasks
> Gerado por /prevec-decompose-plan (fase Tasks)
```

### B.4 — Mapear fases

| Fase | Incluir quando |
|---|---|
| Fase 2 — Design | PRD menciona UI, tela nova, fluxo visual |
| Fase 3 — Backend | API, regra de negócio, domain, eventos |
| Fase 4 — Frontend | Componente, página, estado, formulário |
| Fase 5 — Integration | E2E, contrato cross-camada |

### B.5 — Validar

- Escopo claro (incluído + fora)
- Critérios verificáveis — sem "funciona corretamente"
- Bounded context identificado
- Ao menos uma fase além de Planning marcada

Atualizar planning session: bounded context, dependências, fases. `Status: FEATURE_DOC`.

---

## FASE C — Gerar Tasks T.A.C.E (se selecionado)

### C.1 — Carregar contexto

Planning session existe: ler `.context/.session/planning-[feature].md` — Architecture Snapshot + Feature Draft já estão lá.
Session ausente: ler `.context/DOCS/FEATURES/[nome].md` + `.context/ARCHITECTURE/context-snapshot.md`.

### C.2 — Pesquisa de contexto para enriquecer tasks

Esta etapa é feita UMA vez pelo PLANNER — não pelo BUILDER durante execução.

Para cada arquivo que será criado ou modificado:

**C.2a — Buscar arquivo de referência:**
```bash
find src/ -name "*.php" -path "*Repository*" | head -3  # adaptar para a stack real
```
Identificar arquivo existente com mesmo padrão. Será citado na task como **Referência**.

**C.2b — Extrair imports autorizados:**
Ler seção relevante de `.context/ARCHITECTURE/dependencies.yaml` para o módulo afetado.
Registrar: o que pode importar + o que é proibido. Será embutido na task.

**C.2c — Extrair comandos de gate:**
Ler `.context/WORKFLOW/validation-flow.md` para obter os comandos exatos de lint, test e build.
Usar os comandos reais — nunca genéricos como "rodar os testes".

### C.3 — Criar arquivo de tasks com Rich Format

Salvar em `.context/DOCS/TASKS/[nome]-tasks.md`.

**Estrutura:**
```
Fase (X)        → Planning / Design / Backend / Frontend / Integration
  Grupo (X.Y)   → agrupamento lógico dentro da fase
    Task X.Y.Z  → unidade ~4h, auto-suficiente para execução
```

Se task exceder ~4h: subdividir em TASK-X.Y.1, TASK-X.Y.2.

**Rich Format obrigatório para cada task:**

```markdown
- [ ] **TASK-X.Y.Z** ⏳
  **T — Tarefa:** [verbo imperativo + objeto específico]
  **A — Arquivo:** `path/exato/arquivo.ext` (criar/modificar)
  **Referência:** `path/arquivo-existente.ext` — [padrão que segue]
  **Imports autorizados:** [lista] — proibido: [lista]
  **C — Comportamento:**
  ANTES: [estado atual concreto]
  DEPOIS: [estado esperado concreto]
  **E — Evidência:**
  - [ ] `[comando exato]` → [resultado esperado]
  **Status:** ⏳ Pendente
```

### C.4 — Regras Rich Format

- **T:** verbo imperativo + objeto específico. Sem "implementar coisas"
- **A:** path exato. Sem "arquivos do módulo X"
- **Referência:** arquivo REAL existente no projeto. Sem "ver documentação"
- **Imports autorizados:** lista real extraída de `dependencies.yaml`. Obrigatório para Backend e Frontend
- **Design:** obrigatório para tasks Frontend — link exato para seção do arquivo em `.context/DESIGN/`
- **C:** ANTES e DEPOIS concretos com estado atual real
- **E:** comando exato que pode ser rodado agora. Sem "funciona corretamente"

### C.5 — Revisar tasks criadas

- Toda task tem T, A, Referência, C e E com comandos exatos
- O BUILDER consegue implementar lendo APENAS os arquivos listados em A + a Referência
- Tasks com dependência estão em ordem correta
- Nenhuma task excede ~4h

### C.6 — Arquivar planning session

```bash
mkdir -p .context/.session/.archive
mv .context/.session/planning-[feature].md .context/.session/.archive/planning-[feature].md
```

---

## Modo --fast (só para Complexidade: P)

Se `--fast` foi passado: executar Fases B e C inline sem handoffs intermediários.
Pular Fase A (PRD já deve existir).
Ao final: arquivar planning session.

---

## Handoff final

Apresentar o que foi gerado e o próximo passo:

```
[✅ PRD criado: .context/DOCS/PRDS/[NNNN]-PRD-[topic].md]       (se Fase A executada)
[✅ Feature doc: .context/DOCS/FEATURES/[nome].md]              (se Fase B executada)
[✅ Tasks: .context/DOCS/TASKS/[nome]-tasks.md — [N] tasks]     (se Fase C executada)
➡️  Próximo: /prevec-execute-task [nome] TASK-[X.Y.Z]
```

## Error Handling

- PRD não encontrado quando Feature selecionada sem PRD: listar `.context/DOCS/PRDS/` e pedir confirmação
- Feature não encontrada quando Tasks selecionada sem Feature: alertar e oferecer criar feature doc
- Bounded context ambíguo: perguntar ao usuário
- Feature similar existente: alertar e pedir confirmação
- Arquivo de referência não encontrado: buscar padrão mais próximo ou registrar "sem referência — criar padrão novo"
- `--fast` em feature M ou G: recusar, informar que `--fast` só é válido para complexidade P
- Planning session ausente: criar do zero lendo PRD + `context-snapshot.md`
