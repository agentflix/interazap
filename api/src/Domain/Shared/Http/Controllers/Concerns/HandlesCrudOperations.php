<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Controllers\Concerns;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Reúso de operações CRUD para controllers baseados em BaseController.
 */
trait HandlesCrudOperations
{
    /**
     * @param  callable(string):LengthAwarePaginator<int, mixed>  $list
     * @param  callable(mixed):mixed  $resourceFactory
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
     * @param  callable(string):mixed  $create
     * @param  callable(mixed):mixed  $resourceFactory
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
     * @param  callable(string,string):mixed  $find
     * @param  callable(mixed):mixed  $resourceFactory
     */
    protected function crudShow(Request $request, string $id, callable $find, callable $resourceFactory, string $message): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $model = $find($tenantId, $id);
        $this->authorize('view', $model);

        return $this->success($resourceFactory($model), $message);
    }

    /**
     * @param  callable(string,string):mixed  $find
     * @param  callable(string,string,mixed):mixed  $update  Recebe (tenantId, id, model) — o modelo real encontrado para autorização.
     * @param  callable(mixed):mixed  $resourceFactory
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
     * @param  callable(string,string):mixed  $find
     * @param  callable(string,string):void  $delete
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
