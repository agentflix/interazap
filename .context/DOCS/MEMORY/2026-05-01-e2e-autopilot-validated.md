# Memory: Módulo Autopilot validado E2E — 92/92 PASS

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | 📚 Aprendizado + ✅ Gate de Produção |
| **Data** | 2026-05-01 |
| **Autor** | DEBUG Agent |
| **Contexto** | Validação pré-produção do módulo Autopilot |
| **Tags** | autopilot, tools, e2e, permissões, produção |

---

## Situação

Antes de ir para produção, o módulo Autopilot precisava de evidência de que todas as 29 tools funcionam corretamente com banco de dados real, que a matriz de permissões bloqueia e libera as ferramentas conforme esperado, e que os serviços de infraestrutura (SnapshotResolver, ToolDispatcher, AiAutopilotRun) estão operacionais.

---

## Decisão / Aprendizado

Criada trilha E2E via `php artisan tinker` que roda contra PostgreSQL real sem mocks. 92 cenários, 5.98s de execução. Resultado: **92/92 PASS**.

### Correções identificadas durante a trilha

| Problema | Arquivo | Causa |
|----------|---------|-------|
| `tenant_code` VARCHAR(12) — valor longo demais | `setup.php` | Campo tem limite de 12 chars; reduzido para `E2EAUTO` |
| `plan_id` NOT NULL em `platform_tenants` | `setup.php` | FK obrigatória; reusa primeiro plano existente |
| `embedding_status` enum — valor `completed` inválido | `setup.php` | Enum aceita `ready`, não `completed` |
| `content_hash` NOT NULL em `ai_knowledge_chunks` | `setup.php` | FEAT-051 adicionou coluna NOT NULL; sha256 incluído no seed |
| `read_ticket` retorna `data['ticket']['id']` não `data['ticket_id']` | `test-01-chat.php` | Tool encapsula em objeto `ticket{}` |
| `get_contact_info` retorna `data['contact']['id']` | `test-02-contacts.php` | Tool encapsula em objeto `contact{}` |
| `get_negotiation_info` retorna `data['negotiation']['id']` | `test-04-negotiations.php` | Tool encapsula em objeto `negotiation{}` |
| `add_product_to_negotiation` usa `qty` não `quantity`, retorna `negotiation_product_id` | `test-04-negotiations.php` | Parâmetro e chave de retorno divergentes do assumido |
| `close_negotiation` — `$neg->status` é enum, não string | `test-04-negotiations.php` | `CRMNegotiationStatus` enum; usar `->value` |
| `notify_seller` — `seller_id` é FK para `auth_users` | `test-09-notify.php` | Constraint de integridade referencial; usa UUID real |

### O que está confirmado como sólido

- Todas as 29 tool classes instanciam via DI container sem exceção
- `getParameters()` de todas retorna array válido (schema OpenAI gerado corretamente)
- Tenant isolation: todas as tools com DB filtragem por `tenant_id`
- `ToolDispatcherService::dispatch()` sem `tenant_id` retorna `failure` sem lançar exceção
- `AutopilotRunSnapshotResolver::resolve()` tolera falhas (retorna null por campo, não explode)
- `AiAutopilotRun` transiciona `queued→running→completed` com timestamps e `output` cast correto
- `AiPermissionMatrixService`: todas as 6 roles retornam entre 13 e 29 tools

---

## Consequências

### Positivas
- Módulo Autopilot tem gate de validação reproduzível em qualquer ambiente
- Trilha documenta as chaves exatas de retorno de cada tool (essencial para o gateway parsear respostas)
- Cada execução cria tenant isolado e faz teardown completo em cascade

### Negativas / Trade-offs
- `search_knowledge` depende de pgvector e embeddings reais para retornar resultados — sem embeddings, valida apenas que não lança exceção
- `delegate_to_agent` em ambiente sem worker publica no Redis mas não completa a delegação — validado como comportamento aceitável

---

## Referências
- Scripts: `api/tests/E2E/Autopilot/`
- Runner: `api/tests/E2E/run-e2e.sh`
- Evidência: `.context/DOCS/CHANGELOG/2026-05-01-e2e-autopilot-evidence.md`
