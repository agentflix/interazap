# QA Agent — Persistent Memory

## Common Audit Quality Issues

### Scoped QA vs Global tsconfig.spec Noise

- Em validações scoped de frontend, erro de TypeScript sobre arquivo não listado em `tsconfig.spec.json` pode ser ruído global de configuração do workspace.
- Se o conteúdo funcional do pacote está limpo em `get_errors`, com comportamento e testes do escopo cobrindo regressões, classificar esse ruído como **não-blocker scoped**.
- Em Chat TASKS-026 (transferência com reason/internal_note), padrão validado: specs do escopo passam e `gate:test` pode reprovar por 1 falha externa em `src/app/pages/chat/integration/components/integration-form/integration-form.spec.ts`; classificar como blocker global fora do escopo quando não houver dependência funcional com transfer.
- Em revalidação de `reports/components/*-report`, além de `get_errors`, verificar casts de template (`$any(...)`) e sinais com `Record<string, unknown>`: podem passar no gate de tipo, mas violam o contrato frontend de evitar `any/unknown`.
- `get_errors` pode retornar limpo enquanto `npm run gate:all` falha em regras ESLint (`@typescript-eslint/consistent-type-definitions`) em arquivos `.ts`; para sign-off scoped, usar sempre o output do gate como fonte primária de bloqueio.

### False Positives from Missing Evidence

- Findings with placeholder paths (`.../...component.ts`) and approximate lines (`~XX`) are common in agent-generated audits
- Always verify: file exists AND line number is precise before marking CRITICAL
- God component claims must be verified with `wc -l` — agents may cite non-existent paths

### PREVC Document QA

- Reprovar plano/task quando `TASKS-xxx.md` nao seguir o `task-template.md` com Goal/Constraints/Context/Etapas/Criterios/Evidencias; sem isso o handoff entre agentes e a validacao QA ficam nao executaveis.
- Em auditoria documental, validar o contrato real do codigo antes de aprovar: divergencias entre endpoint/payload documentado e implementacao existente (ex.: `/transfer` vs `/transfers`, `user_id` vs `to_user_id`) sao blocker de executabilidade.
- Reprovar pacote PREVC quando duas tasks do mesmo artefato entram em conflito de escopo/arquivo (ex.: uma task diz para nao alterar `chat-navbar.ts` e outra da mesma entrega exige altera-lo); essa contradicao torna a execucao nao deterministica.
- Reprovar handoff frontend quando a task depender de "spec de @DESIGNER aprovada" sem indicar artefato rastreavel (arquivo/path) para essa spec; a dependencia fica ambigua entre Review e Execution.
- Reprovar pacote PREVC quando o artefato de spec existe, mas permanece como placeholder/pendente: isso bloqueia a executabilidade das entregas dependentes e quebra a rastreabilidade do handoff `@DESIGNER -> @FRONTEND`.
- Reprovar task com criterios Vitest/Jest/Pest declarados se Context/Etapas apontarem apenas arquivos de producao e nao explicitarem onde os testes devem ser criados/ajustados; isso força inferencia do agente e reduz executabilidade.
- Em validacao final de TASKS para agentes, reprovar criterios de conclusao sem mapeamento para evidencias/testes/artefatos verificaveis; checklist manual sem ponte para teste ou parecer nomeado enfraquece a fase V do PREVC.
- Em pacote de UI guiado por `.claude/skills/frontend-flow/SKILL.md`, reprovar spec/task que enumera apenas `loading/error/disabled` e omite `empty`, pois o skill exige os 4 estados `loading/empty/error/default` para handoff executavel.
- Reprovar `Entrega`/task de Validation quando ela consolida apenas um subconjunto dos testes criticos declarados nas entregas anteriores; a fase V precisa carregar tambem os testes de estados/affordances/realtime que sustentam os criterios de P/E.

### File Count Inconsistencies

- When a report claims N files analyzed, verify with `find . -name "*.ts" | wc -l`
- Partition sums (Agent A + B + C + D) must equal total claimed
- TS + HTML totals should reconcile with actual counts

### Schema Incompleteness

- CRITICAL/HIGH findings MUST have: exact file path, exact line number(s), specific remediation
- Generic remediation (e.g., "use takeUntilDestroyed pattern") is insufficient for HIGH items in a table
- Every finding ID must be independently verifiable

### Verified Patterns in This Project

- God components confirmed (>500 lines): `tenants.ts` (1037), `uazapi-instances.ts` (1244), `agenda.ts` (1148), `chat-negotiation-view.ts` (1031), `negotiations.ts` (870)
- Memory leaks confirmed: `simulator.ts` setInterval without ngOnDestroy, `data-table.ts` missing takeUntilDestroyed, `sortable-header.ts` missing takeUntilDestroyed
- `realtime.service.ts`: Subject map cleared but never completed (valid HIGH)
- Angular refactor QA: extração de subcomponentes standalone com vários `input()`/`output()` exige atualizar specs para cobrir renderização e bridge de eventos; testes antigos do container focados só em helpers/estado não validam o refactor.
- Frontend gate baseline recorrente: falhas em `user-chat.spec.ts` com `TS2339`/`TS7053` (membros removidos/renomeados após refactor) podem bloquear execução de specs direcionados sem relação com arquivos auditados; validar ausência de regressão local com `get_errors` por arquivo-alvo.
- Critério de sign-off scope-isolated: quando `get_errors` está limpo em todos os arquivos alterados e o gate falha somente em baseline conhecida fora do diff (ex.: `src/app/pages/chat/components/user-chat/user-chat.spec.ts`), classificar como risco residual não-bloqueante para commit scoped.
- Gate build pode falhar em paralelo por desalinhamento entre `user-chat.html` e `user-chat.ts` (inputs/componentes standalone não importados + métodos/sinais removidos). Tratar como bloqueador global de release, mesmo quando o diff auditado é de outro módulo.
- Reports refactor pattern: quando componente usa `[series]="chartSeries()"` com `af-apexchart`, e depende de `BaseReportComponent` sem tipar o segundo genérico, o `DefaultReportChartSeries` cai em `unknown` e quebra template type-check (`unknown` not assignable to `number[]`). Validar `BaseReportComponent<ReportType, NumericReportChartSeries>` nos relatórios com gráficos de eixo.
- QA reports/components stale-import check: filtrar `*.spec.ts` ao buscar imports proibidos em subclasses de `BaseReportComponent`; specs podem importar `ReportsService` e gerar falso positivo fora do escopo do checklist.
- Gate pattern: `ng lint` com contagem de "error" pode retornar 0 e ainda deixar imports órfãos quando a regra de no-unused-vars/imports não está ativa; complementar auditoria com varredura de imports não usados em arquivos críticos.
- Frontend contract pattern: `Record<string, unknown>` e casts com `as unknown as` continuam violando o contrato do projeto (no any/unknown), mesmo com gates de compilação/lint verdes.
- Reports package QA pattern: `get_errors` limpo e gate global falhando fora do escopo não garante conformidade de contrato frontend; em `reports/components/*-report.ts`, validar explicitamente proibição de `<table>` nativo, badges inline e cores de estado hardcoded (`text-red-*`, `text-emerald-*`, etc.), pois esses desvios não aparecem como erro de compilação.
- Em auditoria regressiva scoped, quando o requester marcar explicitamente `<table>` nativo, badge inline e cores hardcoded como dívida herdada preexistente, reportar como risco residual/documentado (não finding) salvo evidência de piora no diff do lote.
- Backend QA gating pattern: mesmo com testes direcionados verdes (blockers corrigidos), `composer gate:all` reprovado por `pint --test` (style issues) continua bloqueando aprovação para commit/release.
- QA protocol-only pattern: quando `ticket_number` é removido no código de domínio (`api/src/**`, `app/src/**`) e `composer gate:all` falha apenas por Pint em arquivos não relacionados ao ticket/protocol, classificar como sem blocker funcional de escopo e registrar risco residual de drift apenas para ambientes já migrados sem reset de migration base.
- Frontend QA pattern: `npm run gate:all` pode passar com suite/build verdes e ainda deixar erro de editor TS quando `app/tsconfig.spec.json` restringe `include` a poucos arquivos de produção; tratar como bloqueador de aprovação scoped se os specs alterados importarem arquivos fora desse include.
- Revalidação do blocker `tsconfig.spec`: considerar resolvido apenas quando os dois sinais estiverem verdes juntos no escopo `app/`: `get_errors` sem ocorrências no projeto e `npm run gate:all` com `Test Files 116/116`, `Tests 644/644` e build de produção concluído.
- TASKS-026 scoped revalidation pattern: quando backend dedicado de transfer valida `reason` obrigatório + `regex:/\\S/` e action exige `string $reason`, com suíte direcionada verde (`ChatTicketTransferControllerTest`, `ChatTicketTransferActionsTest`, `ChatMessageActionsTest` = 24/24), aprovar por escopo mesmo com 1 falha externa persistente em `integration-form.spec.ts` sem arquivos frontend do ticket no diff.
- QA de commit parcial (staged-only): validar somente `git diff --cached` e ignorar unstaged/untracked por solicitação explícita; aprovação depende de zero blockers no diff staged + evidência recente de `app gate:all` verde.
- Backend refactor pattern (Laravel): quando controller ganha método auxiliar que manipula `roles` direto no model (`syncRoles`) fora de Request/DTO/Action, tratar como risco HIGH de regressão de autorização (bypass de guards centrais como `guardSuperAdminAssignment`) mesmo se a rota ainda não estiver publicada; exigir cobertura explícita para a nova action extraída e para o endpoint final.
- TASK-013-S2 acceptance check: além de testes verdes, validar metas estruturais do plano (`CrmNegotiationActions` < 100 linhas e cada action nova de chat <= 250 linhas); quando exceder, classificar como ajuste necessário mesmo sem falhas funcionais.
- TASK-013-S2 re-audit pattern: com `CrmNegotiationActions` em 49 linhas e actions extraídas de chat até 231 linhas, manter aprovação somente se os guardrails funcionais permanecerem ativos (`forceClose` com policy + suppress de mensagens/CSAT em modo `forced`, e consultas de ticket sempre com filtro `tenant_id`).
- Operação QA em terminal: se shell entrar em prompt `heredoc>` por comando truncado/colado, interromper e encerrar o terminal em background antes de rerodar os testes; não confiar em exit code desse shell preso mesmo quando houver logs parciais de testes.
- QA scoped API pattern: quando o solicitante dispensa explicitamente `composer gate:all`, aceitar aprovação baseada em inspeção estática do diff + suíte unitária direcionada cobrindo cada ID do escopo; registrar como risco residual apenas ausência de validação cross-módulo fora do escopo.
- Reavaliação scoped com critério de testes fixado pelo requester: se TASKS/AUDIT estiverem coerentes entre si e o código dos IDs do sprint estiver confirmado, divergência de contagem histórica de testes entre rodadas deve ser tratada como observação documental não-bloqueante para o veredito scoped.
- TASK-013-S4 pattern: validar pré-requisito de `final class` com varredura explícita de mocks por herança nos testes-alvo das policies; se não houver `Mockery::mock(Policy::class)` e houver testes de contrato instanciando policies concretas, tratar requisito como atendido sem necessidade de interface adicional.
- TASK-013-S4 sign-off pattern: mesmo com código-alvo implementado e suíte focada verde, reprovar Sprint 4 quando `TASKS-013` mantiver checklists/estado como `todo` e faltar evidência objetiva de fechamento de baseline de magic numbers/PHPDoc e atualização do `AUDIT-API-001`.
- Gateway security hardening pattern: ao adicionar `InternalApiKeyGuard`/`ThrottlerGuard` em controllers de chat, suites e2e antigas tendem a quebrar com `401 Unauthorized`; bloqueia gate até atualizar setup de teste com header/chave interna válida ou override explícito do guard nos testes e2e.
- Frontend test runner mismatch pattern: specs novas escritas em estilo Vitest podem aparecer com "No test suite found" no `vitest run` enquanto o gate oficial usa `ng test`; tratar como blocker de cobertura quando os testes adicionados não executam em nenhum dos dois fluxos.
- Multi-tenant password reset pattern: validação por `email` sem escopo de tenant + regra condicional `tenant_id !== null` em token permite janela de bypass para registros legados/não populados; classificar como risco HIGH de isolamento até cobrir com testes de tenant A/B.
- Password reset cross-tenant structural risk: enquanto `auth_password_reset_tokens` tiver PK apenas por `email`, qualquer estratégia de `tenant_id` por update posterior pode sobrescrever contexto entre tenants com mesmo email; tratar como blocker de isolamento até chave/consulta considerar tenant no registro do token.
- Angular migration pattern (`@Input` -> `input()`): além do componente, validar template (`inputSignal()` vs propriedade direta) e specs legados que atribuem input via mutação (`component.foo = ...`), pois isso quebra gate de build/teste com `TS2540`/`TS2339` mesmo quando lint está verde.

### Files That DO NOT Exist (audit false positives)

- `app/src/app/pages/crm/deals/deals.ts`
- `app/src/app/pages/crm/campaigns/campaign.ts` (exists: `campaigns.ts` 258l, `campaign-form.ts` 519l)
- `app/src/app/pages/settings/platform-settings/platform-settings.ts`
- `app/src/app/pages/crm/dashboard/dashboard.ts`

## FEAT-052 — Rodízio Automático de Atendimentos (QA Findings)

**Arquivo detalhado:** `FEAT-052-findings.md`

### Bugs de produção encontrados (não corrigidos por QA)

1. **Middleware `can:` nas rotas de routing queue quebra para usuários normais** — o Laravel cria instância vazia de `ChatRoutingQueue` (sem `tenant_id`), fazendo a policy `view`/`manage` retornar `false` para qualquer usuário não-super-admin.
2. **Parâmetro `{id}` do prefixo `channels/{id}/routing-queue` não propaga para `storeAgent`** quando combinado com `defaults('scope', 'channel')`, causando 404 na adição de agentes a filas de canal.
3. **`ChatRoutingService` é `final class`** — impossibilita mock com Mockery, limitando testes de resiliência a exceções.
4. **`last_assigned_at` trunca para segundos no PostgreSQL** — em alta carga, round robin pode ser não-determinístico.

### Veredicto QA
- 26 testes novos passando, coverage do service 91.2%, zero regressão em ChatTicket existentes.
- Grupo `integration` (PostgreSQL real) passando.

## Audit Report Validation Checklist

1. Every finding must have: ID + Severity + Category + exact File + exact Line(s) + Effort + Pattern + Rule
2. God components: verify with `wc -l` + confirm path exists
3. Memory leaks: confirm file exists AND confirm missing `takeUntilDestroyed`/`ngOnDestroy`
4. Partition counts must sum to total claimed
5. No placeholder paths (`...`) or approximate lines (`~XX`) in CRITICAL findings
