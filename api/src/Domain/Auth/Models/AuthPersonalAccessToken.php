<?php

declare(strict_types=1);

namespace Domain\Auth\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Personal access token usando tabela com prefixo auth_ e UUID.
 *
 * Não pode ser `final` pois Sanctum resolve o model via `Sanctum::personalAccessTokenModel()`
 * e depende de extensibilidade interna.
 */
class AuthPersonalAccessToken extends PersonalAccessToken
{
    use HasUuids;

    protected $table = 'auth_personal_access_tokens';

    public $incrementing = false;

    protected $keyType = 'string';
}
