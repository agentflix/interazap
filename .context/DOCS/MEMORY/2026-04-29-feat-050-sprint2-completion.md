# Memory: Implementação Sprint 2 FEAT-050 — Templates Meta + Gateway

## Metadados
| Campo | Valor |
|-------|-------|
| **Tipo** | 📚 Aprendizado |
| **Data** | 2026-04-29 |
| **Autor** | ORCHESTRATOR + BACKEND + DEV agents |
| **Contexto** | FEAT-050 — Meta WhatsApp Templates Management |
| **Tags** | meta, templates, gateway, nestjs, laravel, cache-redis |

---

## Situação
O Sprint 2 da FEAT-050 foi iniciado mas não concluído. O código existente tinha:
- Migration A1, Actions A2-A4, Rotas A6, Frontend C1-C5 já implementados
- Controller do backend incompleto (só index + store)
- Resource e Request não expunham campos Meta
- Policy sem verificação de permission
- Gateway sem endpoints de create/delete/sync e sem cache key distinta para includeAll

---

## Decisão / Aprendizado

### Backend
1. **Modelo deve ter `$fillable` explícito** — adicionados 7 novos campos (chat_instance_id, provider, external_id, language, status, rejected_reason, components_json, last_synced_at) e casts para `components_json` (array) e `last_synced_at` (datetime).
2. **Controller deve bifurcar store** — quando `provider === 'meta'`, delegar para `CreateMetaTemplateAction` que dispara o job assíncrono; quando `local`, usar a action genérica.
3. **Policy deve usar permission** — `chat.templates.manage` para create/update/delete/sync; viewAny/view permanecem abertos a qualquer usuário autenticado do tenant.

### Gateway
1. **Cache keys distintas por modo** — `meta:templates:approved:{token}` vs `meta:templates:all:{token}` para evitar poluição de cache quando `include_all=true`.
2. **Invalidação proativa** — após create/delete/sync, chamar `invalidateTemplatesCache(accessToken)` para garantir que a próxima listagem busque da Meta.
3. **WABA ID no settings_json** — o Gateway precisa de `waba_id` (do settings_json do canal) para chamar `/{wabaId}/message_templates` na Meta Graph API.

---

## Alternativas Consideradas

| Alternativa | Por que descartada |
|------------|-------------------|
| Reutilizar cache key única para listTemplates | Causaria cache hit indevido quando `include_all` muda entre chamadas |
| Fazer delete Meta síncrono no backend | Já existe action `DeleteChatMessageTemplateAction` que faz isso; o Gateway só precisa expor o endpoint |
| Usar `$guarded = []` no Model | Viol regra absoluta do AGENTS.md (`$fillable` explícito obrigatório) |

---

## Consequências

### Positivas
- Frontend admin de templates já funcional end-to-end (list, create, edit, delete, sync)
- Cache Redis do Gateway é invalidado consistentemente em writes
- Testes cobrem 100% dos novos endpoints (backend e gateway)

### Trade-offs
- PHPStan do projeto inteiro consome >128M de memória (problema pré-existente, não bloqueante)
- Gateway ChannelsController precisa de `waba_id` configurado no settings_json do canal Meta

---

## Referências
- Feature: `.context/DOCS/FEATURES/FEAT-050-meta-message-templates.md`
- Tasks: `.context/DOCS/TASKS/FEAT-050-tasks.md`
