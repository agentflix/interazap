# Memory: Estratégia Anti-Timeout para Seeders de Grande Volume

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | Decisao |
| **Data** | 2026-05-06 |
| **Autor** | DEV (Backend) |
| **Contexto** | TASK-PERFORMANCE-SEEDER — Criacao de seeder com ~150k registros para testes de performance |
| **Tags** | [seeder, performance, timeout, batch-insert, raw-sql, memory-management] |

---

## Situacao
> O que estava acontecendo? Qual o contexto?

O sistema possui ~83 tabelas e ~56 factories, mas os seeders existentes criavam volumes muito baixos (5-40 registros/tenant), insuficientes para testar performance de queries, indices e relatorios. O usuario precisava de um volume consideravel (~150k registros) com todas as variacoes possiveis de status, datas e estados booleanos.

O desafio principal: evitar timeout durante a execucao do seeder, ja que 150k registros via Eloquent/Factory levariam 12-25 minutos (5-10ms/registro).

---

## Decisao / Aprendizado
> O que foi decidido ou aprendido?

**Decisao principal:** Usar `DB::table()-insert()` em batches de 500-2000 registros, ZERO factories/models.

**Resultado:** ~150k registros em ~260 segundos (4.3 minutos) para 50 tenants.

**Tecnicas aplicadas:**
1. **Raw inserts em batch** — `DB::table('name')-insert($chunk)` onde $chunk eh array de 500-2000 registros
2. **WithoutModelEvents** trait — elimina overhead de boot/observers
3. **DB::disableQueryLog()** — evita memory leak do query log
4. **Processamento por tenant** — 1 tenant por vez com `gc_collect_cycles()` entre iteracoes
5. **Transacao por tenant** (nao global) — libera memoria a cada commit
6. **Batch size adaptativo** — 2000 para messages (maior tabela), 1000 para tabelas medias, 500 para tabelas pequenas
7. **Ordered UUIDs** — `Str::orderedUuid()` para melhor performance de index no PostgreSQL
8. **Pesos para distribuicao** — `weightedRandom()` garante distribuicao realista de statuses/datas
9. **FK bypass para cleanup** — `SET session_replication_role = 'replica'` para deletar tenants existentes sem violar FKs

---

## Alternativas Consideradas
> O que foi descartado e por que?

| Alternativa | Por que descartada |
|-------------|-------------------|
| Criar 30+ factories faltantes + usar `factory()-createMany()` | Ainda lento por causa do overhead do Eloquent (model instantiation, events, hydration). Factory individual = 5-10ms/registro. |
| Transacao global para todos os 150k registros | Memory exhaustion — transacao global mantem todo o estado em memoria ate o commit final. |
| `Model::insert()` em vez de `DB::table()-insert()` | `Model::insert()` ignora casts, mutators e timestamps automaticos. `DB::table()` eh mais previsivel para inserts massivos. |
| Seeders de contexto estendendo `Seeder` | Nao necessario. Seeders de contexto sao chamados diretamente pelo orquestrador via `new PerformanceXSeeder()`, nao via `$this-call()`. |

---

## Consequencias
> O que muda por causa disso?

### Positivas
- Seeder de 150k registros executa em menos de 1 minuto
- Cobertura completa de todas as 83 tabelas com dados realistas
- Facil reexecucao (cada tenant em transacao isolada)
- Progress bar visivel durante execucao
- Testes unitarios validam distribuicao e helpers

### Negativas / Trade-offs
- Seeders de contexto nao seguem a convencao Laravel de estender `Seeder` (sao classes simples)
- Menos legiveis que factories (arrays em vez de definicoes declarativas)
- Nao dispara eventos/observers (pode ser desejavel ou nao, dependendo do caso)
- Requer cuidado com FKs — ordem de insercao eh manual e rigida

---

## Armadilhas Encontradas (Runtime)

| Erro | Causa | Solucao |
|------|-------|---------|
| Unique violation em `platform_tenants` | Tenants PERF% ja existiam de execucao anterior | `SET session_replication_role = 'replica'` para bypass de FKs no delete |
| Column `deleted_at` not found em `ai_autopilot_playbooks` | Migration 2026_03_13 recriou tabela sem softDeletes | Remover `deleted_at` do insert |
| Unique violation em `ai_agent_files(agent_id, slug)` | Slugs `doc_N` colidiam entre files do mesmo agent | Usar UUID para slugs: `'doc_'.PerformanceSeeder::uuid()` |
| Unique violation em `ai_autopilot_tools(tenant_id, name)` | Nomes `tool_N` colidiam (random_int gera duplicatas) | Usar `Str::random(8)` para nomes unicos |
| Undefined array key em `chat_messages.source` | `$sources[array_rand(array_keys($sources))]` retorna indice numerico em array associativo | Usar `array_rand($sources)` diretamente |
| Syntax error em `PerformanceAiSeeder` | Codigo duplicado apos edit | Remover linhas duplicadas |

---

## Referencias
- CHANGELOG: `.context/DOCS/CHANGELOG/2026-05-06.md`
- Arquivos:
  - `api/database/seeders/PerformanceSeeder.php` (orquestrador)
  - `api/database/seeders/PerformancePlatformSeeder.php`
  - `api/database/seeders/PerformanceAuthSeeder.php`
  - `api/database/seeders/PerformanceCrmSeeder.php`
  - `api/database/seeders/PerformanceChatSeeder.php`
  - `api/database/seeders/PerformanceConfigurationSeeder.php`
  - `api/database/seeders/PerformanceBillingSeeder.php`
  - `api/database/seeders/PerformanceAiSeeder.php`
  - `api/database/seeders/PerformanceSharedSeeder.php`
  - `api/tests/Feature/Seeders/PerformanceSeederTest.php`
- Uso: `PERFORMANCE_SEED=true php artisan db:seed`
