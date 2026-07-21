# Orchestrator Context Model

Modelo obrigatório de handoff de contexto.
ORCHESTRATOR DEVE preencher este modelo antes de invocar qualquer subagent.
Nunca invoque um subagent sem passar contexto estruturado.

---

## Template de Handoff

```markdown
## Contexto para @[AGENT]

### Identidade da Task (T.A.C.E)

**T — Tarefa:**
[O QUE fazer — descrição exata da task, copiada do arquivo de tasks]

**A — Arquivo(s):**
[ONDE fazer — paths exatos dos arquivos a criar/modificar]

**C — Comportamento:**
ANTES: [estado atual]
DEPOIS: [estado esperado]

**E — Evidência:**
- [ ] [Critério verificável 1]
- [ ] [Critério verificável 2]
- [ ] [Critério verificável N]

---

### Contexto do Projeto

**Stack:**
- Backend: [BACKEND_LANG + BACKEND_FRAMEWORK]
- Frontend: [FRONTEND_LANG + FRONTEND_FRAMEWORK]
- Database: [DATABASE]
- Arquitetura: [ARCHITECTURE]

**Camadas relevantes para esta task:**
[Apenas as camadas que o subagent vai tocar]

**Módulos envolvidos:**
[Lista dos bounded contexts/módulos afetados]

**Regras invioláveis para esta task:**
- [Regra 1 — ex: Domain Layer não importa Laravel]
- [Regra 2]

---

### Estado PREVC

**Feature:** [nome da feature — path: .context/DOCS/FEATURES/[feature].md]
**Task ID:** [TASK-X.Y.Z]
**Fase PREVC:** [EXECUTION / PLANNING / REVIEW]
**Tasks concluídas antes desta:**
- [TASK-X.Y.Z] ✅ [descrição curta]
- [TASK-X.Y.Z] ✅ [descrição curta]

**Tasks dependentes (não iniciar ainda):**
- [TASK-X.Y.Z] — depende desta

---

### Permissões de Arquivo

O subagent PODE tocar apenas:
- [path/to/file.ext] — [motivo]
- [path/to/dir/] — [motivo]

O subagent NÃO DEVE tocar:
- [path/sensível] — [motivo]

---

### Output Esperado

**Entregável:** [código / migration / componente / etc.]
**Critério de conclusão:** [como saber que está pronto — seção E completa]
**Formato de retorno:** [o que o subagent deve responder ao ORCHESTRATOR]

---

### Registros ao Concluir (fase CONFIRM)

[Preencher apenas se este subagent também fará CONFIRM]

**CHANGELOG:** `.context/DOCS/CHANGELOG/[DATA].md`
Formato: `- [HH:MM] [TIPO] [[escopo]]: [descrição]`

**MEMORY:** `.context/DOCS/MEMORY/[DATA]-[titulo].md`
Registrar se: decisão técnica tomada / armadilha encontrada / padrão novo

**project-state.yaml:** `.context/ARCHITECTURE/project-state.yaml`
Incrementar: tasks_completed, decrementar tasks_in_progress
```

---

## Regras de Uso

1. **Nunca omitir campos obrigatórios** — T.A.C.E, Stack, Fase PREVC, Permissões de Arquivo
2. **Campos condicionais** — "Registros ao Concluir" só se o subagent fará CONFIRM
3. **Permissões de arquivo** — sempre listar o que pode E o que não pode tocar
4. **Tasks dependentes** — sempre informar para o subagent não iniciar trabalho além do escopo
5. **Regras invioláveis** — extrair de `.context/ARCHITECTURE/project-brain.yaml`

## Exemplo Preenchido

```markdown
## Contexto para @BACKEND

### Identidade da Task (T.A.C.E)

**T — Tarefa:**
Criar Entity ImportJob no Domain Layer do módulo Catalog

**A — Arquivo(s):**
- `src/Catalog/Domain/Entities/ImportJob.php` (criar)
- `src/Catalog/Domain/Repositories/ImportJobRepositoryInterface.php` (criar)

**C — Comportamento:**
ANTES: ImportJob não existe no domínio
DEPOIS: Entity com campos id, status, file_path, tenant_id, created_at; Repository interface com métodos save(), findById(), findByTenant()

**E — Evidência:**
- [ ] Arquivo ImportJob.php criado em Domain/Entities/
- [ ] Sem imports do Laravel na Entity
- [ ] PHPUnit: php artisan test --filter ImportJobTest passando
- [ ] PHPStan nível 8: 0 erros

---

### Contexto do Projeto

**Stack:** PHP 8.3 + Laravel 12 | TypeScript + Angular 18 | PostgreSQL 16 | DDD

**Camadas relevantes:** Domain Layer (Entity + Interface)

**Módulos:** Catalog (Bounded Context)

**Regras invioláveis:**
- Domain Layer NUNCA importa classes do Laravel
- Entities são PHP puro, sem anotações de ORM
- Value Objects imutáveis

---

### Estado PREVC

**Feature:** importacao-csv-produtos (.context/DOCS/FEATURES/importacao-csv-produtos.md)
**Task ID:** TASK-3.2.1
**Fase PREVC:** EXECUTION
**Tasks concluídas:** TASK-3.1.1 ✅ Migration import_jobs criada

**Tasks dependentes:** TASK-3.3.1 — CsvParserService (depende desta Entity)

---

### Permissões de Arquivo

PODE tocar:
- `src/Catalog/Domain/Entities/` — criação da Entity
- `src/Catalog/Domain/Repositories/` — criação da Interface
- `tests/Unit/Catalog/Domain/` — testes unitários

NÃO DEVE tocar:
- `src/Catalog/Infrastructure/` — responsabilidade do DBA
- `src/Catalog/Application/` — próxima task

---

### Output Esperado

**Entregável:** Entity ImportJob.php + Interface ImportJobRepositoryInterface.php + testes unitários
**Critério:** PHPStan 0 erros + PHPUnit passando
**Retorno:** Confirme os arquivos criados e cole output dos testes
```
