# Tasks: Landing Chat Launcher

> Decomposição T.A.C.E das tasks da feature

---

## Feature: Landing Chat Launcher

**ID:** FEAT-041
**Bounded Context:** Chat
**Total Tasks:** 5
**Concluídas:** 5
**Status:** 🟢 Completed

---

## 🔄 FASE 1: PLANNING

### Tasks

#### TASK-041.1 ✅: Formalizar feature e contrato do launcher

**T — Tarefa:**
Documentar a FEAT-041 com objetivo, escopo, dependências, reuso explícito da FEAT-040, ID fixo obrigatório e contrato `data-*` para integração/analytics.

**A — Arquivo:**

- `.context/DOCS/FEATURES/FEAT-041-landing-chat-launcher.md`

**C — Comportamento:**
ANTES:

- Não existe documentação formal da FEAT-041.
- Não há contrato consolidado para ID fixo e `data-*` do launcher na landing.

DEPOIS:

- Feature doc FEAT-041 criada com status inicial de planning.
- Contrato técnico do launcher definido e verificável.
- Reuso da FEAT-040 explícito, sem novo backend.

**E — Evidência:**

- [x] Arquivo da feature FEAT-041 existe e contém metadados obrigatórios.
- [x] ID fixo `interazap-chat-launcher` está documentado como obrigatório.
- [x] Contrato de atributos `data-*` está documentado com campos obrigatórios.
- [x] Reuso da FEAT-040 está descrito sem criação de backend novo.

**Status:** ✅ Concluída

---

## 🔄 FASE 2: REVIEW

### Tasks

#### TASK-041.2 ✅: Revisão de aderência técnica, UX e segurança

**T — Tarefa:**
Revisar o doc da FEAT-041 para garantir consistência com FEAT-040 e validar critérios de aceite funcionais, técnicos, UX e segurança.

**A — Arquivo:**

- `.context/DOCS/FEATURES/FEAT-041-landing-chat-launcher.md`
- `.context/DOCS/FEATURES/FEAT-040-webchat-widget.md`

**C — Comportamento:**
ANTES:

- Critérios podem estar incompletos ou sem rastreabilidade para validação.
- Risco de divergência do contrato de WebChat já existente.

DEPOIS:

- Critérios de aceite categorizados e verificáveis.
- Requisitos de acessibilidade e segurança explícitos.
- Dependência e reuso de FEAT-040 confirmados sem ruptura de contrato.

**E — Evidência:**

- [x] Checklist de critérios com IDs CA-001+ existe e é auditável.
- [x] Não há requisito que force endpoint/migração nova no backend.
- [x] Itens de UX (responsividade/acessibilidade) e segurança (sem segredos expostos) estão explícitos.

**Status:** ✅ Concluída

---

## 🔄 FASE 3: EXECUTION (FRONTEND)

### Tasks

#### TASK-041.3 ✅: Implementar launcher na landing com contrato estável

**T — Tarefa:**
Implementar o launcher de chat na landing com ID fixo obrigatório `interazap-chat-launcher`, atributos `data-*` definidos e acionamento do fluxo existente da FEAT-040.

**A — Arquivo:**

- `landing/index.html`
- (se necessário) `landing/assets/*` para estilo/comportamento visual do launcher
- (se necessário) `app/src/app/pages/webchat/**` apenas para integração já existente, sem alteração de contrato backend

**C — Comportamento:**
ANTES:

- Landing sem launcher padronizado para chat interno.
- Integração/analytics sem contrato formal de atributos do elemento.

DEPOIS:

- Launcher presente na landing com ID fixo único.
- `data-*` aplicados conforme contrato FEAT-041.
- Clique/tap abre fluxo FEAT-040 sem criação de backend novo.

**E — Evidência:**

- [x] DOM da landing contém `#interazap-chat-launcher` único.
- [x] Atributos obrigatórios (`data-iz-chat-launcher`, `data-iz-tenant-id`, `data-iz-entrypoint`) presentes.
- [x] Fluxo de abertura funciona usando endpoints já existentes da FEAT-040.
- [x] Nenhum arquivo backend (`api/`, `gateway/`) foi criado para esta feature.

**Status:** ✅ Concluída

---

## 🔄 FASE 4: VALIDATION (QA)

### Tasks

#### TASK-041.4 ✅: Validar critérios funcionais, técnicos, UX e segurança

**T — Tarefa:**
Executar validação QA do launcher na landing cobrindo funcionamento, contrato técnico, responsividade, acessibilidade e telemetria.

**A — Arquivo:**

- `.context/DOCS/FEATURES/FEAT-041-landing-chat-launcher.md` (fonte de critérios)
- Evidências em relatório de validação do pipeline/gate aplicável

**C — Comportamento:**
ANTES:

- Sem evidência formal de que o launcher cumpre os critérios aprovados.

DEPOIS:

- Critérios CA-001 a CA-008 validados com evidência objetiva.
- Casos de erro e sucesso cobertos (incluindo evento de erro de abertura).

**E — Evidência:**

- [x] Evidência de render e clique com telemetria mínima (`impression` e `click`).
- [x] Evidência de acessibilidade por teclado e rótulo acessível.
- [x] Evidência de comportamento responsivo em viewport mobile e desktop.
- [x] Evidência de que nenhum segredo/token foi exposto no DOM/URL/log.

**Status:** ✅ Concluída

---

## 🔄 FASE 5: CONFIRM (DOC)

### Tasks

#### TASK-041.5 ✅: Registrar fechamento documental obrigatório da feature

**T — Tarefa:**
Concluir a feature no fluxo PREVC com registros obrigatórios de CHANGELOG, MEMORY e atualização de estado do projeto.

**A — Arquivo:**

- `.context/DOCS/CHANGELOG/2026-04-20.md`
- `.context/DOCS/MEMORY/2026-04-20-feat-041-landing-chat-launcher.md`
- `.context/ARCHITECTURE/project-state.yaml`

**C — Comportamento:**
ANTES:

- Alterações da FEAT-041 podem ficar sem trilha histórica e sem atualização do estado arquitetural.

DEPOIS:

- CHANGELOG registra mudanças factuais da FEAT-041.
- MEMORY registra decisão de ID fixo e contrato `data-*` para evitar regressões.
- `project-state.yaml` reflete status da FEAT-041 no contexto do projeto.

**E — Evidência:**

- [x] Existe entrada de CHANGELOG na data da conclusão.
- [x] Existe memória da decisão com contexto, alternativa e consequência.
- [x] `project-state.yaml` atualizado com referência à FEAT-041.

**Status:** ✅ Concluída

---

## Revisão de Tasks

| Task       | Status | Validada por | Data       |
| ---------- | ------ | ------------ | ---------- |
| TASK-041.1 | ✅     | ORCHESTRATOR | 2025-07-14 |
| TASK-041.2 | ✅     | ORCHESTRATOR | 2025-07-14 |
| TASK-041.3 | ✅     | ORCHESTRATOR | 2025-07-14 |
| TASK-041.4 | ✅     | ORCHESTRATOR | 2026-04-20 |
| TASK-041.5 | ✅     | ORCHESTRATOR | 2026-04-20 |

---

## Progresso

- [5/5] Tasks concluídas
- [x] Feature completa
