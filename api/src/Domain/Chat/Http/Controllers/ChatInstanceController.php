<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Actions\ChatInstanceActions;
use Domain\Chat\Http\Requests\ChatInstanceConnectRequest;
use Domain\Chat\Http\Requests\ChatInstancePresenceRequest;
use Domain\Chat\Http\Requests\ChatInstanceProfileImageRequest;
use Domain\Chat\Http\Requests\ChatInstanceRequest;
use Domain\Chat\Http\Resources\ChatInstanceResource;
use Domain\Chat\Models\ChatInstance;
use Domain\Shared\Http\Controllers\BaseController as Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de Instâncias de Chat.
 *
 * Gerencia as conexões de mensageria (WhatsApp/Omnichannel) para o tenant,
 * lidando com o CRUD de configurações e operações de conectividade (QR Code, Logout).
 *
 * @category Controllers
 */
final class ChatInstanceController extends Controller
{
    public function __construct(
        private readonly ChatInstanceActions $actions
    ) {}

    /**
     * Listar todas as instâncias do tenant.
     *
     * @param  Request  $request  Solicitação HTTP com filtros.
     * @return JsonResponse Lista paginada de instâncias.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ChatInstance::class);

        $tenantId = $request->user()->tenant_id;
        $filters = $request->only(['search', 'status', 'is_active', 'per_page', 'page']);

        $paginator = $this->actions->list($tenantId, $filters);
        $paginator->getCollection()->transform(fn ($item) => new ChatInstanceResource($item));

        return $this->paginated($paginator, 'Instâncias listadas');
    }

    /**
     * Criar uma nova configuração de instância.
     *
     * @param  ChatInstanceRequest  $request  Dados validados.
     * @return JsonResponse Recurso criado.
     */
    public function store(ChatInstanceRequest $request): JsonResponse
    {
        $this->authorize('create', ChatInstance::class);

        $tenantId = $request->user()->tenant_id;

        $instance = $this->actions->create($tenantId, $request->validated());

        return $this->created(
            new ChatInstanceResource($instance)
        );
    }

    /**
     * Exibir detalhes de uma instância específica.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Detalhes da instância.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $instance = $this->actions->find($tenantId, $id);
        $this->authorize('view', $instance);

        return $this->success(
            new ChatInstanceResource($instance)
        );
    }

    /**
     * Atualizar dados de configuração de uma instância.
     *
     * @param  ChatInstanceRequest  $request  Dados validados.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Registro atualizado.
     */
    public function update(ChatInstanceRequest $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $instance = $this->actions->find($tenantId, $id);
        $this->authorize('update', $instance);

        $instance = $this->actions->update($tenantId, $id, $request->validated());

        return $this->success(
            new ChatInstanceResource($instance)
        );
    }

    /**
     * Remover uma instância.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Confirmação sem conteúdo.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $instance = $this->actions->find($tenantId, $id);
        $this->authorize('delete', $instance);

        $this->actions->delete($tenantId, $id);

        return $this->noContent();
    }

    /**
     * Alternar o status de ativação (Habilitado/Desabilitado) da instância.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Confirmação sem conteúdo.
     */
    public function toggleActive(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $instance = $this->actions->find($tenantId, $id);
        $this->authorize('update', $instance);

        $this->actions->toggleActive($tenantId, $id);

        return $this->noContent();
    }

    /**
     * Realizar o logout da sessão ativa junto ao provedor.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Instância desconectada.
     */
    public function disconnect(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $instance = $this->actions->find($tenantId, $id);
        $this->authorize('update', $instance);

        $instance = $this->actions->disconnect($tenantId, $id);

        return $this->success(
            new ChatInstanceResource($instance),
            'Instância desconectada'
        );
    }

    /**
     * Iniciar processo de conexão (Geração de QR Code).
     *
     * @param  ChatInstanceConnectRequest  $request  Parâmetros de conexão.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Dados para autenticação (base64 qr code).
     */
    public function connect(ChatInstanceConnectRequest $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $instance = $this->actions->find($tenantId, $id);
        $this->authorize('update', $instance);

        $result = $this->actions->connect($tenantId, $id, $request->validated());

        return $this->success($result, 'Conexão iniciada');
    }

    /**
     * Sincronizar e obter o status atual no provedor externo.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Status técnico da instância.
     */
    public function status(Request $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $instance = $this->actions->find($tenantId, $id);
        $this->authorize('view', $instance);

        $result = $this->actions->status($tenantId, $id);

        return $this->success($result, 'Status da instância');
    }

    /**
     * Alterar a imagem de perfil da instância.
     *
     * @param  ChatInstanceProfileImageRequest  $request  Dados validados.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Imagem alterada.
     */
    public function profileImage(ChatInstanceProfileImageRequest $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $instance = $this->actions->find($tenantId, $id);
        $this->authorize('update', $instance);

        $result = $this->actions->profileImage($tenantId, $id, $request->validated('image'));

        return $this->success($result, 'Imagem do perfil alterada');
    }

    /**
     * Publicar status de presença da instância.
     *
     * @param  ChatInstancePresenceRequest  $request  Dados validados.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Presença alterada.
     */
    public function presence(ChatInstancePresenceRequest $request, string $id): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $instance = $this->actions->find($tenantId, $id);
        $this->authorize('update', $instance);

        $result = $this->actions->presence($tenantId, $id, $request->validated('presence'));

        return $this->success($result, 'Presença alterada');
    }
}
