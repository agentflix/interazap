<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Controllers;

use Domain\Ai\Actions\AiAutopilotRunActions;
use Domain\Ai\DTOs\AiAutopilotRunDTO;
use Domain\Ai\Http\Requests\AiAutopilotRunRequest;
use Domain\Ai\Http\Resources\AiAutopilotRunResource;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de Execuções do Autopilot.
 *
 * Gerencia o histórico e o detalhamento das execuções de playbooks de IA,
 * permitindo iniciar novos "runs" e consultar o progresso e resultados.
 *
 * @category Controllers
 */
final class AiAutopilotRunController extends BaseController
{
    public function __construct(private readonly AiAutopilotRunActions $actions) {}

    /**
     * Criar e executar uma nova run do Autopilot.
     *
     * @param  AiAutopilotRunRequest  $request  Solicitação validada.
     * @return JsonResponse Execução criada e processada.
     */
    public function store(AiAutopilotRunRequest $request): JsonResponse
    {
        $this->authorizeForRun($request);

        $tenantId = (string) $request->user()->tenant_id;
        $dto = AiAutopilotRunDTO::fromRequest($request, '');
        $run = $this->actions->run($tenantId, $dto);

        return $this->accepted(new AiAutopilotRunResource($run), 'Execução enfileirada')
            ->header('Location', url("/api/ai/runs/{$run->id}"));
    }

    /**
     * Criar e executar uma run vinculada a um playbook.
     *
     * @param  AiAutopilotRunRequest  $request  Solicitação validada.
     * @param  string  $playbookId  Identificador UUID do playbook.
     * @return JsonResponse Execução criada e processada.
     */
    public function runPlaybook(AiAutopilotRunRequest $request, string $playbookId): JsonResponse
    {
        $this->authorizeForRun($request);

        $tenantId = (string) $request->user()->tenant_id;
        $dto = AiAutopilotRunDTO::fromRequest($request, $playbookId);
        $run = $this->actions->run($tenantId, $dto);

        return $this->accepted(new AiAutopilotRunResource($run), 'Execução enfileirada')
            ->header('Location', url("/api/ai/runs/{$run->id}"));
    }

    /**
     * Listar o histórico de execuções do tenant.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @return JsonResponse Lista paginada de execuções.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorizeForView($request);

        $tenantId = (string) $request->user()->tenant_id;
        $paginator = $this->actions->list($tenantId);
        $paginator->getCollection()->transform(fn ($item) => new AiAutopilotRunResource($item));

        return $this->paginated($paginator);
    }

    /**
     * Exibir detalhes de uma execução específica.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID da execução.
     * @return JsonResponse Logs e resultados da execução.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorizeForView($request);

        $tenantId = (string) $request->user()->tenant_id;
        $run = $this->actions->find($tenantId, $id);

        return $this->success(new AiAutopilotRunResource($run));
    }

    /**
     * Cancelar uma execução específica.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID da execução.
     * @return JsonResponse Execução cancelada.
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $this->authorizeForRun($request);

        $tenantId = (string) $request->user()->tenant_id;
        $run = $this->actions->cancel($tenantId, $id);

        return $this->success(new AiAutopilotRunResource($run), 'Execução cancelada');
    }

    private function authorizeForView(Request $request): void
    {
        if ($request->user()->can('ai.autopilots.view') || $request->user()->can('ai.autopilots.manage')) {
            return;
        }

        $this->authorize('ai.autopilots.view');
    }

    private function authorizeForRun(Request $request): void
    {
        if ($request->user()->can('ai.autopilots.run') || $request->user()->can('ai.autopilots.manage')) {
            return;
        }

        $this->authorize('ai.autopilots.run');
    }
}
