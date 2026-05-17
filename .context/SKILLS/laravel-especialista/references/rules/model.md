# Regras Laravel: Model

Use estas regras para models Eloquent, factories e relações. Se o pedido mencionar "modal" no contexto Laravel backend, trate como "model" salvo indicação contrária.

## Model

- Usar `declare(strict_types=1);`.
- Usar `$fillable` explícito; nunca usar `$guarded = []`.
- Para novas tabelas, configurar UUID string:
  - `public $incrementing = false;`
  - `protected $keyType = 'string';`
  - gerar `Str::orderedUuid()` em `booted()` ou na Action, conforme padrão do contexto.
- Aplicar `BelongsToTenant` em models tenant-aware.
- Declarar `$casts` para booleans, arrays, datetimes, enums e valores numéricos.
- Usar `SoftDeletes` quando o domínio exigir restauração/auditoria.

## Relacionamentos

- Tipar retorno: `BelongsTo`, `HasMany`, `HasOne`, `BelongsToMany`.
- Nomear FKs explicitamente quando não forem convencionais.
- Planejár eager loading nas Actions para evitar N+1.
- Usar `select()` em listagens grandes quando o contexto já adota payload enxuto.

## Factories

- Criar ou atualizar factory para models testados.
- Gerar UUIDs e `tenant_id` coerentes com o tenant do teste.
- Evitar dados aleatorios que tornem testes frageis; preferir defaults previsiveis com overrides.
