# Memory: Inconsistência entre Modelo BelongsToTenant e Schema de Banco

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | ⚠️ Armadilha |
| **Data** | 2026-05-01 |
| **Autor** | DEBUG |
| **Contexto** | AiKnowledgeProcessJob falhando em produção |
| **Tags** | ai, migration, tenant, belongs-to-tenant, schema |

---

## Situação
O job `AiKnowledgeProcessJob` começou a falhar consistentemente com:
```
SQLSTATE[42703]: Undefined column: 7 ERROR: column ai_knowledge_chunk_refs.tenant_id does not exist
```
A query quebrada era um simples `DELETE` na linha 129 do job:
```php
AiKnowledgeChunkRef::where('document_id', $document->id)->delete();
```

---

## Decisão / Aprendizado
O modelo `AiKnowledgeChunkRef` usa o trait `BelongsToTenant`, que aplica o global scope `TenantScope`. Esse scope automaticamente injeta `WHERE tenant_id = ?` em **todas** as queries Eloquent. Se a tabela do banco não possui a coluna `tenant_id`, **qualquer operação Eloquent no modelo quebra**, incluindo `delete()`, `update()`, `first()`, etc.

A migration `2026_05_01_000002_add_content_hash_to_ai_knowledge_chunks.php` criou a tabela `ai_knowledge_chunk_refs` sem `tenant_id`, violando o contrato implícito do trait.

---

## Alternativas Consideradas

| Alternativa | Por que descartada |
|------------|-------------------|
| Remover `BelongsToTenant` do modelo | Viola a regra absoluta do projeto: "Every model uses BelongsToTenant trait". Além disso, a tenant isolation é necessária para segurança. |
| Manter sem `tenant_id` e usar `withoutGlobalScope` no job | Fragilidade extrema: qualquer outro código que use Eloquent com o modelo esqueceria do scope e quebraria. |

---

## Consequências

### Positivas
- Correção alinha schema e modelo, restaurando processamento de knowledge base.
- Adiciona foreign key para `platform_tenants`, garantindo integridade referencial.

### Negativas / Trade-offs
- Migration adicional necessária porque a original já havia sido executada em produção.

---

## Referências
- Migration de correção: `api/database/migrations/2026_05_01_030000_add_tenant_id_to_ai_knowledge_chunk_refs.php`
- Migration original com schema incompleto: `api/database/migrations/2026_05_01_000002_add_content_hash_to_ai_knowledge_chunks.php`
- Modelo: `api/src/Domain/Ai/Models/AiKnowledgeChunkRef.php`
- Job: `api/src/Domain/Ai/Jobs/AiKnowledgeProcessJob.php`
