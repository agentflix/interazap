<?php

declare(strict_types=1);

namespace Domain\Configuration\Http\Controllers;

use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Actions\ConfigurationSchedulingSettingActions;
use Domain\Configuration\Http\Requests\UpdateSchedulingSettingRequest;
use Domain\Configuration\Http\Resources\ConfigurationSchedulingSettingResource;
use Domain\Configuration\Models\ConfigurationSchedulingSetting;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controlador de Configurações de Agendamento.
 *
 * Gerencia as configurações de confirmação de eventos do tenant,
 * permitindo definir antecedência de lembretes e canais de notificação.
 *
 * @category Controllers
 */
final class ConfigurationSchedulingSettingController extends BaseController
{
    public function __construct(
        private readonly ConfigurationSchedulingSettingActions $actions,
    ) {}

    /**
     * Obter configurações de agendamento do tenant autenticado.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @return JsonResponse Configuracoes atuais.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConfigurationSchedulingSetting::class);

        /** @var AuthUser $user */
        $user = Auth::user();

        $setting = $this->actions->get($user->tenant_id);

        return $this->success(new ConfigurationSchedulingSettingResource($setting));
    }

    /**
     * Atualizar configurações de agendamento do tenant.
     *
     * @param  UpdateSchedulingSettingRequest  $request  Dados validados.
     * @return JsonResponse Configuracoes atualizadas.
     */
    public function update(UpdateSchedulingSettingRequest $request): JsonResponse
    {
        $this->authorize('update', ConfigurationSchedulingSetting::class);

        /** @var AuthUser $user */
        $user = Auth::user();

        $setting = $this->actions->update($user->tenant_id, $request->validated());

        return $this->success(
            new ConfigurationSchedulingSettingResource($setting),
            'Configurações de agendamento atualizadas'
        );
    }
}
