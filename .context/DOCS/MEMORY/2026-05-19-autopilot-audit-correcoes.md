# Memory: Correções de Audit Autopilot — Padrões de Auto-redespacho e Consolidação de Publisher

## Metadados

| Campo | Valor |
|-------|-------|
| **Tipo** | 🧠 Decisão + ⚠️ Armadilha |
| **Data** | 2026-05-19 |
| **Autor** | ORCHESTRATOR + BACKEND |
| **Contexto** | Autopilot Audit (AUDIT-AI-001) — correções P2/P3 |
| **Tags** | [autopilot, audit, queue-jobs, redis-streams, dry, phpstan] |

---

## Situação

Durante o audit AUDIT-AI-001, identificamos três problemas no módulo Autopilot:

1. **TASK-3.2.1**: `AiRunExecutionJob` despachava `AiToolCallJob::dispatch($this->runId)`, mas a classe `AiToolCallJob` não existia — fatal em runs multi-iteração.
2. **TASK-3.4.1**: `AiContextBuilderService:66` usava `?->timezone` desnecessário com `??`, bloqueando `composer analyse`.
3. **TASK-3.4.2**: `InternalAiController` duplicava a lógica de publicação Redis Stream (`publishRunRequestToStream()` + `normalizeStreamFields()`) que já existia em `AutopilotRunStreamPublisher`.

---

## Decisão / Aprendizado

### 1. Auto-redespacho com `static::dispatch()` (TASK-3.2.1)

**Decisão**: Em vez de criar uma nova classe `AiToolCallJob`, usamos `static::dispatch($this->runId)` no próprio `AiRunExecutionJob`.

**Por quê**:
- O job já encapsula todo o ciclo de execução (inicialização → iteração → finalização)
- Não há necessidade de uma classe separada — o comportamento é idêntico
- O loop infinito já é prevenido pelo `max_iterations` rastreado em DB dentro de `generateResponse()`
- Menor complexidade, menos arquivos, menos superfície de bug

**Armadilha**: Comentários desatualizados mencionavam `AiToolCallJob` em 4 lugares, confundindo desenvolvedores. Sempre atualizar comentários quando remover/refatorar classes.

### 2. Consolidação DRY de Publisher (TASK-3.4.2)

**Decisão**: Injetar `AutopilotRunStreamPublisher` em `InternalAiController` e remover os métodos privados duplicados.

**Resultado**: ~92 linhas de código duplicado removidas. A lógica de `XADD` + serialização agora vive em um único lugar (`AutopilotRunStreamPublisher`), usado por:
- `DispatchAutopilotRunJob` (caminho inicial)
- `AiAgentDelegationService` (caminho de delegação)
- `InternalAiController::delegateRun()` (caminho de delegação interna)

### 3. Nullsafe com Coalescing (TASK-3.4.1)

**Padrão**: `$obj?->property ?? 'default'` é desnecessário quando `$obj` é garantido não-nulo. PHPStan (nível 6) flaga como `nullsafe.neverNull`.

**Fix**: Verificar instância com `instanceof` + atribuição condicional em vez de nullsafe operator.

---

## Alternativas Consideradas

| Alternativa | Por que descartada |
|------------|-------------------|
| Criar `AiToolCallJob` real (TASK-3.2.1) | Adicionaria classe sem valor diferencial — comportamento idêntico ao `AiRunExecutionJob` |
| Manter `publishRunRequestToStream()` em `InternalAiController` | Violação DRY — mesma lógica em 3 lugares, risco de divergência |
| Usar `?->timezone` com supressão PHPStan | Má prática — esconde código morto e confunde leitores |

---

## Consequências

### Positivas
- `composer analyse` passa limpo (0 erros)
- Código duplicado eliminado (~92 linhas)
- Referência quebrada removida (nenhum `AiToolCallJob` em `src/`)
- Arquitetura de publicação centralizada e consistente

### Negativas / Trade-offs
- Teste `AiRunExecutionJobTest` estava desatualizado (esperava `'failed'` mas código retorna `'blocked'`). Correção do teste foi necessária.
- **Lição**: Quando a lógica de validação muda (ex: `agent_id` ausente → `'blocked'` em vez de exceção), os testes que mockam comportamento de fallback precisam ser atualizados em conjunto.

---

## Referências

- Task: `.context/DOCS/TASKS/autopilot-audit-remediation-tasks.md`
- Changelog: `.context/DOCS/CHANGELOG/2026-05-19.md`
- Arquivos alterados:
  - `api/src/Domain/Ai/Jobs/AiRunExecutionJob.php`
  - `api/src/Domain/Ai/Http/Controllers/InternalAiController.php`
  - `api/src/Domain/Ai/Actions/AiAutopilotRunActions.php`
  - `api/tests/Feature/AiRunExecutionJobTest.php`
