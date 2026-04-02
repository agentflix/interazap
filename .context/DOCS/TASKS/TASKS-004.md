# TASKS — Lista de Tarefas InteraZap

> Arquivo único contendo todas as tasks do projeto.

---

# TASK-001-prd-auth-autenticacao — Autenticação Multi-Tenant

## Status: done

## Plano origem: PLAN-001

## Agente responsável: @ORCHESTRATOR

## Goal

Implementar sistema de autenticação multi-tenant com Sanctum, incluindo login, logout, refresh token e gestão de sessões por tenant.

## Critérios de conclusão

- [] Código implementado
- [] Testes escritos e passando
- [] Gates verdes
- [] QA review aprovado
- [] Commit: `feat(auth): implement multi-tenant authentication with Sanctum`

---

# TASK-003-bugfix-chat-read-status — Corrigir Persistência do Read Status

## Status: done

## Plano origem: PLAN-BUGFIX-002

## Agente responsável: @DEBUG → @BACKEND

## Goal

Diagnosticar e corrigir bug em que o status `read` das mensagens não persiste após reload.

## Context

- Módulos afetados: Chat, Gateway
- Dependências: Ambiente com instância WhatsApp ativa

## Etapas

- [x] Diagnóstico no banco de dados
- [x] Inspeção do Gateway NestJS
- [x] Correção baseada nos findings
- [x] Validação end-to-end
- [x] Cleanup de logs

## Critérios de conclusão

- [x] Bug corrigido e validado
- [x] Gates verdes (validação mínima por escopo do bugfix)
- [x] Commit realizado (staging isolado do escopo)

## Evidências

- **Arquivo do fix:** `api/src/Domain/Chat/Actions/ChatWebhookIngestor.php`
- **Ajuste aplicado nesta rodada:** cleanup de logs diagnósticos (`info` → `debug` em pontos de rastreio e remoção de logs temporários BEFORE/AFTER SAVE), sem alteração de regra de negócio.
- **Validação mínima executada (backend, escopo read status):**
    - `cd api && php artisan test tests/Feature/ChatWebhookIngestorTest.php --filter "test_messages_update_without_message_key_updates_status_to_read|test_messages_update_with_stream_nested_raw_updates_status_to_delivered|test_status_update_with_numeric_ack_updates_timestamps"`
    - Resultado: **3 passed (8 assertions)**
- **QA:** `APPROVED_WITH_NOTES` (sem blocker crítico; recomendação de follow-up para teste negativo cross-tenant explícito em `messages_update`).
- **Code Review (REVIEWER):** `APPROVED` (sem blocker no escopo).
- **Observação de governança:** commit semântico da TASK-003 realizado com staging isolado dos arquivos de escopo.

---

# TASK-004-tratamento-erro-403 — Página de Acesso Negado

## Status: done

## Plano origem: PLAN-004

## Agente responsável: @FRONTEND

## Goal

Implementar página de "Acesso Negado" para tratar erros HTTP 403 do backend de forma amigável, evitando telas quebradas quando o usuário tenta executar ações sem permissão.

## Constraints

### Frontend (Angular 20+ / TypeScript 5.9)

- `ChangeDetectionStrategy.OnPush` em todo componente
- `signal()` e `computed()` para estado local
- `inject()` em vez de constructor injection
- `takeUntilDestroyed` em todas as subscriptions
- `track` em todo `@for`
- Nunca usar `any` ou `unknown`
- jsDoc em interfaces e funções exportadas
- Usar shared components — nunca raw `<button>`, `<input>`, ou HTML tables

### Padrões do Projeto

- Verificar `http://localhost:4200/ui-kit` antes de criar novos componentes visuais
- Seguir convenções existentes em `app/src/app/pages/auth/`

## Context

### Módulos afetados

- **Auth**: Interceptação de erros 403 + página de Acesso Negado
- **Shared**: Interceptor HTTP (authInterceptor)
- **Platform**: `/platform/users` — quando usuário sem permissão tenta criar/editar
- **Settings**: `/settings/users` — quando usuário sem permissão tenta criar/editar
- **CRM**: Qualquer tela com ações restritas por permissão

### Dependências

- Nenhuma — não depende de outras tasks

### Referências

- Backend error 403 response format:
    ```json
    {
        "message": "This action is unauthorized.",
        "exception": "Symfony\\Component\\HttpKernel\\Exception\\AccessDeniedHttpException"
    }
    ```
- Pattern de interceptors existentes: `app/src/app/core/interceptors/auth.interceptor.ts`
- Páginas de erro existentes: `app/src/app/pages/auth/access-denied/` (não existe ainda)

## Etapas

### Fase 1 — Criar AccessDeniedComponent

- [ ] Criar diretório `app/src/app/pages/auth/access-denied/`
- [ ] Criar arquivo `access-denied.ts`
- [ ] Implementar com:
    - `ChangeDetectionStrategy.OnPush`
    - Signal `errorMessage` com mensagem do queryParam
    - Botão "Voltar ao início" usando `AfButtonComponent`
    - Alert usando `AfAlertComponent` para exibir mensagem
    - Router para navegar de volta ao home

### Fase 2 — Adicionar Rota

- [ ] Abrir `app/src/app/app.routes.ts`
- [ ] Adicionar rota:
    ```typescript
    {
      path: 'access-denied',
      loadComponent: () =>
        import('./pages/auth/access-denied/access-denied').then((m) => m.AccessDeniedComponent),
    }
    ```

### Fase 3 — Modificar authInterceptor

- [ ] Abrir `app/src/app/core/interceptors/auth.interceptor.ts`
- [ ] Localizar bloco `catchError` após tratamento de 401
- [ ] Adicionar:
    ```typescript
    if (error.status === 403) {
        const errorMessage = error.error?.message || 'This action is unauthorized.';
        void router.navigate(['/access-denied'], {
            queryParams: { message: encodeURIComponent(errorMessage) },
        });
        return EMPTY;
    }
    ```
- [ ] Verificar se NÃO está em `/access-denied` antes de redirecionar (evitar loop)

### Fase 4 — Testar Cenário

- [ ] Testar manualmente em `/settings/users`:
    - Acessar com usuário SEM permissão de admin
    - Tentar criar/editar usuário
    - Verificar redirect para `/access-denied`
    - Verificar mensagem exibida corretamente
- [ ] Testar manualmente em `/platform/users`:
    - Mesmo cenário
- [ ] Testar que NÃO há loop de redirect

### Fase 5 — Gates e Commit

- [ ] Rodar `pnpm run gate:all` em `app/`
- [ ] Criar commit com mensagem convencional:

    ```
    feat(auth): add access denied page for 403 errors

    - Create AccessDeniedComponent with friendly error message
    - Add /access-denied route in app.routes.ts
    - Handle 403 errors in authInterceptor
    - Prevent redirect loops when already on access-denied page
    ```

## Critérios de conclusão

- [ ] AccessDeniedComponent criado e funcional
- [ ] Rota `/access-denied` configurada
- [ ] authInterceptor trata 403 sem quebrar a aplicação
- [ ] Mensagem de erro do backend exibida na página
- [ ] Botão "Voltar ao início" funciona corretamente
- [ ] NÃO há loop de redirect
- [ ] Gates verdes (`pnpm run gate:all`)
- [ ] Commit realizado

## Evidências

<!-- Preenchido na fase Confirm. -->

- Gates Frontend:
- Commit:
- Screenshots da página de Acesso Negado:

---

# TASK-999 — Placeholder

## Status: backlog

## Agente responsável:

## Goal

Tarefa em desenvolvimento.
