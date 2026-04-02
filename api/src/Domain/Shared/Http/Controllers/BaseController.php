<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Controlador base para respostas padronizadas com envelope.
 */
abstract class BaseController extends Controller
{
    use AuthorizesRequests;

    protected function tenantId(?\Illuminate\Http\Request $request = null): string
    {
        $request = $request ?? request();
        $tenantId = $request->user()?->tenant_id;

        if (! is_string($tenantId) || $tenantId === '') {
            abort(401, 'Unauthorized');
        }

        return $tenantId;
    }

    protected function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    protected function error(string $message = 'Error', int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    protected function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function accepted(mixed $data = null, string $message = 'Accepted'): JsonResponse
    {
        return $this->success($data, $message, 202);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function deleted(): JsonResponse
    {
        return $this->noContent();
    }

    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, 404);
    }

    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, 401);
    }

    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, 403);
    }

    /**
     * @param  \Illuminate\Pagination\LengthAwarePaginator<int, mixed>  $paginator
     */
    protected function paginated($paginator, string $message = 'Success'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ], 200);
    }
}
