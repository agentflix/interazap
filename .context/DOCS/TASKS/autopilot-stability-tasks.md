# Tasks — autopilot-stability

**Feature:** `.context/DOCS/FEATURES/autopilot-stability.md`
**PRD:** `.context/DOCS/PRDS/0001-PRD-autopilot-stability.md`
**Status:** [x] Em progresso | [ ] Concluída
**Total:** 19 tasks | Pendentes: 2 | Em progresso: 17 | Concluídas: 0

---

## Fase 3 — Backend (api/)

### Grupo 3.1 — Runtime Stability

#### TASK-3.1.1 — Corrigir TTL do Lock Redis em DispatchAutopilotRunJob

**T — Tarefa:** Alterar TTL de `acquireMessageDispatchLock()` de 60s para igualar `$this->timeout` (300s), eliminando race condition de webhooks duplicados entre 60s e 300s.

**A — Arquivo:**
- `api/src/Domain/Ai/Jobs/DispatchAutopilotRunJob.php` (modificar)
- `api/tests/Feature/DispatchAutopilotRunJobTest.php` (adicionar teste de TTL)

**Referência:** `api/src/Domain/Ai/Jobs/DispatchAutopilotRunJob.php:517-532` — método `acquireMessageDispatchLock` existente

**Imports autorizados:** `Illuminate\Support\Facades\Redis`, `Illuminate\Support\Facades\Log` — proibido: importar do gateway

**C — Comportamento:**
- ANTES: linhas 528-529 usam `'EX', '60'` e `['EX' => 60, 'NX']` — lock expira em 60s, mas job pode rodar até 300s. Webhook duplicado após 60s redispacha.
- DEPOIS: TTL = 300s (mesmo valor de `$this->timeout`). Constante extraída como `private const LOCK_TTL_SECONDS = 300;` no topo da classe. Lock cobre janela inteira de execução.

**E — Evidência:**
- [ ] `grep -n "'EX', '300'" api/src/Domain/Ai/Jobs/DispatchAutopilotRunJob.php` retorna ao menos uma ocorrência
- [ ] `cd api && php artisan test --filter=DispatchAutopilotRunJobTest` passa
- [ ] Novo teste `test_lock_ttl_matches_job_timeout` verifica `LOCK_TTL_SECONDS === self::TIMEOUT`

**Status:** ✅ Concluída
**Dependências:** Nenhuma

---

#### TASK-3.1.2 — Implementar failed() Handler em DispatchAutopilotRunJob

**T — Tarefa:** Adicionar método `failed(\Throwable $e)` que marca run como `failed`, registra log estruturado e dispara evento `AiRunFailed` quando retries esgotam.

**A — Arquivo:**
- `api/src/Domain/Ai/Jobs/DispatchAutopilotRunJob.php` (modificar)
- `api/tests/Feature/DispatchAutopilotRunJobFailedHandlerTest.php` (criar)

**Referência:** `api/src/Domain/Ai/Jobs/AiPromptGuardianJob.php:130` — padrão `public function failed(\Throwable $exception): void`

**Imports autorizados:** `Throwable`, `Domain\Ai\Events\AiRunFailed`, `Domain\Ai\Models\AiAutopilotRun`, `Illuminate\Support\Facades\Log` — proibido: lógica de negócio do controller

**C — Comportamento:**
- ANTES: job sem método `failed()`. Após 3 retries, exception silenciada; run fica em status `queued` ou `running` zumbi.
- DEPOIS: `failed(\Throwable $e)` recupera o run pelo `runId` armazenado, marca `status=failed`, persiste `completed_at`, registra log com `run_id`, `tenant_id`, `correlation_id`, `exception_class`, `exception_message`, e dispara `AiRunFailed` event.

**E — Evidência:**
- [ ] `grep -n "public function failed" api/src/Domain/Ai/Jobs/DispatchAutopilotRunJob.php` retorna 1 ocorrência
- [ ] `cd api && php artisan test --filter=DispatchAutopilotRunJobFailedHandlerTest` passa
- [ ] Teste verifica: run.status=failed + log emitido + evento despachado

**Status:** ✅ Concluída
**Dependências:** TASK-3.1.1

---

#### TASK-3.1.3 — Criar AutopilotZombieRunCleanupJob (Watchdog)

**T — Tarefa:** Criar job que marca como `failed` runs com `status=running` e `started_at < now() - config('ai.stale_run_threshold_minutes', 6)`. Registrar no scheduler com cron `everyMinute()`.

**A — Arquivo:**
- `api/src/Domain/Ai/Jobs/AutopilotZombieRunCleanupJob.php` (criar)
- `api/routes/console.php` (modificar — adicionar Schedule::job)
- `api/tests/Feature/AutopilotZombieRunCleanupJobTest.php` (criar)

**Referência:**
- Job pattern: `api/src/Domain/Ai/Jobs/AiPromptGuardianJob.php`
- Scheduler: `api/routes/console.php:5` — `Schedule::command('chat:close-inactive-tickets')->everyFiveMinutes()`

**Imports autorizados:** `Illuminate\Bus\Queueable`, `Illuminate\Contracts\Queue\ShouldQueue`, `Illuminate\Foundation\Bus\Dispatchable`, `Illuminate\Queue\InteractsWithQueue`, `Illuminate\Queue\SerializesModels`, `Domain\Ai\Models\AiAutopilotRun`, `Illuminate\Support\Facades\Log` — proibido: chamadas ao gateway

**C — Comportamento:**
- ANTES: nenhum job consome `ai.stale_run_threshold_minutes`. Runs travadas em `running` indefinidamente.
- DEPOIS: job query `AiAutopilotRun::where('status', 'running')->where('started_at', '<', now()->subMinutes(config('ai.stale_run_threshold_minutes', 6)))->each(...)` marca cada uma como `failed` com motivo "watchdog_zombie_cleanup", registra log e dispara `AiRunFailed`. Schedule registrado em `console.php` via `Schedule::job(AutopilotZombieRunCleanupJob::class)->everyMinute()`.

**E — Evidência:**
- [ ] `cd api && php artisan test --filter=AutopilotZombieRunCleanupJobTest` passa
- [ ] `grep -n "AutopilotZombieRunCleanupJob" api/routes/console.php` retorna 1 ocorrência
- [ ] `cd api && php artisan schedule:list` lista o job

**Status:** ✅ Concluída
**Dependências:** TASK-3.1.2

---

### Grupo 3.2 — Cleanup (Código Morto)

#### TASK-3.2.1 — Remover AiAutopilotTriggerLog (Model + Referências)

**T — Tarefa:** Deletar model `AiAutopilotTriggerLog` e o bloco em `DispatchAutopilotRunJob.php:496-514` que faz insert em tabela dropada. Confirmar grep zero.

**A — Arquivo:**
- `api/src/Domain/Ai/Models/AiAutopilotTriggerLog.php` (deletar)
- `api/src/Domain/Ai/Jobs/DispatchAutopilotRunJob.php` (modificar — remover bloco)
- `api/database/factories/AiAutopilotTriggerLogFactory.php` (deletar se existir)

**Referência:** Migration `api/database/migrations/2026_03_04_150000_drop_ai_autopilot_trigger_logs_table.php` — comprova drop da tabela

**Imports autorizados:** N/A (apenas remoção) — proibido: deixar `class_exists` guard como work-around

**C — Comportamento:**
- ANTES: model existe; `DispatchAutopilotRunJob.php:496-514` faz `class_exists(AiAutopilotTriggerLog::class) && AiAutopilotTriggerLog::create([...])`. Tabela dropada → insert silenciosamente nunca executa.
- DEPOIS: model deletado; código removido. Zero referências no codebase. Se futuramente precisar audit log, criar nova tabela com nome diferente.

**E — Evidência:**
- [ ] `grep -rn "AiAutopilotTriggerLog" api/` retorna 0 resultados
- [ ] `cd api && php artisan test` passa sem erros de class not found

**Status:** ✅ Concluída
**Dependências:** Nenhuma

---

#### TASK-3.2.2 — Remover Diretório migrations_backup_20260216

**T — Tarefa:** Deletar `api/database/migrations_backup_20260216/` após confirmar que schema atual está estável e nenhuma migration ativa referencia o diretório.

**A — Arquivo:**
- `api/database/migrations_backup_20260216/` (deletar diretório inteiro)

**Referência:** Diretório de backup criado antes do refactor de 2026-02-16 — não há referência ativa

**Imports autorizados:** N/A — proibido: deletar sem verificar referências

**C — Comportamento:**
- ANTES: diretório `migrations_backup_20260216/` existe com migrations órfãs nunca executadas pelo Laravel (não está em `database/migrations/`).
- DEPOIS: diretório removido. `grep -rn "migrations_backup_20260216" api/` retorna 0.

**E — Evidência:**
- [ ] `ls api/database/migrations_backup_20260216` retorna "No such file or directory"
- [ ] `grep -rn "migrations_backup_20260216" api/` retorna 0
- [ ] `cd api && php artisan migrate:status` não acusa diff

**Status:** ✅ Concluída
**Dependências:** Nenhuma

---

### Grupo 3.3 — Approval Lifecycle

#### TASK-3.3.1 — Adicionar Coluna expires_at em ai_autopilot_approvals

**T — Tarefa:** Criar migration que adiciona coluna `expires_at` (nullable, indexed) com backfill `LEAST(created_at + INTERVAL '24 hours', NOW())`. Atualizar model e factory.

**A — Arquivo:**
- `api/database/migrations/2026_05_21_000001_add_expires_at_to_ai_autopilot_approvals.php` (criar)
- `api/src/Domain/Ai/Models/AiAutopilotApproval.php` (modificar — fillable + cast)
- `api/database/factories/AiAutopilotApprovalFactory.php` (modificar — default)

**Referência:** `api/database/migrations/2026_05_10_000002_fix_ai_autopilot_approvals_tenant_id_not_null.php` — padrão de migration que faz UPDATE

**Imports autorizados:** `Illuminate\Database\Migrations\Migration`, `Illuminate\Database\Schema\Blueprint`, `Illuminate\Support\Facades\Schema`, `Illuminate\Support\Facades\DB` — proibido: dropar coluna existente

**C — Comportamento:**
- ANTES: tabela `ai_autopilot_approvals` sem `expires_at`. Approvals pending indefinidamente.
- DEPOIS: coluna `expires_at TIMESTAMP NULL INDEX` criada. Backfill atomic: `UPDATE ai_autopilot_approvals SET expires_at = LEAST(created_at + INTERVAL '24 hours', NOW())`. Model com cast `'expires_at' => 'datetime'`. Factory default `now()->addDay()`.

**E — Evidência:**
- [ ] `cd api && php artisan migrate` aplica sem erro
- [ ] `psql -c "\d ai_autopilot_approvals"` lista coluna `expires_at`
- [ ] `SELECT COUNT(*) FROM ai_autopilot_approvals WHERE expires_at IS NULL` retorna 0

**Status:** ✅ Concluída
**Dependências:** Nenhuma

---

#### TASK-3.3.2 — Criar AutopilotApprovalExpiryJob

**T — Tarefa:** Criar job que (hourly) marca approvals `pending` com `expires_at < now()` como `expired` (status novo) e marca run associada como `failed`.

**A — Arquivo:**
- `api/src/Domain/Ai/Jobs/AutopilotApprovalExpiryJob.php` (criar)
- `api/routes/console.php` (modificar — adicionar Schedule::job hourly)
- `api/tests/Feature/AutopilotApprovalExpiryJobTest.php` (criar)
- `api/src/Domain/Ai/Models/AiAutopilotApproval.php` (modificar — adicionar status `expired` em casts/enums se houver)

**Referência:** TASK-3.1.3 (`AutopilotZombieRunCleanupJob`) — mesmo padrão de scheduled job

**Imports autorizados:** `Illuminate\Bus\Queueable`, `Illuminate\Contracts\Queue\ShouldQueue`, `Domain\Ai\Models\AiAutopilotApproval`, `Domain\Ai\Models\AiAutopilotRun`, `Illuminate\Support\Facades\Log`, `Illuminate\Support\Facades\DB` — proibido: dispatch sync HTTP ao gateway

**C — Comportamento:**
- ANTES: approvals pending acumulam sem cleanup.
- DEPOIS: job query `AiAutopilotApproval::where('status', 'pending')->where('expires_at', '<', now())`. Para cada: transaction → `approval.status = 'expired'`, `approval.expired_at = now()`, `run.status = 'failed'`, `run.completed_at = now()`. Schedule `everyHour()` em console.php.

**E — Evidência:**
- [ ] `cd api && php artisan test --filter=AutopilotApprovalExpiryJobTest` passa
- [ ] Teste verifica: approval expirado vira `expired`, run vira `failed`
- [ ] `cd api && php artisan schedule:list` lista o job

**Status:** ✅ Concluída
**Dependências:** TASK-3.3.1

---

### Grupo 3.4 — Guardrails DB-Driven

#### TASK-3.4.1 — GuardrailEvaluatorService Consulta DB Por Tenant

**T — Tarefa:** Refatorar `resolveGuardrails($tenantId)` para mergear `config('ai.autopilot.static_guardrails')` com `AiAutopilotGuardrail::where('tenant_id', $tenantId)->where('is_active', true)->get()`. Cachear resultado por tenant (Redis, TTL=300s).

**A — Arquivo:**
- `api/src/Domain/Ai/Services/GuardrailEvaluatorService.php` (modificar)
- `api/tests/Unit/Domain/Ai/Services/GuardrailEvaluatorServiceTest.php` (criar/atualizar)

**Referência:**
- `api/src/Domain/Ai/Services/AiKnowledgeCacheService.php` — padrão Redis cache por tenant
- `api/src/Domain/Ai/Models/AiAutopilotGuardrail.php` — model existente

**Imports autorizados:** `Domain\Ai\Models\AiAutopilotGuardrail`, `Illuminate\Support\Facades\Cache`, `Illuminate\Support\Collection` — proibido: cache em-memória estática (race entre requests)

**C — Comportamento:**
- ANTES: linha 60-65 lê só `config('ai.autopilot.static_guardrails')`. Model DB ignorado. Tenant scoping fake.
- DEPOIS: `resolveGuardrails($tenantId)` retorna `Cache::remember("autopilot:guardrails:tenant:{$tenantId}", 300, fn() => $this->mergeGuardrails($tenantId))`. `mergeGuardrails` une config estático + DB query. Cache invalidado em update via observer (TASK-3.4.2).

**E — Evidência:**
- [ ] `cd api && php artisan test --filter=GuardrailEvaluatorServiceTest` passa
- [ ] Teste: criar `AiAutopilotGuardrail` BLOCK via factory → service retorna na list
- [ ] Cache hit em segunda chamada (mock Redis assertions)

**Status:** ✅ Concluída
**Dependências:** Nenhuma

---

#### TASK-3.4.2 — Observer Invalida Cache de Guardrails

**T — Tarefa:** Criar `AiAutopilotGuardrailObserver` que invalida `Cache::forget("autopilot:guardrails:tenant:{$tenantId}")` em `saved`/`deleted`. Registrar em service provider.

**A — Arquivo:**
- `api/src/Domain/Ai/Observers/AiAutopilotGuardrailObserver.php` (criar)
- `api/src/Domain/Ai/Providers/AiServiceProvider.php` (modificar — registrar observer) **ou** equivalente
- `api/tests/Unit/Domain/Ai/Observers/AiAutopilotGuardrailObserverTest.php` (criar)

**Referência:** Procurar padrão observer existente: `find api/src -name "*Observer.php" | head -3` antes de criar — usar padrão local

**Imports autorizados:** `Domain\Ai\Models\AiAutopilotGuardrail`, `Illuminate\Support\Facades\Cache` — proibido: lógica de negócio no observer

**C — Comportamento:**
- ANTES: cache de guardrails fica stale por até 300s após update.
- DEPOIS: `saved(AiAutopilotGuardrail $g)` e `deleted(AiAutopilotGuardrail $g)` chamam `Cache::forget("autopilot:guardrails:tenant:{$g->tenant_id}")`. Observer registrado no provider apropriado.

**E — Evidência:**
- [ ] `cd api && php artisan test --filter=AiAutopilotGuardrailObserverTest` passa
- [ ] Teste: salvar guardrail → cache invalidado
- [ ] `grep -rn "AiAutopilotGuardrailObserver" api/src/Domain/Ai/Providers/` retorna 1

**Status:** ✅ Concluída
**Dependências:** TASK-3.4.1

---

### Grupo 3.5 — Observabilidade

#### TASK-3.5.1 — Adicionar correlation_id em ai_autopilot_runs

**T — Tarefa:** Criar migration adicionando coluna `correlation_id` (uuid, nullable, indexed) e atualizar model. Gerar UUID no `AutopilotTriggerFired` event e persistir.

**A — Arquivo:**
- `api/database/migrations/2026_05_21_000002_add_correlation_id_to_ai_autopilot_runs.php` (criar)
- `api/src/Domain/Ai/Models/AiAutopilotRun.php` (modificar — fillable)
- `api/src/Domain/Ai/Events/AutopilotTriggerFired.php` (modificar — gerar UUID no construtor)
- `api/src/Domain/Ai/Jobs/DispatchAutopilotRunJob.php` (modificar — persistir e propagar correlation_id)

**Referência:** `api/database/migrations/2026_03_29_000001_add_tenant_id_to_ai_autopilot_approvals_table.php` — padrão add column nullable

**Imports autorizados:** `Illuminate\Support\Str` (UUID generation), `Illuminate\Database\Schema\Blueprint` — proibido: gerar UUID no gateway (deve nascer no api)

**C — Comportamento:**
- ANTES: sem `correlation_id`. Logs Laravel/gateway impossíveis de correlacionar.
- DEPOIS: coluna `correlation_id UUID NULL INDEX` em `ai_autopilot_runs`. Event `AutopilotTriggerFired` aceita ou gera UUID em `__construct`. Job persiste em run + propaga no payload `ai.run.request`. `Log::withContext(['correlation_id' => $correlationId])` em todo job.

**E — Evidência:**
- [ ] `cd api && php artisan migrate` aplica
- [ ] `psql -c "\d ai_autopilot_runs"` lista coluna `correlation_id`
- [ ] `cd api && php artisan test --filter=DispatchAutopilotRunJobTest` passa com novo teste verificando persistência

**Status:** ✅ Concluída
**Dependências:** Nenhuma

---

#### TASK-3.5.2 — Expandir MetricsService Com Métodos Autopilot

**T — Tarefa:** Adicionar 4 novos métodos em `MetricsService`: `recordAutopilotRunDuration`, `recordAutopilotToolIterations`, `recordAutopilotApprovalWaitTime`, `recordAutopilotLockContention`. Invocar nos pontos certos.

**A — Arquivo:**
- `api/src/Domain/Shared/Services/MetricsService.php` (modificar — adicionar 4 métodos)
- `api/src/Domain/Ai/Jobs/DispatchAutopilotRunJob.php` (modificar — chamar `recordAutopilotLockContention` quando lock falha + `recordAutopilotRunDuration` no failed/completion)
- `api/src/Domain/Ai/Jobs/AutopilotApprovalExpiryJob.php` (modificar — `recordAutopilotApprovalWaitTime`)
- `api/tests/Unit/Domain/Shared/Services/MetricsServiceAutopilotTest.php` (criar)

**Referência:**
- `api/src/Domain/Shared/Services/MetricsService.php:112` — método `recordAutopilotWebhookDuration` (padrão existente)
- `api/src/Domain/Shared/Services/MetricsService.php:129` — método `recordAutopilotGuardrailBlock`

**Imports autorizados:** padrão já presente no arquivo (Prometheus/StatsD client wrapper) — proibido: criar instanciar cliente paralelo

**C — Comportamento:**
- ANTES: MetricsService tem 2 métodos Autopilot (webhook duration, guardrail block). Faltam métricas de run, tool iterations, approval wait, lock contention.
- DEPOIS: 4 métodos novos com mesma assinatura `(float $seconds, array $labels)` ou `(int $count, array $labels)`. Cada invocado no ponto correto do fluxo. Dashboards Grafana `autopilot-*.json` consumem.

**E — Evidência:**
- [ ] `grep -n "recordAutopilotRunDuration\|recordAutopilotToolIterations\|recordAutopilotApprovalWaitTime\|recordAutopilotLockContention" api/src/Domain/Shared/Services/MetricsService.php` retorna 4
- [ ] `cd api && php artisan test --filter=MetricsServiceAutopilotTest` passa
- [ ] `curl -s localhost:8000/api/metrics | grep autopilot_` mostra novas métricas

**Status:** ✅ Concluída
**Dependências:** TASK-3.1.2, TASK-3.3.2

---

### Grupo 3.6 — Segurança

#### TASK-3.6.1 — Split Permission ai.autopilots.manage Em view/run/manage

**T — Tarefa:** Criar permissões `ai.autopilots.view` e `ai.autopilots.run`. Atualizar policies, FormRequests e controllers para usar gate granular. Manter `manage` como superset temporário (deploy zero-downtime).

**A — Arquivo:**
- `api/database/seeders/PermissionSeeder.php` (modificar — adicionar 2 perms) **ou** equivalente
- `api/src/Domain/Ai/Policies/AiAgentPolicy.php` (modificar — usar gate apropriado por método)
- `api/src/Domain/Ai/Http/Controllers/AiAutopilotRunController.php` (modificar — gate por endpoint)
- `api/src/Domain/Ai/Http/Requests/*Request.php` (modificar — gates relevantes)
- `api/tests/Feature/AutopilotRoutesAuthTest.php` (modificar/criar — cobrir 3 perms)

**Referência:** `api/src/Domain/Ai/Policies/AiAgentPolicy.php:13-33` — uso atual de `'ai.autopilots.manage'`

**Imports autorizados:** `Illuminate\Auth\Access\HandlesAuthorization`, `Illuminate\Foundation\Auth\Access\AuthorizesRequests` — proibido: hardcode de role check sem usar Gate

**C — Comportamento:**
- ANTES: única perm `ai.autopilots.manage` usada em 100% dos endpoints.
- DEPOIS: 3 perms. `view` → GET endpoints. `run` → POST run/cancel. `manage` → admin (playbooks, guardrails, tools, agents config). Roles existentes recebem `view` + `run` por padrão na migration de seeder. `manage` mantém comportamento atual durante 1 release.

**E — Evidência:**
- [ ] `cd api && php artisan db:seed --class=PermissionSeeder` adiciona novas perms
- [ ] `grep -rn "'ai.autopilots.view'\|'ai.autopilots.run'" api/` retorna ≥ 5 ocorrências
- [ ] `cd api && php artisan test --filter=AutopilotRoutesAuthTest` passa cobrindo 3 perms

**Status:** ✅ Concluída
**Dependências:** Nenhuma

---

#### TASK-3.6.2 — Sanitização de message_body em AiContextBuilderService

**T — Tarefa:** Adicionar método `sanitizeUserInput(string $body): string` em `AiContextBuilderService` que trunca para 4000 chars (config-driven), escapa delimitadores de system prompt e remove padrões de prompt injection.

**A — Arquivo:**
- `api/src/Domain/Ai/Services/AiContextBuilderService.php` (modificar — adicionar método + usar em buildContext)
- `api/config/ai.php` (modificar — adicionar `autopilot.input_sanitization` config block)
- `api/tests/Unit/Domain/Ai/Services/AiContextBuilderSanitizationTest.php` (criar)

**Referência:** `api/src/Domain/Ai/Services/AiContextBuilderService.php` — método `buildContext` existente

**Imports autorizados:** `Illuminate\Support\Str`, `Illuminate\Support\Facades\Log` — proibido: usar regex global sem cobertura de testes

**C — Comportamento:**
- ANTES: `message_body` cru entra direto no prompt do LLM. Vulnerável a "Ignore previous instructions", system prompt injection, payload extenso.
- DEPOIS: `sanitizeUserInput` aplicado antes de incluir no contexto: (1) trunca para `config('ai.autopilot.input_sanitization.max_chars', 4000)`, (2) escapa delimitadores configurados (`<<<`, `>>>`, `<|`, `|>`), (3) loga match contra lista de regex em `config('ai.autopilot.input_sanitization.injection_patterns')` sem remover (observabilidade), (4) wrappa input em `<<<USER_INPUT>>>\n{body}\n<<<END>>>`.

**E — Evidência:**
- [ ] `cd api && php artisan test --filter=AiContextBuilderSanitizationTest` passa
- [ ] Teste: body com "Ignore previous instructions" → match registrado via Log spy
- [ ] Teste: body com 5000 chars → truncado para 4000
- [ ] Teste: body com `<<<` → escapado

**Status:** ✅ Concluída
**Dependências:** Nenhuma

---

### Grupo 3.7 — Snapshot Cache + Docs

#### TASK-3.7.1 — Cache em AutopilotRunSnapshotResolver

**T — Tarefa:** Adicionar cache Redis em `AutopilotRunSnapshotResolver::resolve()` por chave `autopilot:snapshot:{tenant}:{agent}:{ticket}` com TTL=60s. Invalidar via observer em novo message do ticket ou update do agent.

**A — Arquivo:**
- `api/src/Domain/Ai/Services/AutopilotRunSnapshotResolver.php` (modificar)
- `api/src/Domain/Chat/Observers/MessageObserver.php` (modificar — invalidar cache em created) **ou** equivalente
- `api/tests/Unit/Domain/Ai/Services/AutopilotRunSnapshotResolverCacheTest.php` (criar)

**Referência:** TASK-3.4.1 (`GuardrailEvaluatorService`) — mesmo padrão `Cache::remember`

**Imports autorizados:** `Illuminate\Support\Facades\Cache`, `Domain\Chat\Models\Message` — proibido: cache em-memória sem TTL

**C — Comportamento:**
- ANTES: snapshot reconstruído por run. N+1 queries em conversation_history + tools + agent_files.
- DEPOIS: `Cache::remember("autopilot:snapshot:{$tenantId}:{$agentId}:{$ticketId}", 60, fn() => $this->buildSnapshot(...))`. MessageObserver `created` invalida cache do ticket relevante.

**E — Evidência:**
- [ ] `cd api && php artisan test --filter=AutopilotRunSnapshotResolverCacheTest` passa
- [ ] Teste com `DB::enableQueryLog()`: segunda chamada idêntica em < 60s gera 0 queries
- [ ] Teste: MessageObserver invalida cache

**Status:** ✅ Concluída
**Dependências:** Nenhuma

---

#### TASK-3.7.2 — Documentar playbook_id Nullable + Helper isAdHoc()

**T — Tarefa:** Adicionar PHPDoc detalhado em `AiAutopilotRun::$playbook_id` referenciando decisão da migration 2026-03-22 ("V2 ad-hoc runs e simulator"). Criar método `isAdHoc(): bool`.

**A — Arquivo:**
- `api/src/Domain/Ai/Models/AiAutopilotRun.php` (modificar — PHPDoc + método)
- `api/tests/Unit/Domain/Ai/Models/AiAutopilotRunTest.php` (criar/modificar — testar `isAdHoc()`)

**Referência:** Migration `api/database/migrations/2026_03_22_134119_make_ai_autopilot_runs_playbook_columns_nullable.php` (comentário "V2 ad-hoc runs e simulator")

**Imports autorizados:** N/A — proibido: alterar nullability sem nova migration

**C — Comportamento:**
- ANTES: `playbook_id` nullable sem doc no domain. Razão da decisão só na migration.
- DEPOIS: PHPDoc explica que `null` indica ad-hoc run (simulator, V2 trigger sem playbook). Método `isAdHoc(): bool { return $this->playbook_id === null; }`. Comentário em `DispatchAutopilotRunJob` no ponto que pode criar run sem playbook.

**E — Evidência:**
- [ ] `grep -n "isAdHoc" api/src/Domain/Ai/Models/AiAutopilotRun.php` retorna 1
- [ ] `cd api && php artisan test --filter=AiAutopilotRunTest` passa

**Status:** ✅ Concluída
**Dependências:** Nenhuma

---

## Fase 4 — Gateway (gateway/)

### Grupo 4.1 — Correlation ID Propagation

#### TASK-4.1.1 — Consumer do correlation_id No Gateway

**T — Tarefa:** Gateway lê `correlation_id` do payload `ai.run.request`, propaga em logger NestJS (contexto) e em todos eventos de saída (`ai.run.completed`, `ai.run.failed`, `ai.run.blocked`, `ai.run.tool_call`, `ai.run.tool_result`).

**A — Arquivo:**
- `gateway/src/domains/ai/orchestrators/ai-run-orchestrator.service.ts` (modificar — capturar correlation_id)
- `gateway/src/domains/ai/dto/ai-run-request.dto.ts` (modificar — campo correlation_id)
- `gateway/src/domains/ai/ai-events.publisher.ts` (modificar — incluir correlation_id em todos events) **ou** equivalente
- `gateway/src/domains/ai/__tests__/correlation-id-propagation.spec.ts` (criar)

**Referência:** `gateway/src/domains/ai/orchestrators/ai-run-orchestrator.service.ts` — orchestrator existente que recebe `ai.run.request`

**Imports autorizados:** NestJS `@nestjs/common` Logger, `class-validator`, `class-transformer` — proibido: acessar PostgreSQL ou Redis fora dos serviços apropriados

**C — Comportamento:**
- ANTES: payload sem `correlation_id`. Logs gateway sem identificador comum com Laravel.
- DEPOIS: DTO aceita `correlation_id?: string`. Orchestrator chama `this.logger.setContext({ correlation_id })` ou equivalente. Todos publishers de eventos de saída incluem `correlation_id`.

**E — Evidência:**
- [ ] `pnpm --filter gateway build` passa
- [ ] `pnpm --filter gateway test --filter=correlation-id-propagation` passa
- [ ] Log do gateway contém `"correlation_id":"<uuid>"` em todo evento de uma run

**Status:** ✅ Concluída
**Dependências:** TASK-3.5.1

---

### Grupo 4.2 — Cancel Listener

#### TASK-4.2.1 — Gateway Aborta Tool Loop em ai.run.cancel_requested

**T — Tarefa:** Adicionar listener BullMQ/Redis no gateway que escuta `ai.run.cancel_requested`. Quando recebe `{ run_id }`, marca flag de cancelamento na execução ativa; tool loop verifica flag entre iterações e aborta com status `cancelled`.

**A — Arquivo:**
- `gateway/src/domains/ai/listeners/ai-run-cancel.listener.ts` (criar)
- `gateway/src/domains/ai/orchestrators/ai-run-orchestrator.service.ts` (modificar — check flag entre tool iterations)
- `gateway/src/domains/ai/ai-cancellation.registry.ts` (criar — in-memory registry de runs canceladas)
- `gateway/src/domains/ai/__tests__/ai-run-cancel.spec.ts` (criar)
- `api/src/Domain/Ai/Http/Controllers/AiAutopilotRunController.php` (modificar — `destroy()` publica evento no canal Redis/BullMQ)

**Referência:** `gateway/src/domains/ai/orchestrators/ai-run-orchestrator.service.ts` — loop de tool calls existente

**Imports autorizados (gateway):** `@nestjs/bullmq`, `bullmq`, `@nestjs/common` — proibido: acesso PostgreSQL direto
**Imports autorizados (api):** `Illuminate\Support\Facades\Redis`, `Illuminate\Support\Facades\Queue` — proibido: import de classes gateway

**C — Comportamento:**
- ANTES: `DELETE /api/ai/runs/{id}` marca status=cancelled local. Gateway segue executando até completion/fail — custo LLM desperdiçado.
- DEPOIS: api publica `ai.run.cancel_requested { run_id, tenant_id }` no canal Redis. Gateway listener registra `run_id` em `AiCancellationRegistry`. Tool loop verifica registry antes de cada iteração; se cancelado, aborta + publica `ai.run.completed { status: 'cancelled' }`. Run final: `status=cancelled`.

**E — Evidência:**
- [ ] `pnpm --filter gateway test --filter=ai-run-cancel` passa
- [ ] `cd api && php artisan test --filter=AiAutopilotRunControllerCancelTest` passa
- [ ] Teste integrado: DELETE → gateway aborta < 1s

**Status:** ✅ Concluída
**Dependências:** TASK-4.1.1

---

## Fase 5 — Integration (E2E)

### Grupo 5.1 — Testes de Failure Path

#### TASK-5.1.1 — E2E: Failure Paths Cross-Camada

**T — Tarefa:** Implementar 7 testes E2E cobrindo cenários de falha: gateway timeout, tool exception, guardrail BLOCK, approval expired, approval rejected, concurrent webhooks, correlation_id end-to-end.

**A — Arquivo:**
- `api/tests/E2E/Autopilot/GatewayTimeoutTest.php` (criar)
- `api/tests/E2E/Autopilot/ToolExceptionTest.php` (criar)
- `api/tests/E2E/Autopilot/GuardrailBlockTest.php` (criar)
- `api/tests/E2E/Autopilot/ApprovalExpiredTest.php` (criar)
- `api/tests/E2E/Autopilot/ApprovalRejectedTest.php` (criar)
- `api/tests/E2E/Autopilot/ConcurrentWebhooksTest.php` (criar)
- `api/tests/E2E/Autopilot/CorrelationIdEndToEndTest.php` (criar)

**Referência:** `api/tests/E2E/Autopilot/` — diretório existente; `api/tests/E2E/sim-real-company-30-chats.php` — padrão E2E do projeto

**Imports autorizados:** `Tests\TestCase`, `Illuminate\Foundation\Testing\RefreshDatabase`, `Illuminate\Support\Facades\Bus`, `Illuminate\Support\Facades\Event`, `Illuminate\Support\Facades\Redis` — proibido: chamadas reais ao gateway (mock obrigatório)

**C — Comportamento:**
- ANTES: zero cobertura E2E para failure paths. Bugs em produção não detectados em CI.
- DEPOIS: 7 testes cobrindo:
  1. `GatewayTimeoutTest`: mock gateway nunca responde → watchdog marca run=failed após threshold.
  2. `ToolExceptionTest`: tool handler lança exception → action.status=failed + run continua/aborta conforme política.
  3. `GuardrailBlockTest`: guardrail BLOCK criado via factory → run aborta com razão registrada.
  4. `ApprovalExpiredTest`: approval com expires_at < now → cleanup job marca expired + run=failed.
  5. `ApprovalRejectedTest`: usuário rejeita approval → run=failed imediato.
  6. `ConcurrentWebhooksTest`: 2 dispatches paralelos mesmo msg_id → apenas 1 run criado (Redis real).
  7. `CorrelationIdEndToEndTest`: dispatch run → correlation_id persistido + presente em payload + em log fake do gateway.

**E — Evidência:**
- [ ] `cd api && php artisan test --testsuite=E2E --filter=Autopilot` passa todos 7 testes
- [ ] Coverage de fluxos críticos via `php artisan test --coverage --filter=Autopilot` aumenta ≥ 15%

**Status:** ✅ Concluída
**Dependências:** TASK-3.1.3, TASK-3.3.2, TASK-3.4.1, TASK-3.5.1, TASK-4.1.1, TASK-4.2.1

---

## Resumo de Dependências

```
3.1.1 (lock TTL) ─→ 3.1.2 (failed handler) ─→ 3.1.3 (watchdog)
3.2.1 (remove TriggerLog) (independente)
3.2.2 (remove backup) (independente)
3.3.1 (expires_at) ─→ 3.3.2 (expiry job)
3.4.1 (guardrails DB) ─→ 3.4.2 (observer cache invalidation)
3.5.1 (correlation_id col) ─→ 4.1.1 (gateway consumer)
3.5.2 (MetricsService) requer 3.1.2, 3.3.2
3.6.1 (perm split) (independente)
3.6.2 (sanitization) (independente)
3.7.1 (snapshot cache) (independente)
3.7.2 (playbook doc) (independente)
4.1.1 (correlation gateway) ─→ 4.2.1 (cancel listener)
5.1.1 (E2E) requer 3.1.3, 3.3.2, 3.4.1, 3.5.1, 4.1.1, 4.2.1
```

## Próximo

```
/prevec-execute-task autopilot-stability TASK-3.1.1
```
