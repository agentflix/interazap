# PLAN-021 — Bloquear exclusão de integração conectada (Chat)

## Objetivo

Impedir que uma integração WhatsApp seja excluída enquanto estiver com status `connected`. O sistema deve exigir desconexão prévia antes de permitir a exclusão — tanto no nível da API (validação) quanto no Frontend (UX com botão desabilitado).

## Módulo relacionado

Chat

## PRD relacionado: PRD-CHAT-001

## Escopo

### Incluído

- Backend: adicionar validação em `ChatInstanceActions::delete()` que rejeita exclusão de instâncias com `isConnected() === true`, retornando erro 409 (Conflict) com mensagem clara
- Backend: adicionar teste Pest em `ChatInstanceTest.php` cobrindo o novo cenário de bloqueio
- Frontend: desabilitar o botão de excluir na tabela quando `isConnected(item) === true`, mantendo o tooltip "Desconecte primeiro"
- Frontend: o erro 409 da API deve ser tratado com toast de erro explicativo

### Excluído

- Alterar o comportamento do `disconnect()` — a sessão local será limpa localmente apenas (já existente)
- Fazer chamada ao gateway Uazapi no `disconnect()` — fora do escopo
- Alterar `ChatIntegrationConnector` ou `UazapiGatewayService`

### Limitação conhecida

- O `disconnect()` não faz chamada ao gateway Uazapi — apenas limpa `status` e `last_connection` localmente. A sessão no provedor Uazapi **permanecerá ativa** mesmo após o `disconnect()` ser chamado. Esta é uma limitação intencional fora do escopo deste plano.

## Evidências da Codebase

### Backend

- [x] `api/src/Domain/Chat/Http/Controllers/ChatInstanceController.php` — `destroy()` (linha 115), `$this->authorize('delete', $instance)`
- [x] `api/src/Domain/Chat/Actions/ChatInstanceActions.php` — `delete()` (linha 157): `$instance->delete()` sem validação de status. `isConnected()` na **linha 268 — é `private`** (precisa ser elevado a `public`)
- [x] `api/src/Domain/Chat/Models/ChatInstance.php` — `isConnected()` method, `status` field
- [x] `api/src/Domain/Chat/Policies/ChatInstancePolicy.php` — `delete()` gate
- [x] `tests/Feature/ChatInstanceControllerTest.php` — arquivo de testes existente (não é `ChatInstanceTest.php`)

### Frontend

- [x] `app/src/app/pages/chat/integration/integration.ts` — `openDelete()`, `isConnected()`, `af-table-actions` (sempre expõe delete)
- [x] `app/src/app/core/services/integration.service.ts` — `delete()` (HTTP DELETE)
- [x] `app/src/app/shared/components/table-actions/table-actions.ts` — `delete` output, **não tem `deleteDisabled` — precisa adicionar**
- [x] `app/src/app/shared/components/confirm-modal/confirm-modal.ts` — modal de confirmação usado no delete
- [x] `app/src/app/shared/components/icon-button/icon-button.ts` — suporte a `[disabled]`

## Etapas propostas

1. **Backend — Adicionar validação em `ChatInstanceActions::delete()`**
   - `isConnected()` em `ChatInstanceActions` é `private` (linha 268) — **elevar para `public`** para uso no `delete()`
   - Antes de `$instance->delete()`, verificar `$this->isConnected($instance)`
   - Se conectada: lançar `\Symfony\Component\HttpKernel\Exception\ConflictHttpException` (mesmo padrão de `UnprocessableEntityHttpException` usado no mesmo arquivo — **não usar `ApiException::conflict()` que não existe**)
   - Mensagem: "Não é possível excluir uma integração conectada. Desconecte primeiro."
   - Garantir que o `tenant_id` filter continue aplicado via `$this->find()`

2. **Backend — Adicionar teste Pest em `ChatInstanceControllerTest.php`**
   - `test_cannot_delete_connected_integration` — status connected → assert 409 + mensagem
   - `test_cannot_delete_connected_integration_cross_tenant` — tenant B tenta deletar instância de tenant A (sem permissão) → assert 403
   - `test_can_delete_disconnected_integration` — status disconnected → assert 204
   - `test_can_delete_integration_with_qr_status` — status qr (não conectado) → assert 204 (documenta que qr não é bloqueante)
   - Verificar método correto de autenticação no arquivo (`actingAsApiUser` ou similar — **não `actingAsAs()`**)

3. **Frontend — Desabilitar delete no `af-table-actions` quando conectado**
   - `af-table-actions` currently only has `showDelete` (show/hide) — **adicionar `deleteDisabled` input**
   - Em `af-table-actions.ts`: adicionar `readonly deleteDisabled = input(false)`
   - No template: usar `[attr.title]` condicional para o tooltip:
     ```html
     <af-icon-button ... [disabled]="deleteDisabled()" [attr.title]="deleteDisabled() ? 'Desconecte primeiro para excluir' : 'Excluir'">
     ```
   - No template de `integration.ts`: `<af-table-actions [deleteDisabled]="isConnected(item)" ...>`

4. **Frontend — Tratar erro 409 da API no `handleDeleteConfirmed()`**
   - No `error` callback, verificar `err.status === 409`
   - Toast específico: "Não é possível excluir uma integração conectada. Desconecte primeiro."

## Entregas derivadas

**Entregas:** 1 | **Tasks:** 2

| Entrega | Descrição | Tasks | Esforço | Status |
|---------|-----------|-------|---------|--------|
| 1 | Bloquear delete de integração conectada (BE + FE) | TASK-033-BE, TASK-033-FE | S | todo |

## Arquivos a Modificar

### Backend (Laravel)

| Arquivo | Ação | Caminho |
|---------|------|---------|
| `ChatInstanceActions.php` | modificar | `api/src/Domain/Chat/Actions/` |
| `ChatInstanceControllerTest.php` | modificar | `api/tests/Feature/` |

### Frontend (Angular)

| Arquivo | Ação | Caminho |
|---------|------|---------|
| `table-actions.ts` | modificar | `app/src/app/shared/components/table-actions/` |
| `integration.ts` | modificar | `app/src/app/pages/chat/integration/` |

## Riscos e dependências

### Riscos

| Risco | Probabilidade | Impacto | Mitigação |
|-------|--------------|---------|-----------|
| Breaking change: clientes que fazem DELETE programaticamente sem desconectar vão receber 409 | Baixa | Médio | Documentar na resposta 409; é comportamento esperado |

### Dependências

- Nenhuma — não há dependência de gateway ou outros módulos

## Estimativa

| Item | Valor |
|------|-------|
| Complexidade | Baixa |
| Camadas afetadas | Backend + Frontend |
| Migrações necessárias | Não |
| Impacto em módulos existentes | Não (escopo isolado Chat) |

## Gates

| Camada | Comando |
|--------|---------|
| Backend | `composer format && ./vendor/bin/phpstan analyse --level=6 src/Domain/Chat/ && php artisan test --filter=ChatInstanceControllerTest` |
| Frontend | `pnpm run gate:all` |
