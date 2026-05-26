<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Controllers\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Trait com operações CRUD padronizadas para controllers que estendem BaseController.
 *
 * Encapsula autorização via policy, resolução do tenant e transformação
 * de recursos para as operações de listagem, criação, exibição, atualização e exclusão.
 */
trait HandlesCrudOperations
{
    /**
     * Executa a listagem paginada com autorização de viewAny.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $policyClass  Classe do modelo a autorizar.
     * @param  callable(string):LengthAwarePaginator<int, mixed>  $list  Função que retorna o paginador para o tenant.
     * @param  callable(mixed):mixed  $resourceFactory  Transforma cada item no resource de resposta.
     * @param  string  $message  Mensagem descritiva da listagem.
     * @return JsonResponse Resposta paginada.
     */
    protected function crudIndex(
        Request $request,
        string $policyClass,
        callable $list,
        callable $resourceFactory,
        string $message,
    ): JsonResponse {
        $this->authorize('viewAny', $policyClass);

        $tenantId = $this->tenantId($request);
        $paginator = $list($tenantId);
        $paginator->getCollection()->transform(static fn (mixed $item): mixed => $resourceFactory($item));

        return $this->paginated($paginator, $message);
    }

    /**
     * Executa a criação do recurso com autorização de create.
     *
     * @param  FormRequest  $request  FormRequest validado.
     * @param  string  $policyClass  Classe do modelo a autorizar.
     * @param  callable(string):mixed  $create  Função que cria o recurso para o tenant.
     * @param  callable(mixed):mixed  $resourceFactory  Transforma o recurso criado em resource de resposta.
     * @param  string  $message  Mensagem descritiva da criação.
     * @return JsonResponse Resposta 201 com o recurso criado.
     */
    protected function crudStore(
        FormRequest $request,
        string $policyClass,
        callable $create,
        callable $resourceFactory,
        string $message,
    ): JsonResponse {
        $this->authorize('create', $policyClass);

        $tenantId = $this->tenantId($request);
        $created = $create($tenantId);

        return $this->created($resourceFactory($created), $message);
    }

    /**
     * Exibe um único recurso com autorização de view.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  Identificador do recurso.
     * @param  callable(string,string):mixed  $find  Função que localiza o recurso por (tenantId, id).
     * @param  callable(mixed):mixed  $resourceFactory  Transforma o modelo em resource de resposta.
     * @param  string  $message  Mensagem descritiva.
     * @return JsonResponse Resposta 200 com o recurso.
     */
    protected function crudShow(Request $request, string $id, callable $find, callable $resourceFactory, string $message): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $model = $find($tenantId, $id);
        $this->authorize('view', $model);

        return $this->success($resourceFactory($model), $message);
    }

    /**
     * Atualiza um recurso existente com autorização de update.
     *
     * @param  FormRequest  $request  FormRequest validado.
     * @param  string  $id  Identificador do recurso.
     * @param  callable(string,string):mixed  $find  Função que localiza o recurso por (tenantId, id).
     * @param  callable(string,string,mixed):mixed  $update  Função de atualização recebendo (tenantId, id, model).
     * @param  callable(mixed):mixed  $resourceFactory  Transforma o modelo atualizado em resource de resposta.
     * @param  string  $message  Mensagem descritiva.
     * @return JsonResponse Resposta 200 com o recurso atualizado.
     */
    protected function crudUpdate(
        FormRequest $request,
        string $id,
        callable $find,
        callable $update,
        callable $resourceFactory,
        string $message,
    ): JsonResponse {
        $tenantId = $this->tenantId($request);
        $model = $find($tenantId, $id);
        $this->authorize('update', $model);

        $updated = $update($tenantId, $id, $model);

        return $this->success($resourceFactory($updated), $message);
    }

    /**
     * Remove um recurso com autorização de delete.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  Identificador do recurso.
     * @param  callable(string,string):mixed  $find  Função que localiza o recurso por (tenantId, id).
     * @param  callable(string,string):void  $delete  Função que exclui o recurso por (tenantId, id).
     * @return JsonResponse Resposta 204 sem conteúdo.
     */
    protected function crudDestroy(Request $request, string $id, callable $find, callable $delete): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $model = $find($tenantId, $id);
        $this->authorize('delete', $model);

        $delete($tenantId, $id);

        return $this->noContent();
    }
}
