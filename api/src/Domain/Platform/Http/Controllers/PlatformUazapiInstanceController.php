<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Controllers;

use Domain\Platform\Actions\PlatformUazapiInstanceActions;
use Domain\Platform\DTOs\PlatformUazapiInstanceDTO;
use Domain\Platform\Http\Requests\PlatformUazapiConnectRequest;
use Domain\Platform\Http\Requests\PlatformUazapiInstancePresenceRequest;
use Domain\Platform\Http\Requests\PlatformUazapiInstanceProfileImageRequest;
use Domain\Platform\Http\Requests\PlatformUazapiInstanceRequest;
use Domain\Platform\Http\Requests\PlatformUazapiInstanceUpdateAdminFieldsRequest;
use Domain\Platform\Http\Requests\PlatformUazapiInstanceUpdateNameRequest;
use Domain\Platform\Http\Resources\PlatformUazapiInstanceResource;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de Instâncias WhatsApp (Uazapi).
 *
 * Gerencia o ciclo de vida das instâncias de mensageria para o tenant,
 * incluindo criação, conexão (QR Code), desconexão e monitoramento de status.
 *
 * @category Controllers
 */
final class PlatformUazapiInstanceController extends BaseController
{
    /**
     * @param  PlatformUazapiInstanceActions  $actions  Ação de gestão de instâncias.
     */
    public function __construct(private readonly PlatformUazapiInstanceActions $actions) {}

    /**
     * Listar todas as instâncias vinculadas ao tenant.
     *
     * Aceita filtros via query string:
     * - status: 'connected', 'disconnected', 'connecting', 'qr'
     * - search: string para busca por nome ou system_name
     * - per_page: quantidade de itens por página
     * - page: número da página
     *
     * @param  Request  $request  Solicitação HTTP.
     * @return JsonResponse Lista paginada de instâncias.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \Domain\Platform\Models\PlatformUazapiInstance::class);

        $tenantId = $this->tenantId($request);
        $filters = $request->only(['status', 'search', 'per_page', 'page']);
        $paginator = $this->actions->list($tenantId, $filters);
        $paginator->getCollection()->transform(fn ($item) => new PlatformUazapiInstanceResource($item));

        return $this->paginated($paginator, 'Instâncias listadas');
    }

    /**
     * Criar uma nova configuração de instância.
     *
     * Registra os parâmetros iniciais para uma nova conexão WhatsApp.
     *
     * @param  PlatformUazapiInstanceRequest  $request  Dados validados da instância.
     * @return JsonResponse Recurso criado com status 201.
     */
    public function store(PlatformUazapiInstanceRequest $request): JsonResponse
    {
        $this->authorize('create', \Domain\Platform\Models\PlatformUazapiInstance::class);

        $tenantId = $this->tenantId($request);
        $instance = $this->actions->create($tenantId, PlatformUazapiInstanceDTO::fromRequest($request));

        return $this->created(new PlatformUazapiInstanceResource($instance), 'Instância criada');
    }

    /**
     * Exibir detalhes de uma instância específica.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID da instância.
     * @return JsonResponse Dados detalhados da instância.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $instance = $this->actions->find($tenantId, $id);
        $this->authorize('view', $instance);

        return $this->success(new PlatformUazapiInstanceResource($instance), 'Instância carregada');
    }

    /**
     * Consultar o status de conexão atual no Gateway.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID da instância.
     * @return JsonResponse Dados de status (connected, disconnected, etc).
     */
    public function status(Request $request, string $id): JsonResponse
    {
        $this->authorize('viewAny', \Domain\Platform\Models\PlatformUazapiInstance::class);

        $tenantId = $this->tenantId($request);
        $status = $this->actions->status($tenantId, $id);

        return $this->success($status, 'Status da instância');
    }

    /**
     * Iniciar processo de conexão (Geração de QR Code ou Pair Code).
     *
     * @param  PlatformUazapiConnectRequest  $request  Parâmetros de conexão.
     * @param  string  $id  Identificador UUID da instância.
     * @return JsonResponse Dados para autenticação no WhatsApp.
     */
    public function connect(PlatformUazapiConnectRequest $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $instance = $this->actions->find($tenantId, $id);
        $this->authorize('update', $instance);

        $response = $this->actions->connect($tenantId, $id, $request->validated());

        return $this->success([
            'instance' => new PlatformUazapiInstanceResource($instance),
            'connection' => $response,
        ], 'Conexão iniciada');
    }

    /**
     * Realizar logout da sessão ativa na instância.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID da instância.
     * @return JsonResponse Confirmação de desconexão.
     */
    public function disconnect(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $instance = $this->actions->find($tenantId, $id);
        $this->authorize('update', $instance);

        $response = $this->actions->disconnect($tenantId, $id);

        return $this->success($response, 'Instância desconectada');
    }

    /**
     * Remover permanentemente uma instância.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID da instância.
     * @return JsonResponse Confirmação de exclusão (204).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $instance = $this->actions->find($tenantId, $id);
        $this->authorize('delete', $instance);

        $this->actions->delete($tenantId, $id);

        return $this->noContent();
    }

    /**
     * Atualizar campos administrativos da instância.
     *
     * @param  PlatformUazapiInstanceUpdateAdminFieldsRequest  $request  Dados validados.
     * @param  string  $id  Identificador UUID da instância.
     * @return JsonResponse Instância atualizada.
     */
    public function updateAdminFields(
        PlatformUazapiInstanceUpdateAdminFieldsRequest $request,
        string $id,
    ): JsonResponse {
        $tenantId = $this->tenantId($request);
        $instance = $this->actions->find($tenantId, $id);
        $this->authorize('update', $instance);

        $updated = $this->actions->updateAdminFields($tenantId, $id, $request->validated());

        return $this->success(new PlatformUazapiInstanceResource($updated), 'Campos administrativos atualizados');
    }

    /**
     * Atualizar o nome da instância.
     *
     * @param  PlatformUazapiInstanceUpdateNameRequest  $request  Dados validados.
     * @param  string  $id  Identificador UUID da instância.
     * @return JsonResponse Instância atualizada.
     */
    public function updateName(
        PlatformUazapiInstanceUpdateNameRequest $request,
        string $id,
    ): JsonResponse {
        $tenantId = $this->tenantId($request);
        $instance = $this->actions->find($tenantId, $id);
        $this->authorize('update', $instance);

        $updated = $this->actions->updateName($tenantId, $id, $request->validated()['name']);

        return $this->success(new PlatformUazapiInstanceResource($updated), 'Nome da instância atualizado');
    }

    /**
     * Alterar a imagem de perfil da instância.
     *
     * @param  PlatformUazapiInstanceProfileImageRequest  $request  Dados validados.
     * @param  string  $id  Identificador UUID da instância.
     * @return JsonResponse Resposta do gateway.
     */
    public function profileImage(
        PlatformUazapiInstanceProfileImageRequest $request,
        string $id,
    ): JsonResponse {
        $tenantId = $this->tenantId($request);
        $instance = $this->actions->find($tenantId, $id);
        $this->authorize('update', $instance);

        $response = $this->actions->profileImage($tenantId, $id, $request->validated()['image']);

        return $this->success([
            'instance' => new PlatformUazapiInstanceResource($instance->fresh()),
            'response' => $response,
        ], 'Imagem do perfil atualizada');
    }

    /**
     * Publicar status de presença da instância.
     *
     * @param  PlatformUazapiInstancePresenceRequest  $request  Dados validados.
     * @param  string  $id  Identificador UUID da instância.
     * @return JsonResponse Resposta do gateway.
     */
    public function presence(
        PlatformUazapiInstancePresenceRequest $request,
        string $id,
    ): JsonResponse {
        $tenantId = $this->tenantId($request);
        $instance = $this->actions->find($tenantId, $id);
        $this->authorize('update', $instance);

        $response = $this->actions->presence($tenantId, $id, $request->validated()['presence']);

        return $this->success([
            'instance' => new PlatformUazapiInstanceResource($instance->fresh()),
            'response' => $response,
        ], 'Presença atualizada');
    }
}
