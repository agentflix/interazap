# 0002-PRD-job-tenant-isolation

**Versão:** 1.0
**Data:** 2026-05-23
**Autor:** Rafael Silva
**Status:** [ ] Rascunho | [ ] Em revisão | [x] Aprovado

---

## Visão Geral

Corrigir 8 jobs Laravel que buscam modelos `BelongsToTenant` sem tenant scope explícito no `find()` inicial. `TenantScope` é silenciosamente ignorado em contexto de job (sem auth user, sem TenantContext), deixando queries sem filtro de tenant. Defesa em profundidade.

## Problema

Auditoria do codebase (2026-05-23) identificou que os 8 jobs abaixo fazem `::find($id)` ou `::query()->find($id)` em models com `BelongsToTenant` sem configurar `TenantContext`. Comportamento atual de `TenantScope::apply()`:

```php
// TenantScope.php — silently skips filter when no context
$tenantId = TenantContext::get();  // null no job
if ($tenantId === null) {
    $tenantId = auth()->user()?->tenant_id;  // null no job
}
if ($tenantId === null) {
    return;  // sem filtro!
}
```

Resultado: query executa `SELECT * FROM table WHERE id = ?` sem tenant_id. IDs vêm de código servidor confiável (risco atual baixo), mas viola defense-in-depth e pode ser explorado se houver vulnerability de job injection.

**Jobs afetados:**
1. `AiMediaTranscriptionJob.php:71` — `ChatMessage::find($this->messageId)` (tem `$this->tenantId` disponível)
2. `SubmitMetaTemplateJob.php:50,119` — `ChatMessageTemplate::query()->find($this->templateId)` (sem tenantId)
3. `SendNotificationJob.php:58` — `ConfigurationNotification::query()->find($this->notificationId)` (sem tenantId)
4. `AiKnowledgeProcessJob.php:97,530` — `AiKnowledgeDocument::find($this->documentId)` (sem tenantId)
5. `AiPromptGuardianJob.php:47` — `AiPromptTenant::query()->find($this->tenantPromptId)` (sem tenantId)
6. `AiRunExecutionJob.php:50` — `AiAutopilotRun::query()->findOrFail($this->runId)` (sem tenantId)
7. `DispatchAutopilotRunJob.php:223` — `ChatMessage::query()->find($messageId)` (tem `$this->tenantId`)
8. `AiRunExecutionJob.php:50` — `AiAutopilotRun::query()->findOrFail($this->runId)` (sem tenantId explícito)

**Nota:** A análise inicial de 56 achados revelou que o codebase está em boa forma. Todos os outros achados foram verificados e já estão corretamente implementados (guards, interceptors, void .catch(), JWT secrets, axios timeout, etc.).

## Solução

**Dois padrões de fix:**

**Padrão A** — Job já tem `$tenantId` no construtor: adicionar `->where('tenant_id', $this->tenantId)` no find inicial.
```php
// Antes
$message = ChatMessage::find($this->messageId);
// Depois
$message = ChatMessage::query()->where('tenant_id', $this->tenantId)->find($this->messageId);
```

**Padrão B** — Job sem `$tenantId`: adicionar param no construtor + usar no find. Atualizar dispatch sites.
```php
// Construtor: adicionar string $tenantId
// find: ->where('tenant_id', $this->tenantId)->find($id)
```

## Usuários

- **Primário:** Sistema/queue workers — operações internas isoladas por tenant.
- **Secundário:** Equipe de segurança — auditoria de tenant isolation.

## Requisitos Funcionais

1. [RF01] Todo job que processa dados de tenant deve ter `$tenantId` no construtor.
2. [RF02] Todo `find()` em model com `BelongsToTenant` em jobs deve incluir `->where('tenant_id', ...)`.
3. [RF03] Jobs não devem retornar dados de tenant errado caso `$id` pertença a outro tenant.
4. [RF04] Dispatch sites dos jobs devem passar `$tenantId` explicitamente.

## Requisitos Não-Funcionais

1. [RNF01] `composer gate:all` passa após todas as mudanças.
2. [RNF02] Nenhuma regressão em testes existentes.
3. [RNF03] Jobs continuam idempotentes e com retry correto.

## Critérios de Aceite

- [ ] `grep -n "ChatMessage::find" api/src/Domain/Ai/Jobs/AiMediaTranscriptionJob.php` mostra `where('tenant_id'`
- [ ] `grep -n "ChatMessage::query" api/src/Domain/Ai/Jobs/DispatchAutopilotRunJob.php` mostra `where('tenant_id'`
- [ ] `SubmitMetaTemplateJob` tem `$tenantId` no construtor e no find
- [ ] `SendNotificationJob` tem `$tenantId` no construtor e no find
- [ ] `AiKnowledgeProcessJob` tem `$tenantId` no construtor e no find
- [ ] `AiPromptGuardianJob` tem `$tenantId` no construtor e no find
- [ ] `AiRunExecutionJob` tem `$tenantId` no construtor e no find
- [ ] `composer gate:all` passa

## Dependências

- Nenhuma feature nova — apenas adição de tenant scoping em queries existentes

## Riscos

| Risco | Probabilidade | Impacto | Mitigação |
|---|---|---|---|
| Dispatch sites desconhecidos para jobs sem tenantId | Média | Alto | Fazer grep de todos os dispatch sites antes de modificar construtor |
| Job falhar em produção com ID de tenant incorreto (descobrir bug existente) | Baixa | Médio | Comportamento esperado; manter `if (!$model) return;` guard |
| AiKnowledgeProcessJob: `$tenantId` pode não estar disponível no dispatch | Baixa | Médio | Verificar todos os dispatch sites antes de implementar |

## Cronograma Estimado

- Planejamento: 0 dias (já feito)
- Execução: 0.5 dia (8 fixes simples + pesquisa de dispatch sites)
- Validação: 0.5 dia (gate:all + testes manuais)

## Fora de Escopo

- Refactor de TenantScope para throw em vez de silent return
- Testes E2E de cross-tenant access via jobs
- Demais achados da análise (já todos corretamente implementados)
