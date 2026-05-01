# Memory: RAG Quality Improvements — Decisões e Trade-offs

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | 🧠 Decisão / 📚 Aprendizado / ⚠️ Armadilha |
| **Data** | 2026-04-30 |
| **Autor** | DOC (consolidado de FEAT-051) |
| **Contexto** | FEAT-051 — Melhorias de qualidade no pipeline RAG (observability, tuning, chunking, dedup, filtros, stats) |
| **Tags** | rag, ai, postgres, pgvector, hnsw, chunking, deduplication, privacy, observability |

---

## Situação
> O que estava acontecendo? Qual o contexto?

O pipeline RAG do módulo AI operava com múltiplas limitações conhecidas:
- Não havia visibilidade sobre latência, taxa de zero-resultados ou distribuição de modos de busca
- Pesos híbridos (vector + keyword) estavam hardcoded em 0.6/0.4 sem possibilidade de tuning
- Chunking era monolítico, ignorando tipo de documento (Markdown, CSV, etc.)
- Chunks duplicados entre documentos não eram deduplicados, desperdiçando espaço e processamento
- `ef_search` do pgvector usava o default 40, prejudicando recall em datasets grandes
- Faltavam filtros facetados (por documento, tipo de arquivo, data)
- O texto original das queries poderia vazar em logs, criando risco de privacidade

---

## Decisão / Aprendizado
> O que foi decidido ou aprendido?

### 1. Observability via `ai_rag_query_logs`
Criada tabela dedicada para métricas RAG. O texto original da query **NUNCA** é armazenado — apenas o hash SHA-256, garantindo compliance de privacidade.

### 2. Tunabilidade de `ef_search`
Configurável via `ai.rag.ef_search`. Default elevado para **100** (vs. 40 do pgvector) para melhorar recall em grandes volumes de chunks, com custo controlado de latency.

### 3. Pesos híbridos externalizados
Movidos de hardcoded para `ai.rag.vector_weight` / `ai.rag.keyword_weight`. Default mantido em 60/40 por falta de benchmark interno; ajuste será data-driven.

### 4. Chunking type-aware (Strategy Pattern)
Adotado padrão Strategy com interface `ChunkerStrategyInterface`. Implementações:
- `DefaultChunker`: genérico, por parágrafos
- `MarkdownChunker`: respeita headers e blocos de código
- `CsvChunker`: por linhas/registros

### 5. Deduplicação com ref table
Em vez de deletar duplicatas, criada tabela `ai_knowledge_chunk_refs` ligando chunks aos documentos que os contêm. Isso preserva rastreabilidade e evita perda de referência.

### 6. Faceted search
DTO `KnowledgeSearchFiltersDTO` centraliza filtros: `document_ids`, `file_types`, `created_after`, `created_before`.

### 7. Endpoint `rag-stats`
Action `GetRagStatsAction` calcula p50/p95/p99 de latência, zero-results rate e distribuição de modo (vector/hybrid), expondo via `RagStatsResource`.

### 8. Bug de parameter binding no PostgreSQL
Aprendizado crítico: `SET LOCAL hnsw.ef_search = ?` **não aceita parameter binding** do PDO/Eloquent. Foi necessário usar interpolação de string (com sanitização do valor inteiro).

---

## Alternativas Consideradas
> O que foi descartado e por quê?

| Alternativa | Por que descartada |
|------------|-------------------|
| Deletar chunks duplicados ao invés de ref table | Perderia rastreabilidade de quais documentos compartilham o mesmo conteúdo; dificultaria auditoria |
| Armazenar texto original da query para debugging | Violação direta de privacidade; hash SHA-256 é suficiente para correlação e análise de padrões |
| Ajustar pesos híbridos para 50/50 ou 70/30 imediatamente | Sem dados de benchmark ou A/B test; decisão adiada até acumular métricas reais do `rag-stats` |
| Incluir reranking nesta feature | Escopo muito grande; reranking será avaliado após 7 dias de dados de query logs (planejado para FEAT-052) |
| Reindexar todos os documentos antigos automaticamente para novos chunkers | Alto custo computacional; decisão deixada como manual/optional para não bloquear o release |

---

## Consequências
> O que muda por causa disso?

### Positivas
- Pipeline RAG agora é mensurável e tunável sem deploy de código
- Recall melhorado em datasets grandes graças ao `ef_search` elevado
- Chunking respeita estrutura do documento, reduzindo contexto quebrado
- Deduplicação economiza espaço e processamento de embedding
- Filtros facetados permitem buscas precisas por conjunto de documentos
- Privacidade garantida: queries originais nunca persistem

### Negativas / Trade-offs
- Latência de busca vetorial pode aumentar levemente com `ef_search=100` (trade-off recall vs. speed)
- Tabela `ai_rag_query_logs` crescerá rapidamente; avaliar TTL/purging em FEAT-052
- `ai_knowledge_chunk_refs` adiciona JOIN em consultas de deduplicação
- Reindexação manual de documentos antigos pode deixar chunks legados com estratégia antiga
- Interpolação de string no `SET LOCAL` requer validação rigorosa do valor para evitar SQL injection

---

## Referências
- Feature: `.context/DOCS/FEATURES/FEAT-051.md` (se existir)
- Tasks: `.context/DOCS/TASKS/TASK-051.*.md`
- CHANGELOG: `.context/DOCS/CHANGELOG/2026-04-30.md`
- Código:
  - `api/src/Domain/Ai/Services/AiRagService.php`
  - `api/src/Domain/Ai/Services/AiChunkingService.php`
  - `api/src/Domain/Ai/Services/AiRagQueryLogger.php`
  - `api/src/Domain/Ai/Actions/Rag/GetRagStatsAction.php`
