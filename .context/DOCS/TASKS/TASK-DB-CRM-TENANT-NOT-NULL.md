# Tasks: DB Fix — tenant_id NOT NULL (CRM + AI Autopilot)

> Corrige violações de multi-tenancy: `crm_contact_tags.tenant_id` e
> `ai_autopilot_approvals.tenant_id` declarados como `nullable`, o que viola
> a Regra Absoluta 14 do projeto (toda query passa por `BelongsToTenant`).
> Não há feature doc associada — são correções de schema de caráter urgente.

---

## Histórico / Contexto

| Issue | Estado | Descrição |
|-------|--------|-----------|
| **Issue 1** — `ForeignKeyDefinition::comment()` antes de `constrained()` | **FECHADO** | PHPStan não reporta mais o problema. Todas as migrations CRM já seguem a ordem `->comment()->constrained()`. Nenhuma ação adicional necessária. |
| **Issue 2a** — `crm_contact_tags.tenant_id` nullable | **ABERTO** | Ver TASK-DB-001 |
| **Issue 2b** — `ai_autopilot_approvals.tenant_id` nullable | **ABERTO** | Ver TASK-DB-002 |

---

## Estrutura Hierárquica

| Nível | Significado |
|-------|-------------|
| DB-001 | Fix crm_contact_tags.tenant_id NOT NULL |
| DB-002 | Fix ai_autopilot_approvals.tenant_id NOT NULL |
| DB-003 | Teste Pest de garantia tenant_id NOT NULL |

---

## TASK-DB-001 — Corrigir crm_contact_tags.tenant_id NOT NULL

- [ ] **TASK-DB-001** ⏳

  **T — Tarefa:**
  Remover `->nullable()` de `crm_contact_tags.tenant_id` na migration histórica (para
  `migrate:fresh` ser correto) e criar migration compensatória que, em ambientes existentes:
  1. Faz backfill de `tenant_id` a partir de `crm_contacts.tenant_id` via `crm_contact_id`;
  2. Deleta linhas órfãs (sem contato válido ou sem tenant resolvido);
  3. Aplica `ALTER COLUMN … SET NOT NULL`.

  **A — Arquivo:**

  | Arquivo | Operação |
  |---------|----------|
  | `api/database/migrations/2026_01_01_000020_create_crm_base_tables.php` | Editar linha 170: remover `->nullable()` |
  | `api/database/migrations/2026_05_10_000001_fix_crm_contact_tags_tenant_id_not_null.php` | Criar (migration compensatória) |

  **C — Comportamento:**

  _ANTES_
  ```sql
  -- crm_contact_tags.tenant_id é nullable uuid
  -- Linhas podem existir com tenant_id IS NULL (violação multi-tenant)
  -- migrate:fresh cria a coluna como nullable
  ```

  _Passo 1 — Edição na migration histórica (linha 170):_
  ```php
  // DE:
  $table->foreignUuid('tenant_id')->nullable()->comment('Tenant ao qual a relação pertence')->constrained('platform_tenants');
  // PARA:
  $table->foreignUuid('tenant_id')->comment('Tenant ao qual a relação pertence')->constrained('platform_tenants');
  ```

  _Passo 2 — Migration compensatória `up()`:_
  ```php
  public function up(): void
  {
      // 1. Backfill: propagar tenant_id a partir do contato vinculado
      DB::statement("
          UPDATE crm_contact_tags cct
          SET    tenant_id = cc.tenant_id
          FROM   crm_contacts cc
          WHERE  cct.crm_contact_id = cc.id
            AND  cct.tenant_id IS NULL
      ");

      // 2. Limpar órfãos irrecuperáveis (contato deletado ou sem tenant_id)
      DB::statement("
          DELETE FROM crm_contact_tags
          WHERE  tenant_id IS NULL
      ");

      // 3. Aplicar NOT NULL constraint
      DB::statement("
          ALTER TABLE crm_contact_tags
          ALTER COLUMN tenant_id SET NOT NULL
      ");
  }
  ```

  _Migration compensatória `down()` (reversível):_
  ```php
  public function down(): void
  {
      DB::statement("
          ALTER TABLE crm_contact_tags
          ALTER COLUMN tenant_id DROP NOT NULL
      ");
  }
  ```

  _DEPOIS_
  ```sql
  -- crm_contact_tags.tenant_id é NOT NULL uuid
  -- migrate:fresh cria a coluna sem nullable
  -- Todas as linhas têm tenant_id preenchido
  ```

  **Riscos e mitigações:**

  | Risco | Mitigação |
  |-------|-----------|
  | Linhas com `crm_contact_id` apontando para contato deletado (sem cascade) | Deletadas no passo 2 — não bloqueia migration |
  | Constraint unique `uq_crm_contact_tags(tenant_id, crm_contact_id, crm_tag_id)` já depende de tenant_id | Não há conflito; backfill preenche antes do NOT NULL |
  | Tabela grande em produção (lock) | Usar `ALTER TABLE … ALTER COLUMN … SET NOT NULL` que no PostgreSQL 17 é um metadata-only operation se não houver `CHECK` associado — lock breve |

  **E — Evidência:**
  - [ ] `php artisan migrate:fresh --seed` (ambiente local) — zero erros
  - [ ] `php artisan migrate` (incremental, sobre DB existente) — zero erros
  - [ ] `php artisan migrate:rollback` — reverte sem erro
  - [ ] Query confirma NOT NULL no ambiente de testing:
    ```sql
    SELECT is_nullable
    FROM   information_schema.columns
    WHERE  table_name = 'crm_contact_tags'
      AND  column_name = 'tenant_id';
    -- Esperado: 'NO'
    ```
  - [ ] Nenhuma linha com `tenant_id IS NULL` após migration:
    ```sql
    SELECT COUNT(*) FROM crm_contact_tags WHERE tenant_id IS NULL;
    -- Esperado: 0
    ```
  - [ ] PHPStan L6 limpo (`composer gate:all`)

  **Responsável:** DBA
  **Status:** Pendente

---

## TASK-DB-002 — Corrigir ai_autopilot_approvals.tenant_id NOT NULL

- [ ] **TASK-DB-002** ⏳

  **T — Tarefa:**
  Remover `->nullable()` de `ai_autopilot_approvals.tenant_id` na migration
  `2026_03_29_000001` (corrige `migrate:fresh`) e criar migration compensatória que,
  em ambientes existentes:
  1. Faz backfill de `tenant_id` a partir de `ai_autopilot_runs.tenant_id` via `run_id`;
  2. Deleta aprovações sem run válido ou sem tenant resolvido;
  3. Aplica `ALTER COLUMN … SET NOT NULL`.

  **Contexto adicional:** A migration histórica `2026_01_01_000050` e a migration de
  recriação `2026_03_04_170000` já criavam `tenant_id` como NOT NULL — o nullable foi
  introduzido apenas pela migration de backport `2026_03_29_000001`, que adicionou a
  coluna com `->nullable()` para ambientes que não passaram pela recriação de 03-04.

  **A — Arquivo:**

  | Arquivo | Operação |
  |---------|----------|
  | `api/database/migrations/2026_03_29_000001_add_tenant_id_to_ai_autopilot_approvals_table.php` | Editar linha 24: remover `->nullable()` |
  | `api/database/migrations/2026_05_10_000002_fix_ai_autopilot_approvals_tenant_id_not_null.php` | Criar (migration compensatória) |

  **C — Comportamento:**

  _ANTES_
  ```sql
  -- ai_autopilot_approvals.tenant_id é nullable uuid
  -- Aprovações podem existir sem tenant (violação BelongsToTenant)
  -- migrate:fresh com 2026_03_29 adiciona a coluna como nullable
  ```

  _Passo 1 — Edição na migration `2026_03_29_000001` (linha 24):_
  ```php
  // DE:
  $table->uuid('tenant_id')->nullable()->after('id');
  // PARA:
  $table->uuid('tenant_id')->after('id');
  ```

  _Passo 2 — Migration compensatória `up()`:_
  ```php
  public function up(): void
  {
      // 1. Backfill: propagar tenant_id a partir da run vinculada
      DB::statement("
          UPDATE ai_autopilot_approvals aaa
          SET    tenant_id = aar.tenant_id
          FROM   ai_autopilot_runs aar
          WHERE  aaa.run_id = aar.id
            AND  aaa.tenant_id IS NULL
      ");

      // 2. Limpar aprovações irrecuperáveis (run deletada ou run sem tenant_id)
      DB::statement("
          DELETE FROM ai_autopilot_approvals
          WHERE  tenant_id IS NULL
      ");

      // 3. Aplicar NOT NULL constraint
      DB::statement("
          ALTER TABLE ai_autopilot_approvals
          ALTER COLUMN tenant_id SET NOT NULL
      ");
  }
  ```

  _Migration compensatória `down()` (reversível):_
  ```php
  public function down(): void
  {
      DB::statement("
          ALTER TABLE ai_autopilot_approvals
          ALTER COLUMN tenant_id DROP NOT NULL
      ");
  }
  ```

  _DEPOIS_
  ```sql
  -- ai_autopilot_approvals.tenant_id é NOT NULL uuid
  -- migrate:fresh via 2026_03_29 adiciona coluna sem nullable
  -- Todas as aprovações têm tenant_id preenchido
  ```

  **Riscos e mitigações:**

  | Risco | Mitigação |
  |-------|-----------|
  | Aprovações com `run_id` apontando para run já deletada | Deletadas no passo 2 — comportamento esperado e seguro |
  | Ambiente onde `2026_03_04` recriou a tabela sem nullable (coluna já é NOT NULL) | `ALTER COLUMN SET NOT NULL` em coluna já NOT NULL é no-op no PostgreSQL — sem erro |
  | Ambiente onde `2026_03_29` nunca rodou (coluna não existe) | Migration compensatória deve checar `Schema::hasColumn()` antes do backfill e do ALTER |

  **Observação sobre a guarda `Schema::hasColumn()`:**
  A migration compensatória deve envolver os passos 1–3 em:
  ```php
  if (Schema::hasColumn('ai_autopilot_approvals', 'tenant_id')) {
      // backfill + delete + ALTER
  }
  ```
  Caso a coluna não exista (ambiente que nunca rodou `2026_03_29`), a migration
  histórica `2026_03_04` ou `2026_01_01_000050` já garante NOT NULL — nada a fazer.

  **E — Evidência:**
  - [ ] `php artisan migrate:fresh --seed` (ambiente local) — zero erros
  - [ ] `php artisan migrate` (incremental, sobre DB existente) — zero erros
  - [ ] `php artisan migrate:rollback` — reverte sem erro
  - [ ] Query confirma NOT NULL no ambiente de testing:
    ```sql
    SELECT is_nullable
    FROM   information_schema.columns
    WHERE  table_name = 'ai_autopilot_approvals'
      AND  column_name = 'tenant_id';
    -- Esperado: 'NO'
    ```
  - [ ] Nenhuma linha com `tenant_id IS NULL` após migration:
    ```sql
    SELECT COUNT(*) FROM ai_autopilot_approvals WHERE tenant_id IS NULL;
    -- Esperado: 0
    ```
  - [ ] PHPStan L6 limpo (`composer gate:all`)

  **Responsável:** DBA
  **Status:** Pendente

---

## TASK-DB-003 — Teste Pest: tenant_id NOT NULL em tabelas sensíveis (opcional)

- [ ] **TASK-DB-003** ⏳

  **T — Tarefa:**
  Criar teste Pest de schema que valida, para um conjunto de tabelas críticas de
  multi-tenancy, que a coluna `tenant_id` existe e é NOT NULL. O teste roda contra
  `--database=testing` e falha se qualquer tabela listada violar a regra.

  **A — Arquivo:**

  | Arquivo | Operação |
  |---------|----------|
  | `api/tests/Feature/Schema/TenantIdNotNullTest.php` | Criar |

  **C — Comportamento:**

  _ANTES_
  ```
  Não há teste automatizado que detecte regressão de nullable em tenant_id.
  Um nullable introduzido em nova migration passa despercebido até produção.
  ```

  _DEPOIS_
  ```php
  <?php
  declare(strict_types=1);

  use Illuminate\Support\Facades\DB;

  $tenantTables = [
      'crm_contact_tags',
      'ai_autopilot_approvals',
      // Adicionar novas tabelas auditáveis aqui
  ];

  test('tenant_id é NOT NULL nas tabelas críticas de multi-tenancy', function (string $table) {
      $row = DB::selectOne("
          SELECT is_nullable
          FROM   information_schema.columns
          WHERE  table_schema = 'public'
            AND  table_name   = ?
            AND  column_name  = 'tenant_id'
      ", [$table]);

      expect($row)->not->toBeNull("Tabela '{$table}' não possui coluna tenant_id");
      expect($row->is_nullable)->toBe('NO', "tenant_id em '{$table}' deveria ser NOT NULL");
  })->with($tenantTables);
  ```

  _DEPOIS (comportamento de CI)_
  ```
  Qualquer migration futura que introduza tenant_id nullable em tabela listada
  faz o suite falhar antes de chegar à Validation — gate preventivo.
  ```

  **E — Evidência:**
  - [ ] `php artisan migrate:fresh --seed --database=testing && php artisan test --filter TenantIdNotNull` — verde
  - [ ] Teste falha intencionalmente ao adicionar tabela com nullable (smoke-test manual)
  - [ ] PHPStan L6 limpo no arquivo de teste

  **Responsável:** DBA + QA
  **Status:** Pendente (opcional mas recomendado)

---

## Revisão das Tasks (DBA + REVIEWER)

| Checkpoint | Responsável | Status |
|------------|-------------|--------|
| Migration histórica `2026_01_01_000020` corrigida (sem nullable) | DBA | aguardando |
| Migration histórica `2026_03_29_000001` corrigida (sem nullable) | DBA | aguardando |
| Migration compensatória DB-001 com `up()` + `down()` | DBA | aguardando |
| Migration compensatória DB-002 com `up()` + `down()` (guarda `hasColumn`) | DBA | aguardando |
| `migrate:fresh` local sem erros | DBA | aguardando |
| `migrate:rollback` local sem erros | DBA | aguardando |
| Queries NOT NULL confirmadas no testing | QA | aguardando |
| Teste Pest DB-003 verde | QA | aguardando |
| `composer gate:all` limpo | QA | aguardando |
| CHANGELOG atualizado após conclusão | DOC | aguardando |
| MEMORY atualizado (decisão de deletar órfãos) | DOC | aguardando |

---

## Decisão de Design — Órfãos: Deletar vs. Bloquear

Adotou-se a estratégia de **deletar** linhas irrecuperáveis (sem FK válida) em vez de
bloquear a migration, pelos seguintes motivos:

1. Uma linha de `crm_contact_tags` ou `ai_autopilot_approvals` sem contato/run pai já
   é dados corrompidos — manter seria pior do que descartar;
2. Bloquear exigiria intervenção manual por operador, introduzindo risco operacional
   e atrasando o fix em produção;
3. O volume de órfãos esperado é zero em ambiente saudável; se houver dados, indica
   ausência de `ON DELETE CASCADE` anterior — já corrigida nas migrations subsequentes.

Esta decisão deve ser registrada em `.context/DOCS/MEMORY/` na fase CONFIRM.
