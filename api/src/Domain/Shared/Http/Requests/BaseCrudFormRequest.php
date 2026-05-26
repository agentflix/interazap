<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest base com autorização CRUD via policies.
 *
 * Resolve automaticamente a policy correta dependendo do método HTTP
 * (POST = create, outros = update), delegando a verificação ao modelo
 * identificado por modelClass().
 */
abstract class BaseCrudFormRequest extends FormRequest
{
    /**
     * Autoriza a requisição via policy do modelo.
     *
     * @return bool Verdadeiro se o usuário tem permissão para a operação.
     */
    public function authorize(): bool
    {
        return $this->authorizeCrudByPolicy();
    }

    /**
     * Realiza a autorização delegando à policy do modelo.
     *
     * @return bool Verdadeiro se autorizado.
     */
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
     * Retorna a classe do modelo Eloquent a ser usada na autorização via policy.
     *
     * @return class-string<Model> FQCN do modelo.
     */
    abstract protected function modelClass(): string;

    /**
     * Retorna o nome do parâmetro de rota usado para localizar o modelo.
     *
     * @return string Nome do parâmetro (padrão: 'id').
     */
    protected function routeModelKey(): string
    {
        return 'id';
    }

    /**
     * Resolve o modelo a partir do parâmetro de rota.
     *
     * Depende do TenantScope registrado globalmente via BelongsToTenant,
     * garantindo isolamento de tenant sem filtragem explícita aqui.
     *
     * @param  class-string<Model>  $modelClass  Classe do modelo a localizar.
     * @return Model|null Instância do modelo ou null se não encontrado.
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
