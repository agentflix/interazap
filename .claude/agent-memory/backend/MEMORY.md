# MEMORY.md — Backend Agent

Persistent notes for Laravel/PHP backend work in InteraZap.

## Project conventions confirmed in use

- **Pest tests** are written as classic PHPUnit `class extends TestCase` files inside `tests/Feature/`. The annotation `@dataProvider` is **not** picked up — use the PHP attribute `#[\PHPUnit\Framework\Attributes\DataProvider('provider')]` for data providers in Pest-class-style tests.
- `LazilyRefreshDatabase` is applied globally via `tests/Pest.php` for all `Feature` tests.
- `BaseController` (`Domain\Shared\Http\Controllers`) provides the response envelope helpers: `success`, `created`, `error`, `paginated`, `noContent`. All controllers extend it.
- `BaseJsonResource` exposes a protected `iso(?DateTimeInterface)` helper for ISO-8601 timestamps; resources implement `data(Request)` instead of `toArray`.
- Public unauthenticated endpoints go through middleware group `throttle:public` (30 req/min/IP, defined in `app/Providers/AppServiceProvider.php`). Register them inside the `throttle:public` group in `routes/api.php`, **not** inside `auth:sanctum` and **not** inside `billing.delinquency`.
- `Domain/Platform` is the correct domain for **global, non-tenant** entities (e.g., InteraZap-internal leads/prospects, platform plans). These models do NOT use `BelongsToTenant`.
- UUID primary keys: set `public $incrementing = false`, `protected $keyType = 'string'`, and assign in `booted()` with `Str::orderedUuid()`.
- PHPStan on this project requires `--memory-limit=1G` for non-trivial file selections; default 128M crashes when bootstrapping the Laravel container.

## Honeypot pattern for public lead/contact endpoints

When exposing a public `POST` form endpoint, accept an extra optional field (e.g. `website`) in the FormRequest as `nullable|string`. In the Action, if the field is non-empty, return a fake unsaved model (silent drop) — do NOT 422, do NOT persist, do NOT dispatch events. Bots get a 201 and never know they were rejected.

## Sanctum custom PAT model

This project uses `Domain\Auth\Models\AuthPersonalAccessToken` (table `auth_personal_access_tokens`), configured via `config/sanctum.php` (`personal_access_token_model`) and `Sanctum::usePersonalAccessTokenModel()` in `AppServiceProvider`. Tests doing direct token lookup MUST use `AuthPersonalAccessToken::findToken()` — `Laravel\Sanctum\PersonalAccessToken::findToken()` throws `relation "personal_access_tokens" does not exist`.

## Bearer-token tests + AuthManager cache pitfall

In Sanctum's `Guard::__invoke`, the resolver tries `config('sanctum.guard', 'web')` BEFORE reading the `Authorization: Bearer` header. Within a single Pest TestCase, the `AuthManager` caches the resolved user across `getJson` calls, so a second `withToken($otherToken)->getJson(...)` may return the FIRST user. This does not affect production (each HTTP request gets a fresh container scope). Workarounds (in order of preference): (1) split into two test methods with one token each; (2) avoid `$this->app['auth']->forgetGuards()` — caused SEGFAULT here on PHP 8.3. (3) `$this->refreshApplication()` works but breaks `LazilyRefreshDatabase` transactions.
