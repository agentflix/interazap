# TASKS-034 — Bloquear exclusão de integração conectada (PLAN-021)

## Status: todo

## Plano origem: PLAN-021-bloquear-delete-integracao-conectada

## Agente responsável

BACKEND + FRONTEND

## Goal

Impedir exclusão de integração WhatsApp quando `isConnected() === true`. Duas camadas:

1. **Backend**: `ChatInstanceActions::delete()` lança 409 se instância conectada
2. **Frontend**: botão "Excluir" desabilitado + tooltip + toast de erro 409

## Constraints

- Backend: não alterar `disconnect()` — apenas validação no `delete()`
- Backend: manter tenant isolation via `find()`
- Frontend: manter `ChangeDetectionStrategy.OnPush`, `takeUntilDestroyed`
- Respeitar padrões AGENTS.md: `final class`, `readonly`, phpDoc

## Context — Correções de revisão

> ⚠️ **Erros encontrados na primeira versão do plano:**
> 1. `isConnected()` em `ChatInstanceActions` é `private` — elevar para `public`
> 2. `ApiException::conflict()` **não existe** na codebase — usar `\Symfony\Component\HttpKernel\Exception\ConflictHttpException`
> 3. Arquivo de teste é `ChatInstanceControllerTest.php` — não `ChatInstanceTest.php`
> 4. No template: usar `[attr.title]` condicional — não `title=""` estático
> 5. Testes Pest: verificar método correto de autenticação (não `actingAsAs()`)
> 6. Adicionar testes: cross-tenant + qr-status

### Backend — `ChatInstanceActions.php`

- `isConnected()` é `private` na linha 268 — **elevar para `public`** antes de usar no `delete()`
- O `delete()` recebe `(string $tenantId, string $id)` — não `(string $id)` como no plano inicial
- Exception padrão do projeto: `\Symfony\Component\HttpKernel\Exception\*HttpException` (exemplo em uso: `UnprocessableEntityHttpException` linhas 308 e 342)

```php
public function delete(string $tenantId, string $id): void
{
    $instance = $this->find($tenantId, $id);

    if ($this->isConnected($instance)) {
        throw new \Symfony\Component\HttpKernel\Exception\ConflictHttpException(
            'Não é possível excluir uma integração conectada. Desconecte primeiro.'
        );
    }

    $instance->delete();
}
```

### Backend — `ChatInstanceControllerTest.php`

> ⚠️ Verificar o método correto de autenticação no arquivo existente antes de escrever os testes. Padrão típico do projeto: `actingAsApiUser()` ou similar — **não `actingAsAs()`**.

```php
test('cannot delete a connected integration', function () {
    $user = $this->actingAsApiUser(); // VERIFICAR método correto no arquivo
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $user->tenant_id,
        'status' => 'connected',
    ]);

    $this->deleteJson("/api/chat/integrations/{$instance->id}")
        ->assertStatus(409)
        ->assertJsonFragment(['message' => 'Não é possível excluir uma integração conectada. Desconecte primeiro.']);
});

test('cannot delete connected integration from another tenant', function () {
    $user = $this->actingAsApiUser();
    $otherTenant = Tenant::factory()->create();
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $otherTenant->id,
        'status' => 'connected',
    ]);

    $this->deleteJson("/api/chat/integrations/{$instance->id}")
        ->assertStatus(404); // find() com tenant filter retorna null → 404
});

test('can delete a disconnected integration', function () {
    $user = $this->actingAsApiUser();
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $user->tenant_id,
        'status' => 'disconnected',
    ]);

    $this->deleteJson("/api/chat/integrations/{$instance->id}")
        ->assertStatus(204);

    $this->assertDatabaseMissing('chat_instances', ['id' => $instance->id]);
});

test('can delete an integration with qr status', function () {
    $user = $this->actingAsApiUser();
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $user->tenant_id,
        'status' => 'qr', // qr não é "connected" — não deve ser bloqueante
    ]);

    $this->deleteJson("/api/chat/integrations/{$instance->id}")
        ->assertStatus(204);
});
```

### Frontend — `table-actions.ts`

```typescript
// Adicionar input (já segue padrão dos outros inputs):
readonly deleteDisabled = input(false);

// No template — usar [attr.title] condicional:
<af-icon-button
  label="Excluir"
  variant="danger"
  size="sm"
  [disabled]="deleteDisabled()"
  [attr.title]="deleteDisabled() ? 'Desconecte primeiro para excluir' : 'Excluir'"
  (click)="delete.emit()"
>
  <lucide-icon name="trash-2" [size]="15" />
</af-icon-button>
```

### Frontend — `integration.ts`

```html
<!-- No template — passar deleteDisabled ao af-table-actions: -->
<af-table-actions
  [deleteDisabled]="isConnected(item)"
  (edit)="openEdit(item)"
  (delete)="openDelete(item)"
/>
```

```typescript
// Em handleDeleteConfirmed() — tratar erro 409:
error: (err) => {
  if (err.status === 409) {
    toast.error('Não é possível excluir uma integração conectada. Desconecte primeiro.');
  } else {
    toast.error('Erro ao remover integração');
  }
}
```

## Etapas

### Backend

- [ ] Elevar `isConnected()` de `private` para `public` em `ChatInstanceActions.php`
- [ ] Modificar `delete()` — adicionar validação `isConnected()` + `ConflictHttpException`
- [ ] Verificar método de autenticação correto em `ChatInstanceControllerTest.php`
- [ ] Adicionar teste Pest `test_cannot_delete_connected_integration`
- [ ] Adicionar teste Pest `test_cannot_delete_connected_integration_from_another_tenant`
- [ ] Adicionar teste Pest `test_can_delete_disconnected_integration`
- [ ] Adicionar teste Pest `test_can_delete_integration_with_qr_status`
- [ ] `composer format && ./vendor/bin/phpstan analyse --level=6 src/Domain/Chat/`
- [ ] `php artisan test --filter=ChatInstanceControllerTest`

### Frontend

- [ ] Adicionar `readonly deleteDisabled = input(false)` em `table-actions.ts`
- [ ] No template de `table-actions.ts`: `[disabled]="deleteDisabled()"` + `[attr.title]` condicional
- [ ] Em `integration.ts`: `[deleteDisabled]="isConnected(item)"` no `<af-table-actions>`
- [ ] Em `handleDeleteConfirmed()`: tratar `err.status === 409` com toast específico
- [ ] `pnpm run gate:all`

## Critérios de conclusão

- [ ] Código implementado conforme plano
- [ ] 4 testes Pest escritos e passando
- [ ] Gates verdes: `phpstan --level=6` + `pest --filter=ChatInstanceControllerTest`
- [ ] Gates FE verdes: `pnpm run gate:all`
- [ ] QA review sem issues críticos
- [ ] Code review aprovado
- [ ] Documentação atualizada

## Evidências

- Gates BE:
- Gates FE:
- QA Review:
- Code Review:
- Commit:
