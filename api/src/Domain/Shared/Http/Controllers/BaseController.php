<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Controlador base com helpers para respostas JSON padronizadas.
 *
 * Fornece métodos utilitários para montar envelopes de sucesso, erro,
 * paginação e respostas HTTP semânticas (201, 202, 204, 401, 403, 404).
 */
abstract class BaseController extends Controller
{
    use AuthorizesRequests;

    /**
     * Retorna o tenant_id do usuário autenticado, abortando com 401 se ausente.
     *
     * @param  \Illuminate\Http\Request|null  $request  Requisição HTTP (usa request() global se nulo).
     * @return string Identificador do tenant.
     */
    protected function tenantId(?\Illuminate\Http\Request $request = null): string
    {
        $request = $request ?? request();
        $tenantId = $request->user()?->tenant_id;

        if (! is_string($tenantId) || $tenantId === '') {
            abort(401, 'Unauthorized');
        }

        return $tenantId;
    }

    /**
     * Retorna resposta de sucesso com envelope padronizado.
     *
     * @param  mixed  $data  Dados a incluir na chave "data".
     * @param  string  $message  Mensagem descritiva da operação.
     * @param  int  $statusCode  Código HTTP de status (padrão: 200).
     * @return JsonResponse Envelope JSON de sucesso.
     */
    protected function success(mixed $data = null, string $message = 'Success', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $statusCode);
    }

    /**
     * Retorna resposta de erro com envelope padronizado.
     *
     * @param  string  $message  Mensagem de erro para o cliente.
     * @param  int  $statusCode  Código HTTP de status (padrão: 400).
     * @param  mixed  $errors  Detalhes adicionais de validação ou erros (opcional).
     * @return JsonResponse Envelope JSON de erro.
     */
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

    /**
     * Retorna resposta HTTP 201 Created com envelope padronizado.
     *
     * @param  mixed  $data  Recurso recém-criado.
     * @param  string  $message  Mensagem descritiva.
     * @return JsonResponse Resposta 201.
     */
    protected function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    /**
     * Retorna resposta HTTP 202 Accepted indicando processamento assíncrono.
     *
     * @param  mixed  $data  Dados opcionais do recurso aceito.
     * @param  string  $message  Mensagem descritiva.
     * @return JsonResponse Resposta 202.
     */
    protected function accepted(mixed $data = null, string $message = 'Accepted'): JsonResponse
    {
        return $this->success($data, $message, 202);
    }

    /**
     * Retorna resposta HTTP 204 No Content (sem corpo).
     *
     * @return JsonResponse Resposta 204.
     */
    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    /**
     * Retorna resposta HTTP 204 para operações de exclusão bem-sucedidas.
     *
     * @return JsonResponse Resposta 204.
     */
    protected function deleted(): JsonResponse
    {
        return $this->noContent();
    }

    /**
     * Retorna resposta HTTP 404 Not Found.
     *
     * @param  string  $message  Mensagem de recurso não encontrado.
     * @return JsonResponse Resposta 404.
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->error($message, 404);
    }

    /**
     * Retorna resposta HTTP 401 Unauthorized.
     *
     * @param  string  $message  Mensagem de não autorizado.
     * @return JsonResponse Resposta 401.
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->error($message, 401);
    }

    /**
     * Retorna resposta HTTP 403 Forbidden.
     *
     * @param  string  $message  Mensagem de acesso negado.
     * @return JsonResponse Resposta 403.
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->error($message, 403);
    }

    /**
     * Retorna resposta paginada com metadados e links de navegação.
     *
     * @param  \Illuminate\Pagination\LengthAwarePaginator<int, mixed>  $paginator  Paginador Eloquent.
     * @param  string  $message  Mensagem descritiva da listagem.
     * @return JsonResponse Resposta com data, meta e links de paginação.
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
