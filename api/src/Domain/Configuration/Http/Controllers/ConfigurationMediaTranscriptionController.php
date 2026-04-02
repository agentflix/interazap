<?php

declare(strict_types=1);

namespace Domain\Configuration\Http\Controllers;

use Domain\Configuration\Http\Requests\ConfigurationMediaTranscriptionRequest;
use Domain\Configuration\Http\Resources\ConfigurationMediaTranscriptionResource;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Controlador de configurações de transcrição de mídia.
 *
 * Gerencia as preferências de transcrição automática de áudio,
 * imagem e vídeo por tenant, incluindo limites de uso.
 *
 * @category Controllers
 */
final class ConfigurationMediaTranscriptionController extends BaseController
{
    /**
     * Obter as configurações atuais de transcrição de mídia.
     */
    public function show(Request $request): JsonResponse
    {
        Gate::authorize('ai.autopilots.manage');

        $tenant = PlatformTenant::findOrFail($this->tenantId($request));

        return $this->success(
            new ConfigurationMediaTranscriptionResource($tenant),
            'Configurações de transcrição de mídia'
        );
    }

    /**
     * Atualizar as configurações de transcrição de mídia.
     */
    public function update(ConfigurationMediaTranscriptionRequest $request): JsonResponse
    {
        Gate::authorize('ai.autopilots.manage');

        $tenant = PlatformTenant::findOrFail($this->tenantId($request));

        $tenant->update($request->validated());

        logger()->info('[MediaTranscription] Configurações atualizadas', [
            'tenant_id' => $tenant->id,
            'settings' => $request->validated(),
        ]);

        return $this->success(
            new ConfigurationMediaTranscriptionResource($tenant),
            'Configurações de transcrição atualizadas'
        );
    }
}
