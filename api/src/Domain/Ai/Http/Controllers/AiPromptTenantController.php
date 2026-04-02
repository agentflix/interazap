<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Controllers;

use App\Http\Controllers\Controller;
use Domain\Ai\Actions\Prompts\AiPromptTenantActions;
use Domain\Ai\Actions\Prompts\RollbackTenantPromptAction;
use Domain\Ai\Actions\Prompts\UpdateTenantPromptAction;
use Domain\Ai\DTOs\TenantPromptDTO;
use Domain\Ai\Http\Requests\UpdateTenantPromptRequest;
use Domain\Ai\Http\Resources\TenantPromptResource;
use Domain\Ai\Models\AiPromptTenant;
use Domain\Auth\Models\AuthUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller para endpoints de prompt do tenant.
 */
final class AiPromptTenantController extends Controller
{
    public function __construct(private readonly AiPromptTenantActions $actions) {}

    /**
     * Retorna o prompt atual do tenant.
     */
    public function show(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AiPromptTenant::class);

        /** @var AuthUser $user */
        $user = $request->user();
        $tenant = $user->tenant;

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        $tenantPrompt = $this->actions->findWithSegment($tenant);

        if (! $tenantPrompt) {
            return response()->json([
                'message' => 'No prompt configured for this tenant.',
                'data' => null,
            ]);
        }

        return response()->json([
            'data' => new TenantPromptResource($tenantPrompt),
        ]);
    }

    /**
     * Atualiza o prompt do tenant.
     */
    public function update(
        UpdateTenantPromptRequest $request,
        UpdateTenantPromptAction $action
    ): JsonResponse {
        $this->authorize('viewAny', AiPromptTenant::class);

        /** @var AuthUser $user */
        $user = $request->user();
        $tenant = $user->tenant;

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        $dto = TenantPromptDTO::fromRequest($request);
        $tenantPrompt = $action->execute($tenant, $dto);
        $this->actions->forgetBasePromptCache((string) $tenant->id);
        $tenantPrompt = $this->actions->loadSegment($tenantPrompt);

        return response()->json([
            'message' => 'Prompt updated. Validation is pending.',
            'data' => new TenantPromptResource($tenantPrompt),
        ]);
    }

    /**
     * Rollback para a versão anterior do prompt.
     */
    public function rollback(
        Request $request,
        RollbackTenantPromptAction $action
    ): JsonResponse {
        $this->authorize('viewAny', AiPromptTenant::class);

        /** @var AuthUser $user */
        $user = $request->user();
        $tenant = $user->tenant;

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        $tenantPrompt = $this->actions->findByTenant($tenant);

        if (! $tenantPrompt) {
            return response()->json(['message' => 'No prompt to rollback.'], 404);
        }

        $tenantPrompt = $action->execute($tenantPrompt);
        $this->actions->forgetBasePromptCache((string) $tenant->id);
        $tenantPrompt = $this->actions->loadSegment($tenantPrompt);

        return response()->json([
            'message' => 'Prompt rolled back successfully.',
            'data' => new TenantPromptResource($tenantPrompt),
        ]);
    }

    /**
     * Exclui o prompt customizado do tenant para voltar ao comportamento padrão.
     */
    public function destroy(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AiPromptTenant::class);

        /** @var AuthUser $user */
        $user = $request->user();
        $tenant = $user->tenant;

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        $tenantPrompt = $this->actions->findByTenant($tenant);

        if (! $tenantPrompt) {
            return response()->json(['message' => 'No prompt configured for this tenant.'], 404);
        }

        $tenantPrompt->delete();
        $this->actions->forgetBasePromptCache((string) $tenant->id);

        return response()->json([
            'message' => 'Prompt deleted successfully. Tenant will use segment and plan defaults.',
        ]);
    }

    /**
     * Resolve e retorna o prompt completo montado para o tenant.
     */
    public function resolve(
        Request $request,
    ): JsonResponse {
        $this->authorize('viewAny', AiPromptTenant::class);

        /** @var AuthUser $user */
        $user = $request->user();
        $tenant = $user->tenant;

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        $resolved = $this->actions->resolve($tenant);

        return response()->json([
            'data' => [
                'resolved_prompt' => $resolved['resolved_prompt'],
                'components' => $resolved['components'],
            ],
        ]);
    }
}
