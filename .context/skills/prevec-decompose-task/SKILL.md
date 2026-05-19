---
name: prevec-decompose-task
description: Decompõe feature doc em tasks T.A.C.E ricas e auto-suficientes — cada task contém tudo que o BUILDER precisa para implementar sem pesquisar contexto adicional. Triggers: "decompor feature", "criar tasks", "prevec-decompose-task". Do NOT use sem feature doc aprovada.
metadata:
  author: prevec
  version: '1.0.0'
---

# prevec-decompose-task

Gera tasks executáveis sem pesquisa. O planejamento faz o trabalho pesado de busca de contexto UMA vez — o BUILDER só implementa.

**Princípio central:** uma task bem escrita não exige que o BUILDER abra nenhum arquivo além dos listados em **A**.

## Input

```
/prevec-decompose-task [nome-da-feature]
```

## Pré-condições

- Feature doc existe em `.context/DOCS/FEATURES/[nome].md`
- `.context/DOCS/TASKS/` existe

## Processo

### 1. Carregar contexto do planning session

```bash
ls .context/.session/planning-[feature].md 2>/dev/null
```

**Session existe:** ler `.context/.session/planning-[feature].md` — Architecture Snapshot + Feature Draft já estão lá.
**Session ausente:** ler `.context/DOCS/FEATURES/[nome].md` + `.context/ARCHITECTURE/context-snapshot.md`.

### 2. Pesquisa de contexto para enriquecer tasks

Esta etapa é feita UMA vez pelo PLANNER — não pelo BUILDER durante execução.

Para cada arquivo que será criado ou modificado nas tasks:

**2a. Buscar arquivo de referência:**
```bash
find src/ -name "*.php" -path "*Repository*" | head -3  # exemplo
```
Identificar arquivo existente que usa o mesmo padrão. Será citado na task como **Referência**.

**2b. Extrair imports autorizados:**
Ler seção relevante de `.context/ARCHITECTURE/dependencies.yaml` para o módulo afetado.
Registrar: o que pode importar + o que é proibido. Será embutido na task.

**2c. Extrair comandos de gate:**
Ler `.context/WORKFLOW/validation-flow.md` para obter os comandos exatos de lint, test e build da stack.
Usar os comandos reais — nunca genéricos como "rodar os testes".

### 3. Confirmar fases

Ler **Fases estimadas** do planning session ou feature doc. Incluir apenas as marcadas.

### 4. Criar arquivo de tasks com Rich Format

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
# Tasks: [Nome da Feature]

> Feature: .context/DOCS/FEATURES/[nome].md
> Total: [N] tasks | Pendentes: [N] | Em progresso: 0 | Concluídas: 0

---

## ✅ FASE 1: PLANNING
> Feature doc aprovada e fases mapeadas.

---

## ⏳ FASE 2: DESIGN
> Artefatos DEVEM ser salvos em `.context/DESIGN/[feature]-[tipo].md`.

### 2.1 — [Agrupamento]

- [ ] **TASK-2.1.1** ⏳
  **T — Tarefa:** [verbo imperativo + objeto específico]
  **A — Arquivo:** `.context/DESIGN/[feature]-wireframe.md` (criar)
  **Referência:** não se aplica — artefato de design novo
  **C — Comportamento:**
  ANTES: Sem spec visual para [feature]
  DEPOIS: Wireframe aprovado com fluxo de [tela X] e [tela Y] documentado
  **E — Evidência:**
  - [ ] Arquivo `.context/DESIGN/[feature]-wireframe.md` existe e aprovado pelo usuário
  **Status:** ⏳ Pendente

---

## ⏳ FASE 3: BACKEND

### 3.1 — [Agrupamento: ex. Entidades de Domínio]

- [ ] **TASK-3.1.1** ⏳
  **T — Tarefa:** [verbo imperativo + objeto específico — ex: "Criar OrderRepository implementando IOrderRepository"]
  **A — Arquivo:** `src/Domain/Order/Repository/OrderRepository.php` (criar)
  **Referência:** `src/Domain/Customer/Repository/CustomerRepository.php` — mesmo padrão
  **Imports autorizados:** `App\Domain\Order\`, `App\Shared\` — proibido: `App\Infrastructure\`, qualquer `Framework\`
  **C — Comportamento:**
  ANTES: IOrderRepository não tem implementação concreta — qualquer chamada lança exceção
  DEPOIS: OrderRepository implementa find(), save(), delete() retornando Order domain objects
  **E — Evidência:**
  - [ ] `php artisan test --filter OrderRepositoryTest` → verde, 0 falhas
  - [ ] `php artisan insights src/Domain/Order/Repository/` → 0 violações de arquitetura
  **Status:** ⏳ Pendente

- [ ] **TASK-3.1.2** ⏳
  [mesmo Rich Format — T, A, Referência, Imports autorizados, C, E com comandos exatos]

### 3.2 — [Agrupamento: ex. API Endpoints]

- [ ] **TASK-3.2.1** ⏳
  [mesmo Rich Format]

---

## ⏳ FASE 4: FRONTEND
> PRÉ-REQUISITO: `.context/DESIGN/[feature]-*.md` aprovado antes de iniciar.

### 4.1 — [Agrupamento: ex. Componentes]

- [ ] **TASK-4.1.1** ⏳
  **T — Tarefa:** [ex: "Criar componente OrderForm seguindo spec de .context/DESIGN/"]
  **A — Arquivo:** `src/components/OrderForm/OrderForm.tsx` (criar)
  **Referência:** `src/components/CustomerForm/CustomerForm.tsx` — mesmo padrão de form
  **Design:** `.context/DESIGN/[feature]-wireframe.md` — seção "Formulário de Pedido"
  **Imports autorizados:** `@/components/ui/`, `@/hooks/`, `@/types/` — proibido: imports diretos de `@/store/`
  **C — Comportamento:**
  ANTES: Página /orders/new exibe formulário vazio sem validação
  DEPOIS: OrderForm renderiza campos [X, Y, Z], valida em tempo real, submete para POST /api/orders
  **E — Evidência:**
  - [ ] `npm test -- OrderForm` → verde
  - [ ] `npm run build` → sem erro de tipos
  - [ ] Formulário visível em /orders/new com campos [X, Y, Z]
  **Status:** ⏳ Pendente

---

## ⏳ FASE 5: INTEGRATION

### 5.1 — [Agrupamento: ex. Testes E2E]

- [ ] **TASK-5.1.1** ⏳
  **T — Tarefa:** [ex: "Escrever teste E2E do fluxo completo de criação de pedido"]
  **A — Arquivo:** `tests/e2e/order-creation.spec.ts` (criar)
  **Referência:** `tests/e2e/customer-creation.spec.ts` — mesmo padrão Playwright
  **C — Comportamento:**
  ANTES: Fluxo de criação de pedido sem cobertura E2E
  DEPOIS: Teste cobre: login → /orders/new → preencher form → submit → confirmação
  **E — Evidência:**
  - [ ] `npx playwright test order-creation` → verde
  **Status:** ⏳ Pendente
```

### 5. Regras Rich Format obrigatórias

- **T:** verbo imperativo + objeto específico. Sem "implementar coisas"
- **A:** path exato. Sem "arquivos do módulo X"
- **Referência:** arquivo REAL existente no projeto com mesmo padrão. Sem "ver documentação"
- **Imports autorizados:** lista real extraída de `dependencies.yaml`. Obrigatório para Backend e Frontend
- **Design:** obrigatório para tasks Frontend — link exato para seção do arquivo em `.context/DESIGN/`
- **C:** ANTES e DEPOIS concretos com estado atual real
- **E:** comando exato que pode ser rodado agora. Sem "funciona corretamente"

### 6. Revisar tasks criadas

- Toda task tem T, A, Referência, C e E com comandos exatos
- O BUILDER consegue implementar lendo APENAS os arquivos listados em A + a Referência
- Nenhuma task exige pesquisa de contexto adicional durante execução
- Tasks com dependência estão em ordem correta
- Nenhuma task excede ~4h

### 7. Arquivar planning session

```bash
mkdir -p .context/.session/.archive
mv .context/.session/planning-[feature].md .context/.session/.archive/planning-[feature].md
```

### 8. Handoff

```
Tasks criadas: .context/DOCS/TASKS/[nome]-tasks.md
[N] tasks auto-suficientes em [X] fases
Planning session arquivado.
Próximo: /prevec-execute-task [nome] TASK-[X.Y.Z]
```

## Output

```
✅ Tasks criadas: .context/DOCS/TASKS/[nome]-tasks.md
📋 [N] tasks | [X] fases | Rich Format com Referência + Imports + Comandos
➡️  Próximo: /prevec-execute-task [nome] TASK-3.1.1
```

## Error Handling

- Arquivo de referência não encontrado: buscar padrão mais próximo ou registrar "sem referência — criar padrão novo"
- Imports autorizados ausentes em dependencies.yaml: usar rules do context-snapshot e registrar dívida
- Comando de gate desconhecido: ler validation-flow.md e usar comando real
- Planning session ausente: ler feature doc + context-snapshot diretamente
- Task muito grande: subdividir em TASK-X.Y.1, TASK-X.Y.2
