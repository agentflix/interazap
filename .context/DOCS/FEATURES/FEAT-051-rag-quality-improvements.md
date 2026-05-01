# Feature: RAG Quality Improvements — Observabilidade, Tuning e Retrieval

> Feature doc — Refatoração e melhoria do módulo RAG com foco em qualidade de retrieval e observabilidade

---

## Metadados

| Campo | Valor |
|-------|-------|
| **ID** | FEAT-051 |
| **Nome** | RAG Quality Improvements |
| **Bounded Context** | AI |
| **Complexidade** | M |
| **Prioridade** | Should |
| **Status** | 🟡 Em Planning |
| **Criada em** | 2026-04-30 |
| **Última atualização** | 2026-04-30 |

---

## Resumo

Conjunto de melhorias incrementais no módulo RAG (`api/src/Domain/Ai`) focadas em três eixos:

1. **Observabilidade** — log estruturado de queries para medir qualidade real de retrieval
2. **Tuning de retrieval** — `ef_search` HNSW, pesos híbridos configuráveis, contexto expandido
3. **Qualidade de chunking** — chunking por tipo de documento, deduplicação por hash
4. **Filtros de busca** — facets opcionais para refinar resultado (file_type, date range, document_ids)

A feature **não introduz novas dependências externas** (sem reranker, sem cache semântico) — esses ficam para FEAT-052 após termos dados do log.

---

## Objetivo

Hoje o RAG funciona, mas estamos otimizando no escuro: não sabemos quais queries falham, qual o score médio dos chunks retornados, ou se 60/40 (vector/keyword) é o melhor peso. Esta feature instrumenta o sistema e aplica os ajustes de baixo risco que já sabemos que melhoram retrieval, sem exigir benchmarks prévios.

---

## Escopo

### Dentro do Escopo ✅

- [ ] Tabela `ai_rag_query_logs` com métricas por query (latência, score, count, mode)
- [ ] `SET LOCAL hnsw.ef_search` configurável nas queries de busca
- [ ] Pesos híbridos (vector/keyword) movidos para `config/ai.php`
- [ ] Contexto expandido: ao retornar chunks, incluir vizinhos (`chunk_index ± 1`)
- [ ] Chunking type-aware para Markdown (respeitar headings) e CSV (preservar header)
- [ ] Deduplicação de chunks por hash SHA-256 do conteúdo (por tenant)
- [ ] Filtros faceted na busca: `document_ids[]`, `file_types[]`, `created_after`
- [ ] Endpoint admin `GET /ai/knowledge/rag-stats` para visualizar métricas agregadas
- [ ] Testes Pest cobrindo cada melhoria isoladamente

### Fora do Escopo ❌

- Reranking via LLM (FEAT-052)
- Cache semântico de queries (FEAT-052)
- A/B test de dimensões 512 vs 1536 (research, não implementação)
- URL re-fetch agendado (FEAT-053)
- Bulk upload (FEAT-053)
- Dashboard frontend para `rag-stats` (entrega só endpoint, dashboard fica para depois)

---

## Dependências

| Feature/Sistema | Tipo | Status | Blocker |
|-----------------|------|--------|---------|
| pgvector ext | Necessária | Pronta | Não |
| `ai_knowledge_chunks` | Necessária | Pronta | Não |
| Laravel Horizon | Necessária | Pronta | Não |

---

## Critérios de Aceite

| ID | Critério | Verificável | Status |
|----|----------|-------------|--------|
| CA-001 | Toda chamada a `AiRagService::search()` grava 1 row em `ai_rag_query_logs` | Teste integração | ❌ |
| CA-002 | Query de busca aplica `SET LOCAL hnsw.ef_search` configurável via env `RAG_EF_SEARCH` | Inspeção SQL log | ❌ |
| CA-003 | Pesos híbridos lidos de `config('ai.rag.vector_weight')` e `config('ai.rag.keyword_weight')` | Teste unitário | ❌ |
| CA-004 | Search com 5 resultados retorna até 15 chunks (5 + vizinhos) ordenados por documento+índice | Teste integração | ❌ |
| CA-005 | Markdown com 3 headings `##` gera chunks separados por seção | Teste unitário do chunker | ❌ |
| CA-006 | CSV com header e 100 linhas: cada chunk inclui o header como primeira linha | Teste unitário | ❌ |
| CA-007 | Upload de 2 documentos com parágrafo idêntico gera apenas 1 chunk no banco | Teste integração | ❌ |
| CA-008 | Search com `file_types: ['pdf']` retorna apenas chunks de documentos PDF | Teste integração | ❌ |
| CA-009 | `GET /ai/knowledge/rag-stats` retorna p50/p95 latency, taxa zero-results, score médio (últimos 7d) | Teste E2E | ❌ |
| CA-010 | Cobertura mínima de 80% em `Services/AiRagService` e `Services/AiChunkingService` | `pest --coverage` | ❌ |

---

## Tasks

| Task ID | Descrição | Camada | Status |
|---------|-----------|--------|--------|
| TASK-051.1 | Migration: criar tabela `ai_rag_query_logs` | DBA | ⏳ |
| TASK-051.2 | Adicionar config `ai.rag.*` (ef_search, weights, expand_neighbors) | BACKEND | ⏳ |
| TASK-051.3 | Aplicar `SET LOCAL hnsw.ef_search` nas queries de `AiRagService` | BACKEND | ⏳ |
| TASK-051.4 | Mover pesos híbridos `0.6/0.4` para config | BACKEND | ⏳ |
| TASK-051.5 | Implementar contexto expandido (chunks vizinhos) | BACKEND | ⏳ |
| TASK-051.6 | Implementar logging de queries em `ai_rag_query_logs` | BACKEND | ⏳ |
| TASK-051.7 | Refatorar `AiChunkingService` em estratégias por tipo (`MarkdownChunker`, `CsvChunker`, `DefaultChunker`) | BACKEND | ⏳ |
| TASK-051.8 | Adicionar campo `content_hash` em `ai_knowledge_chunks` + dedup no insert | DBA + BACKEND | ⏳ |
| TASK-051.9 | Adicionar filtros faceted em `SearchKnowledgeRequest` e `AiRagService::search` | BACKEND | ⏳ |
| TASK-051.10 | Criar endpoint `GET /ai/knowledge/rag-stats` | BACKEND | ⏳ |
| TASK-051.11 | Suite Pest: cobrir todas as melhorias com testes | QA | ⏳ |
| TASK-051.12 | Atualizar CHANGELOG + MEMORY documentando decisões | DOC | ⏳ |

---

## Estratégia de Rollout

1. **TASK-051.1 a 051.4** podem ir em uma única PR (mudanças de config, baixo risco)
2. **TASK-051.5 e 051.6** em PR separada (introduzem novo comportamento — observabilidade primeiro)
3. **TASK-051.7 e 051.8** em PR dedicada (chunking + dedup, requerem reindex de docs antigos opcional)
4. **TASK-051.9 e 051.10** em PR final junto com testes

Após 7 dias com `ai_rag_query_logs` populado, avaliar se reranking (FEAT-052) é necessário com base nos dados reais.

---

## Notas

- Reindex de documentos antigos para aproveitar novo chunking é **opcional e manual** via `ReindexAllKnowledgeDocumentsCommand` existente. Não vamos forçar reindex automático para evitar custo de embeddings.
- Deduplicação só compara hash dentro do mesmo tenant — chunks idênticos entre tenants diferentes são mantidos por isolamento.
- Endpoint `rag-stats` é admin-only (`authorize('ai.autopilots.manage')`) e usa agregação SQL direta (sem cache nessa primeira versão).
