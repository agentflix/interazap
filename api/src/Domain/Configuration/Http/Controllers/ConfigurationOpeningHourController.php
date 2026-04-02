<?php

declare(strict_types=1);

namespace Domain\Configuration\Http\Controllers;

use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Actions\ConfigurationOpeningHourActions;
use Domain\Configuration\Http\Requests\ConfigurationOpeningHourBulkRequest;
use Domain\Configuration\Http\Requests\ConfigurationOpeningHourRequest;
use Domain\Configuration\Http\Resources\ConfigurationOpeningHourResource;
use Domain\Configuration\Models\ConfigurationOpeningHour;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Controlador de Horários de Atendimento.
 *
 * Gerencia as janelas de funcionamento do tenant, permitindo definir
 * horários por dia da semana e verificar a disponibilidade atual.
 *
 * @category Controllers
 */
final class ConfigurationOpeningHourController extends BaseController
{
    public function __construct(
        private readonly ConfigurationOpeningHourActions $actions
    ) {}

    /**
     * Listar todos os horários configurados para o tenant.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @return JsonResponse Coleção de horários.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConfigurationOpeningHour::class);

        /** @var AuthUser $user */
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $items = $this->actions->list($tenantId);

        return $this->success([
            'opening_hours' => ConfigurationOpeningHourResource::collection($items),
        ]);
    }

    /**
     * Criar uma nova entrada de horário.
     *
     * @param  ConfigurationOpeningHourRequest  $request  Dados validados.
     * @return JsonResponse Registro criado.
     */
    public function store(ConfigurationOpeningHourRequest $request): JsonResponse
    {
        $this->authorize('create', ConfigurationOpeningHour::class);

        /** @var AuthUser $user */
        $user = Auth::user();

        $hour = $this->actions->create($user->tenant_id, $request->validated());

        return $this->created(new ConfigurationOpeningHourResource($hour), 'Horário criado');
    }

    /**
     * Exibir detalhes de um registro específico.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Detalhes do horário.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        /** @var AuthUser $user */
        $user = Auth::user();
        $hour = $this->actions->find($user->tenant_id, $id);

        $this->authorize('view', $hour);

        return $this->success(new ConfigurationOpeningHourResource($hour));
    }

    /**
     * Atualizar dados de um horário.
     *
     * @param  ConfigurationOpeningHourRequest  $request  Dados validados.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Registro atualizado.
     */
    public function update(ConfigurationOpeningHourRequest $request, string $id): JsonResponse
    {
        /** @var AuthUser $user */
        $user = Auth::user();
        $hour = $this->actions->list($user->tenant_id)->firstWhere('id', $id);

        if ($hour) {
            $this->authorize('update', $hour);
        } else {
            $this->authorize('create', ConfigurationOpeningHour::class);
        }

        $hour = $this->actions->update($user->tenant_id, $id, $request->validated());

        return $this->success(new ConfigurationOpeningHourResource($hour), 'Horário atualizado');
    }

    /**
     * Remover um registro de horário.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Confirmação sem conteúdo.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        /** @var AuthUser $user */
        $user = Auth::user();
        $hour = $this->actions->list($user->tenant_id)->firstWhere('id', $id);

        if ($hour) {
            $this->authorize('delete', $hour);
        }

        $this->actions->delete($user->tenant_id, $id);

        return $this->noContent();
    }

    /**
     * Substituir em massa todos os horários do tenant.
     *
     * @param  ConfigurationOpeningHourBulkRequest  $request  Lista de novos horários.
     * @return JsonResponse Coleção atualizada.
     */
    public function bulk(ConfigurationOpeningHourBulkRequest $request): JsonResponse
    {
        $this->authorize('create', ConfigurationOpeningHour::class);

        /** @var AuthUser $user */
        $user = Auth::user();

        $items = $this->actions->bulkReplace($user->tenant_id, $request->validated()['opening_hours']);

        return $this->success([
            'opening_hours' => ConfigurationOpeningHourResource::collection($items),
        ], 'Horários atualizados');
    }

    /**
     * Verificar se o tenant está aberto no momento atual.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @return JsonResponse Resultado booleano e dados auxiliares.
     */
    public function isOpen(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ConfigurationOpeningHour::class);

        /** @var AuthUser $user */
        $user = Auth::user();
        $result = $this->actions->isOpen($user->tenant_id);

        return $this->success($result);
    }
}
