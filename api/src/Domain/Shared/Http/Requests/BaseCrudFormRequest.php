<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de autorização para FormRequests de CRUD via policies.
 */
abstract class BaseCrudFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->authorizeCrudByPolicy();
    }

    protected function authorizeCrudByPolicy(): bool
    {
        $user = $this->user('sanctum') ?? $this->user();
        if ($user === null) {
            return false;
        }

        $modelClass = $this->modelClass();

        if ($this->isMethod('post')) {
            return $user->can('create', $modelClass);
        }

        $model = $this->resolveRouteModel($modelClass);
        if ($model !== null) {
            return $user->can('update', $model);
        }

        return $user->can('update', $modelClass);
    }

    /**
     * @return class-string<Model>
     */
    abstract protected function modelClass(): string;

    protected function routeModelKey(): string
    {
        return 'id';
    }

    /**
     * Resolves the model from route parameter.
     *
     * @param  class-string<Model>  $modelClass
     *
     * @note Relies on TenantScope being registered as a global scope in models
     *       that use BelongsToTenant trait. This ensures tenant isolation without
     *       explicit tenant filtering in this method.
     */
    protected function resolveRouteModel(string $modelClass): ?Model
    {
        $routeValue = $this->route($this->routeModelKey());
        if (! is_string($routeValue) || $routeValue === '') {
            return null;
        }

        /** @var Model $model */
        $model = new $modelClass;
        /** @var ?Model $found */
        $found = $model->newQuery()->find($routeValue);

        return $found;
    }
}
