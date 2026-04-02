# AGENTS.md — AgentFlix Backend (Laravel 12 / PHP 8.3)

> 📌 This extends the root `../AGENTS.md` — read it first for full project context.

## Stack

- **Laravel 12** with PHP 8.3 strict mode
- **PostgreSQL 17** with pgvector for AI embeddings
- **Redis 7** for cache, queues, sessions
- **Sanctum** for API authentication
- **Spatie Permissions** for RBAC
- **Pest** for testing
- **PHPStan L6 + Larastan + Rector** for static analysis

## Mandatory Rules

1. `declare(strict_types=1)` in **every** PHP file
2. phpDoc on **every** class and public method
3. `final class` on Controllers, Actions, DTOs
4. `$fillable` explicit — **NEVER** `$guarded = []`
5. UUID primary keys — never auto-increment
6. Eager loading — **NEVER** N+1

## DDD Pattern

```
Controller → DTO → Action → Resource
```

All files live in `src/Domain/{Domain}/` following this structure:

- `Http/Controllers/{Domain}{Entity}Controller.php`
- `Actions/{Domain}{Entity}Actions.php`
- `Models/{Domain}{Entity}.php`
- `DTOs/{Domain}{Entity}DTO.php`
- `Http/Requests/{Domain}{Entity}{Verb}Request.php`
- `Http/Resources/{Domain}{Entity}Resource.php`
- `Policies/{Domain}{Entity}Policy.php`
- `Routes/api.php`

## Tenant Isolation

- Every model uses `BelongsToTenant` trait
- Every controller action calls `$this->authorize()`
- Queries auto-scoped by tenant via trait
- **NEVER** bypass tenant scope

## Testing

- Pest feature tests in `tests/Feature/{Domain}{Entity}Test.php`
- `actingAs()` with tenant-scoped user
- Assert response structure, status codes, DB state
- 0 skipped tests, ≥80% coverage

## Gates

```bash
composer gate:all    # format → analyse → test → refactor
```

Auto-fix: `composer format`

**All gates must pass before commit.**
