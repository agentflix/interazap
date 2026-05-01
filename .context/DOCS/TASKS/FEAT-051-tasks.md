# Tasks: RAG Quality Improvements

> Decomposição T.A.C.E das tasks da feature FEAT-051

---

## Feature: RAG Quality Improvements
**ID:** FEAT-051
**Bounded Context:** AI
**Total Tasks:** 12
**Concluídas:** 0

---

## 🔄 FASE 3: DBA / BACKEND (Foundation)

### TASK-051.1 ⏳: Migration `ai_rag_query_logs`

**T — Tarefa:** Criar tabela para registrar cada query RAG executada com métricas.

**A — Arquivo:**
- `api/database/migrations/2026_05_01_000001_create_ai_rag_query_logs_table.php`

**C — Comportamento:**
```
ANTES:
- Não há registro de queries RAG executadas
- Impossível medir p95 de latência, taxa de zero-results ou score médio

DEPOIS:
- Tabela `ai_rag_query_logs` armazena:
  - id (uuid PK)
  - tenant_id (uuid FK → platform_tenants, indexed)
  - query_hash (string 64) — SHA-256 da query normalizada
  - query_length (int) — comprimento original em chars
  - mode (string 16) — 'vector' | 'hybrid'
  - results_count (int)
  - top_score (decimal 5,4 nullable)
  - avg_score (decimal 5,4 nullable)
  - latency_ms (int)
  - has_results (boolean) — false se results_count == 0
  - created_at (timestamp, indexed)
- Índice composto (tenant_id, created_at) para agregações por janela
- Índice (has_results, created_at) para taxa de falha
```

**E — Evidência:**
- [ ] `php artisan migrate` cria tabela sem erro
- [ ] Estrutura validada via `\Schema::getColumnListing('ai_rag_query_logs')`
- [ ] Índices verificados via `\DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'ai_rag_query_logs'")`
- [ ] Rollback com `migrate:rollback` funciona limpo

**Status:** ⏳ Pendente

---

### TASK-051.2 ⏳: Config `ai.rag.*`

**T — Tarefa:** Adicionar novas chaves de configuração para tuning de retrieval.

**A — Arquivo:**
- `api/config/ai.php`
- `api/.env.example`

**C — Comportamento:**
```
ANTES:
- Pesos híbridos hardcoded em AiRagService (0.6 / 0.4)
- ef_search não configurado (default pgvector = 40)
- Expansão de contexto inexistente

DEPOIS:
- config('ai.rag.ef_search') → env RAG_EF_SEARCH (default 100)
- config('ai.rag.vector_weight') → env RAG_VECTOR_WEIGHT (default 0.6)
- config('ai.rag.keyword_weight') → env RAG_KEYWORD_WEIGHT (default 0.4)
- config('ai.rag.expand_neighbors') → env RAG_EXPAND_NEIGHBORS (default true)
- config('ai.rag.neighbor_window') → env RAG_NEIGHBOR_WINDOW (default 1)
- Validação no boot: vector_weight + keyword_weight devem somar 1.0
```

**E — Evidência:**
- [ ] `config('ai.rag.ef_search')` retorna 100 sem env definida
- [ ] `.env.example` documenta as 5 variáveis com comentários
- [ ] Teste unitário valida soma dos pesos = 1.0

**Status:** ⏳ Pendente

---

### TASK-051.3 ⏳: HNSW `ef_search` nas queries

**T — Tarefa:** Aplicar `SET LOCAL hnsw.ef_search` em toda query de busca vetorial.

**A — Arquivo:**
- `api/src/Domain/Ai/Services/AiRagService.php`

**C — Comportamento:**
```
ANTES:
- DB::select() executa query pgvector com ef_search default (40)
- Recall pode estar subótimo em datasets grandes

DEPOIS:
- Antes da query principal, executa em mesma transação:
  DB::statement('SET LOCAL hnsw.ef_search = ?', [config('ai.rag.ef_search')])
- Encapsulado em DB::transaction() para garantir LOCAL scope
- Aplicado para modo VECTOR e HYBRID
```

**E — Evidência:**
- [ ] Teste unitário com query log spy confirma `SET LOCAL hnsw.ef_search` antes da SELECT
- [ ] Teste de regressão: busca ainda retorna mesmos resultados em fixtures
- [ ] Logs de slow query em ambiente local não mostram degradação

**Status:** ⏳ Pendente

---

### TASK-051.4 ⏳: Pesos híbridos via config

**T — Tarefa:** Substituir constantes `0.6` e `0.4` por leitura de config.

**A — Arquivo:**
- `api/src/Domain/Ai/Services/AiRagService.php`

**C — Comportamento:**
```
ANTES:
- SQL string contém literais 0.6 e 0.4:
  (0.6 * vector_score) + (0.4 * keyword_score)

DEPOIS:
- Pesos lidos de config('ai.rag.vector_weight') e config('ai.rag.keyword_weight')
- Bind como parâmetros nomeados na query (não interpolar string)
- Validação no construtor do service: pesos somam 1.0 (tolerância 0.001)
```

**E — Evidência:**
- [ ] Teste unitário com config mock (0.7/0.3) verifica SQL gerado
- [ ] Teste com config inválido (0.5/0.6) lança InvalidArgumentException
- [ ] Busca em modo HYBRID retorna mesmos resultados com config default (0.6/0.4)

**Status:** ⏳ Pendente

---

### TASK-051.5 ⏳: Contexto expandido (chunks vizinhos)

**T — Tarefa:** Após retornar top-N chunks, buscar chunks `chunk_index ± window` do mesmo documento.

**A — Arquivo:**
- `api/src/Domain/Ai/Services/AiRagService.php`
- `api/src/Domain/Ai/DTOs/KnowledgeSearchResultDTO.php` (adicionar campo `is_neighbor: bool`)

**C — Comportamento:**
```
ANTES:
- Search retorna exatamente N chunks (top-N por score)
- LLM recebe fragmentos sem contexto adjacente

DEPOIS:
- Quando config('ai.rag.expand_neighbors') = true:
  1. Busca top-N chunks por score (existente)
  2. Para cada chunk retornado, busca SELECT * FROM ai_knowledge_chunks
     WHERE document_id = ? AND chunk_index BETWEEN ?-W AND ?+W
  3. Mescla resultados deduplicando por chunk.id
  4. Marca vizinhos com is_neighbor=true e score=null
  5. Ordena por (document_id, chunk_index) ASC para preservar fluxo de leitura
- getContextForLLM(): formata vizinhos sem mostrar score (apenas "[continuação]")
```

**E — Evidência:**
- [ ] Teste integração: documento com 10 chunks, busca retorna chunk 5 → output inclui chunks 4,5,6
- [ ] Vizinhos não duplicados quando 2 resultados originais são adjacentes
- [ ] Com `expand_neighbors=false` comportamento idêntico ao atual
- [ ] DTO serializa `is_neighbor` apenas quando true

**Status:** ⏳ Pendente

---

### TASK-051.6 ⏳: Logging de queries RAG

**T — Tarefa:** Cada chamada a `AiRagService::search` grava 1 registro em `ai_rag_query_logs`.

**A — Arquivo:**
- `api/src/Domain/Ai/Services/AiRagService.php`
- `api/src/Domain/Ai/Models/AiRagQueryLog.php` (novo)
- `api/src/Domain/Ai/Services/AiRagQueryLogger.php` (novo)

**C — Comportamento:**
```
ANTES:
- Buscas RAG executam silenciosamente
- Sem dados para análise de qualidade

DEPOIS:
- AiRagService.search() envolto em try/finally:
  - Marca microtime no início
  - Após query, calcula: results_count, top_score, avg_score, latency_ms
  - Chama AiRagQueryLogger.log(...) — não-bloqueante via dispatch async opcional
- AiRagQueryLogger:
  - Normaliza query: lowercase + trim + collapse whitespace
  - Hash SHA-256 (apenas hash, nunca a query original — privacidade)
  - Insert em ai_rag_query_logs
  - Falha silenciosa: log error, NÃO propaga exception (não pode quebrar busca)
- Model AiRagQueryLog usa BelongsToTenant trait
```

**E — Evidência:**
- [ ] Teste integração: chamar search → 1 row criada com campos corretos
- [ ] Teste: query "como devolver?" e "  Como Devolver?  " geram mesmo query_hash
- [ ] Teste: erro no insert do log NÃO propaga para o caller (busca ainda retorna)
- [ ] Query original NUNCA aparece em logs ou banco (apenas hash)
- [ ] Performance: overhead < 5ms por busca (medido em teste)

**Status:** ⏳ Pendente

---

## 🔄 FASE 3: BACKEND (Quality)

### TASK-051.7 ⏳: Chunking type-aware

**T — Tarefa:** Refatorar `AiChunkingService` para usar Strategy pattern por tipo de documento.

**A — Arquivo:**
- `api/src/Domain/Ai/Services/AiChunkingService.php` (refactor)
- `api/src/Domain/Ai/Services/Chunkers/ChunkerStrategyInterface.php` (novo)
- `api/src/Domain/Ai/Services/Chunkers/DefaultChunker.php` (novo — extrai lógica atual)
- `api/src/Domain/Ai/Services/Chunkers/MarkdownChunker.php` (novo)
- `api/src/Domain/Ai/Services/Chunkers/CsvChunker.php` (novo)

**C — Comportamento:**
```
ANTES:
- AiChunkingService.chunk(text) aplica mesmo algoritmo para todos os tipos
- Markdown perde estrutura de headings (cortes no meio de seções)
- CSV perde cabeçalho após primeiro chunk

DEPOIS:
- AiChunkingService recebe AiDocumentType no chunk(text, type)
- Resolve strategy via match():
  - MARKDOWN → MarkdownChunker (split por ##/###, agrega até 500 tokens)
  - CSV → CsvChunker (preserva header em todos os chunks, agrupa N rows)
  - default → DefaultChunker (lógica atual de paragrafo+sentença)
- MarkdownChunker:
  - Identifica headings level 2/3 como pontos de quebra preferenciais
  - Não quebra dentro de bloco de código (```)
  - Mantém heading parent no início do chunk filho
- CsvChunker:
  - Detecta header (primeira linha)
  - Chunks contêm header + N rows até 500 tokens
  - Cada chunk auto-suficiente (LLM não precisa do chunk anterior para entender)
- AiKnowledgeProcessJob passa $document->file_type para chunk()
```

**E — Evidência:**
- [ ] Teste MarkdownChunker: doc com `# A`, `## B`, `## C` gera ≥ 2 chunks separados em B/C
- [ ] Teste MarkdownChunker: bloco ``` ``` ``` não é dividido
- [ ] Teste CsvChunker: header `id,name,email` aparece em chunk[0] e chunk[1]
- [ ] Teste regressão: tipos não-especializados (txt/json/pdf) usam DefaultChunker e produzem output idêntico ao atual
- [ ] AiKnowledgeProcessJob passa file_type corretamente

**Status:** ⏳ Pendente

---

### TASK-051.8 ⏳: Deduplicação de chunks

**T — Tarefa:** Adicionar `content_hash` e evitar inserir chunks duplicados por tenant.

**A — Arquivo:**
- `api/database/migrations/2026_05_01_000002_add_content_hash_to_ai_knowledge_chunks.php` (novo)
- `api/src/Domain/Ai/Models/AiKnowledgeChunk.php`
- `api/src/Domain/Ai/Jobs/AiKnowledgeProcessJob.php`

**C — Comportamento:**
```
ANTES:
- Doc1 e Doc2 com parágrafo idêntico geram 2 chunks idênticos no banco
- Busca retorna o mesmo conteúdo 2x ocupando slots no top-N

DEPOIS:
- Coluna content_hash CHAR(64) NOT NULL em ai_knowledge_chunks
- Backfill na migration: UPDATE com encode(sha256(content::bytea), 'hex')
- Índice composto (tenant_id, content_hash)
- AiKnowledgeProcessJob, antes do INSERT:
  1. Calcula hash do content
  2. SELECT id FROM ai_knowledge_chunks WHERE tenant_id=? AND content_hash=? LIMIT 1
  3. Se existe: cria registro de "alias" via tabela ai_knowledge_chunk_refs
     (document_id, chunk_id_referenced, chunk_index) — links chunks compartilhados
  4. Se não existe: insert normal com hash + embedding
- AiRagService.search inclui chunks via JOIN com ai_knowledge_chunk_refs
  (todos os documentos que apontam para o chunk são listados como source)
```

**E — Evidência:**
- [ ] Teste integração: upload de 2 docs com paragrafo idêntico → 1 chunk + 1 ref
- [ ] Busca retorna o chunk único, mas indica ambos os documentos como source
- [ ] Backfill migration funciona em base com chunks pré-existentes
- [ ] Performance: query com JOIN não excede +20ms vs busca atual (em fixture com 1k chunks)

**Status:** ⏳ Pendente

---

### TASK-051.9 ⏳: Filtros faceted na busca

**T — Tarefa:** Adicionar filtros opcionais em `SearchKnowledgeRequest` e propagar para SQL.

**A — Arquivo:**
- `api/src/Domain/Ai/Http/Requests/SearchKnowledgeRequest.php`
- `api/src/Domain/Ai/Services/AiRagService.php`
- `api/src/Domain/Ai/Contracts/AiRagServiceInterface.php`
- `api/src/Domain/Ai/DTOs/KnowledgeSearchFiltersDTO.php` (novo)

**C — Comportamento:**
```
ANTES:
- Busca filtra apenas tenant_id, is_active, status=ready
- Não há como restringir por tipo de doc, IDs ou data

DEPOIS:
- KnowledgeSearchFiltersDTO com:
  - documentIds: ?array<string>
  - fileTypes: ?array<AiDocumentType>
  - createdAfter: ?Carbon
  - createdBefore: ?Carbon
- SearchKnowledgeRequest valida (nullable, sometimes):
  - document_ids: array, cada item uuid existente do tenant
  - file_types: array, cada item enum AiDocumentType
  - created_after, created_before: date format ISO 8601
- AiRagService.search aceita ?KnowledgeSearchFiltersDTO
- SQL adiciona WHERE condicionais via query builder (não interpolação)
- Filtros aplicados a ambos modos (vector e hybrid)
```

**E — Evidência:**
- [ ] Teste: search com `file_types: ['pdf']` retorna apenas chunks de docs PDF
- [ ] Teste: search com `document_ids: [uuid]` retorna chunks apenas daquele doc
- [ ] Teste: combinação `file_types + created_after` aplica AND corretamente
- [ ] Validação: `document_ids` de outro tenant retorna 422
- [ ] Sem filtros: comportamento idêntico ao atual

**Status:** ⏳ Pendente

---

### TASK-051.10 ⏳: Endpoint `GET /ai/knowledge/rag-stats`

**T — Tarefa:** Endpoint admin para visualizar métricas agregadas dos logs.

**A — Arquivo:**
- `api/src/Domain/Ai/Http/Controllers/AiKnowledgeController.php`
- `api/src/Domain/Ai/Actions/Rag/GetRagStatsAction.php` (novo)
- `api/src/Domain/Ai/DTOs/RagStatsDTO.php` (novo)
- `api/src/Domain/Ai/Http/Resources/RagStatsResource.php` (novo)
- `api/routes/api.php`

**C — Comportamento:**
```
ANTES:
- Não há visão das queries executadas

DEPOIS:
- GET /ai/knowledge/rag-stats?days=7 (default 7, max 90)
- Auth: 'ai.autopilots.manage' + tenant scope automático
- Rate limit: 30/min
- GetRagStatsAction agrega via SQL:
  - total_queries (count)
  - zero_results_rate (count where has_results=false / total)
  - p50_latency_ms, p95_latency_ms, p99_latency_ms (percentile_cont)
  - avg_top_score, avg_results_count
  - mode_distribution: {'vector': N, 'hybrid': M}
  - top_query_hashes: top 10 queries mais frequentes (apenas hash + count)
- DTO/Resource estruturados, sem expor queries originais
```

**E — Evidência:**
- [ ] Teste E2E: popular logs fake → endpoint retorna agregações corretas
- [ ] `days=0` ou `days=91` retorna 422
- [ ] User sem permissão recebe 403
- [ ] Tenant A não vê dados de Tenant B
- [ ] Resposta < 200ms para 10k logs em fixture

**Status:** ⏳ Pendente

---

## 🔄 FASE 4: QA

### TASK-051.11 ⏳: Suite de testes Pest

**T — Tarefa:** Garantir cobertura de testes em todas as melhorias.

**A — Arquivo:**
- `api/tests/Unit/Domain/Ai/Services/AiRagServiceTest.php`
- `api/tests/Unit/Domain/Ai/Services/AiChunkingServiceTest.php`
- `api/tests/Unit/Domain/Ai/Services/Chunkers/MarkdownChunkerTest.php` (novo)
- `api/tests/Unit/Domain/Ai/Services/Chunkers/CsvChunkerTest.php` (novo)
- `api/tests/Feature/Domain/Ai/RagSearchTest.php` (novo)
- `api/tests/Feature/Domain/Ai/RagStatsEndpointTest.php` (novo)

**C — Comportamento:**
```
ANTES:
- Cobertura existente em AiRagServiceTest e AiChunkingServiceTest

DEPOIS:
- Cobertura ≥ 80% nos services modificados
- Cenários adicionais cobertos:
  - ef_search aplicado nas queries
  - pesos híbridos lidos de config
  - contexto expandido (com e sem)
  - logging de queries (sucesso e falha)
  - chunking por tipo (markdown, csv)
  - dedup de chunks
  - filtros faceted (cada combinação)
  - endpoint rag-stats (auth, validação, agregação)
- CI gate: `./vendor/bin/pest --coverage --min=80` em paths Ai/Services/*
```

**E — Evidência:**
- [ ] `./vendor/bin/pest tests/Unit/Domain/Ai` → 100% verde
- [ ] `./vendor/bin/pest tests/Feature/Domain/Ai` → 100% verde
- [ ] Coverage ≥ 80% em `src/Domain/Ai/Services/AiRagService.php`
- [ ] Coverage ≥ 80% em `src/Domain/Ai/Services/AiChunkingService.php` e chunkers

**Status:** ⏳ Pendente

---

## 🔄 FASE 5: DOC

### TASK-051.12 ⏳: CHANGELOG + MEMORY

**T — Tarefa:** Documentar mudanças e decisões tomadas.

**A — Arquivo:**
- `.context/DOCS/CHANGELOG/2026-05-XX.md` (data da conclusão)
- `.context/DOCS/MEMORY/2026-05-XX-rag-quality-improvements.md`
- `.context/ARCHITECTURE/project-state.yaml`

**C — Comportamento:**
```
ANTES:
- Decisões de retrieval (pesos, ef_search, dedup) sem rastro em MEMORY

DEPOIS:
- CHANGELOG factual:
  - Tabela ai_rag_query_logs criada
  - Configs ai.rag.* expostas
  - Strategy pattern em chunking
  - Endpoint rag-stats adicionado
- MEMORY com decisões e trade-offs:
  - Por que ef_search=100 (default ajustado)
  - Por que pesos 60/40 mantidos como default
  - Por que dedup via tabela de refs vs delete (preserva rastreabilidade)
  - Por que NÃO incluímos reranking nesta feature
- project-state.yaml atualizado com FEAT-051 = done
```

**E — Evidência:**
- [ ] Arquivos CHANGELOG/MEMORY criados seguindo templates
- [ ] project-state.yaml atualizado e validado (yaml lint)
- [ ] Links cruzados entre MEMORY ↔ feature doc ↔ tasks file

**Status:** ⏳ Pendente

---

## Revisão de Tasks

| Task | Status | Camada | Validada por | Data |
|------|--------|--------|--------------|------|
| TASK-051.1 | ⏳ | DBA | - | - |
| TASK-051.2 | ⏳ | BACKEND | - | - |
| TASK-051.3 | ⏳ | BACKEND | - | - |
| TASK-051.4 | ⏳ | BACKEND | - | - |
| TASK-051.5 | ⏳ | BACKEND | - | - |
| TASK-051.6 | ⏳ | BACKEND | - | - |
| TASK-051.7 | ⏳ | BACKEND | - | - |
| TASK-051.8 | ⏳ | DBA + BACKEND | - | - |
| TASK-051.9 | ⏳ | BACKEND | - | - |
| TASK-051.10 | ⏳ | BACKEND | - | - |
| TASK-051.11 | ⏳ | QA | - | - |
| TASK-051.12 | ⏳ | DOC | - | - |

---

## Ordem de Execução Recomendada

```
PR 1 — Foundation & Tuning (baixo risco)
├── TASK-051.1  Migration ai_rag_query_logs
├── TASK-051.2  Config ai.rag.*
├── TASK-051.3  ef_search nas queries
└── TASK-051.4  Pesos híbridos via config

PR 2 — Observabilidade & Contexto
├── TASK-051.5  Contexto expandido
└── TASK-051.6  Logging de queries

PR 3 — Chunking & Dedup
├── TASK-051.7  Chunking type-aware
└── TASK-051.8  Deduplicação por hash

PR 4 — Filtros & Stats
├── TASK-051.9   Filtros faceted
├── TASK-051.10  Endpoint rag-stats
├── TASK-051.11  Suite Pest completa
└── TASK-051.12  CHANGELOG + MEMORY
```

---

## Progresso

- [0/12] Tasks concluídas
- [ ] Feature completa
