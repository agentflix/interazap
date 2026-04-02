<?php

declare(strict_types=1);

namespace Domain\Platform\Actions;

use Domain\Platform\DTOs\PlatformUazapiInstanceDTO;
use Domain\Platform\Models\PlatformUazapiInstance;
use Domain\Platform\Services\UazapiGatewayService;
use Domain\Shared\Support\SearchSanitizer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orquestrador de Instâncias Uazapi.
 *
 * Centraliza a lógica de negócio para manipulação de instâncias de WhatsApp,
 * integrando a persistência no banco de dados com as chamadas de API do Gateway.
 *
 * @category Actions
 */
final class PlatformUazapiInstanceActions
{
    public function __construct(private readonly UazapiGatewayService $gateway) {}

    /**
     * Listar as instâncias de um tenant ordenadas pela data de criação.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $filters  Filtros opcionais (status, search, per_page, page).
     * @return LengthAwarePaginator<int, PlatformUazapiInstance> Paginador de instâncias.
     */
    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = PlatformUazapiInstance::query()
            ->where('tenant_id', $tenantId);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = SearchSanitizer::likeContains((string) $filters['search']);
            $connectionName = $query->getModel()->getConnectionName() ?: config('database.default');
            $driver = (string) config("database.connections.$connectionName.driver", 'pgsql');
            $operator = $driver === 'sqlite' ? 'like' : 'ilike';
            $query->where(function ($q) use ($search, $operator): void {
                $q->where('name', $operator, $search)
                    ->orWhere('system_name', $operator, $search);
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 15);

        return $query->orderByDesc('created_at')->paginate($perPage);
    }

    /**
     * Criar e inicializar uma nova instância no Gateway e no Banco de Dados.
     *
     * Realiza a chamada ao Gateway para obter o token e metadados iniciais
     * antes de persistir o registro local.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  PlatformUazapiInstanceDTO  $dto  Dados para criação.
     * @return PlatformUazapiInstance Instância criada e persistida.
     */
    public function create(string $tenantId, PlatformUazapiInstanceDTO $dto): PlatformUazapiInstance
    {
        return DB::transaction(function () use ($tenantId, $dto): PlatformUazapiInstance {
            $gatewayResponse = $this->gateway->initInstance($dto->toArray());
            $status = $this->normalizeStatus($this->extractStatus($gatewayResponse));

            return PlatformUazapiInstance::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'name' => $gatewayResponse['name'] ?? $dto->name,
                'system_name' => $dto->systemName,
                'token' => $gatewayResponse['token'] ?? $gatewayResponse['instance']['token'] ?? '',
                'status' => $status,
                'webhook_url' => $gatewayResponse['webhook']['url']
                    ?? $gatewayResponse['webhook_url']
                    ?? null,
                'config' => $dto->config,
                'metadata' => $gatewayResponse,
            ]);
        });
    }

    /**
     * Remover uma instância do Gateway e deletar o registro local.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da instância.
     */
    public function delete(string $tenantId, string $id): void
    {
        $instance = $this->find($tenantId, $id);
        $this->gateway->deleteInstance($instance->token);
        $instance->delete();
    }

    /**
     * Sincronizar e retornar o status oficial da instância vindo do Gateway.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da instância.
     * @return array<string, mixed> Resposta bruta do status vinda do gateway.
     */
    public function status(string $tenantId, string $id): array
    {
        $instance = $this->find($tenantId, $id);
        $status = $this->gateway->status($instance->token);

        $instance->status = $this->normalizeStatus($this->extractStatus($status, $instance->status));
        $instance->last_status_at = now();
        $instance->metadata = $status;
        $instance->save();

        return $status;
    }

    /**
     * Iniciar a conexão (Geração de QR/Pair) no Gateway.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da instância.
     * @param  array<string, mixed>  $payload  Parâmetros adicionais (ex: pairing code phone).
     * @return array<string, mixed> Resposta de conexão do gateway.
     */
    public function connect(string $tenantId, string $id, array $payload = []): array
    {
        $instance = $this->find($tenantId, $id);
        $response = $this->gateway->connectInstance($instance->token, $payload);
        $instance->status = $this->normalizeStatus($this->extractStatus($response, 'connecting'));
        $instance->metadata = $response;
        $instance->save();

        return $response;
    }

    /**
     * Desconectar (Logout) a instância no Gateway.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da instância.
     * @return array<string, mixed> Resposta de logout do gateway.
     */
    public function disconnect(string $tenantId, string $id): array
    {
        $instance = $this->find($tenantId, $id);
        $response = $this->gateway->disconnectInstance($instance->token);
        $instance->status = $this->normalizeStatus($this->extractStatus($response, 'disconnected'));
        $instance->metadata = $response;
        $instance->save();

        return $response;
    }

    /**
     * Atualizar configuração JSONB da instância.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da instância.
     * @param  array<string, mixed>  $payload  Campos de configuração a atualizar.
     * @return PlatformUazapiInstance Instância atualizada.
     */
    public function updateAdminFields(string $tenantId, string $id, array $payload): PlatformUazapiInstance
    {
        $instance = $this->find($tenantId, $id);

        if (array_key_exists('config', $payload)) {
            $currentConfig = is_array($instance->config) ? $instance->config : [];
            $instance->config = array_merge($currentConfig, (array) $payload['config']);
        }

        $instance->save();

        return $instance;
    }

    /**
     * Atualizar o nome da instância.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da instância.
     * @param  string  $name  Novo nome.
     * @return PlatformUazapiInstance Instância atualizada.
     */
    public function updateName(string $tenantId, string $id, string $name): PlatformUazapiInstance
    {
        $instance = $this->find($tenantId, $id);
        $instance->name = $name;
        $instance->save();

        return $instance;
    }

    /**
     * Alterar a imagem de perfil da instância.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da instância.
     * @param  string  $image  URL, base64 ou 'remove'.
     * @return array<string, mixed> Resposta do gateway.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
     */
    public function profileImage(string $tenantId, string $id, string $image): array
    {
        $instance = $this->find($tenantId, $id);

        if ($instance->status !== 'connected') {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException(
                'A instância deve estar conectada para alterar a imagem do perfil.'
            );
        }

        $response = $this->gateway->updateProfileImage($instance->token, $image);

        $metadata = is_array($instance->metadata) ? $instance->metadata : [];
        if (isset($response['profile']['profilePicUrl'])) {
            $metadata['profilePicUrl'] = $response['profile']['profilePicUrl'];
        } elseif (isset($response['profilePicUrl'])) {
            $metadata['profilePicUrl'] = $response['profilePicUrl'];
        }
        $instance->metadata = $metadata;
        $instance->save();

        return $response;
    }

    /**
     * Publicar status de presença da instância.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da instância.
     * @param  string  $presence  'available' ou 'unavailable'.
     * @return array<string, mixed> Resposta do gateway.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
     */
    public function presence(string $tenantId, string $id, string $presence): array
    {
        $instance = $this->find($tenantId, $id);

        if ($instance->status !== 'connected') {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException(
                'A instância deve estar conectada para alterar a presença.'
            );
        }

        $response = $this->gateway->updatePresence($instance->token, $presence);

        $metadata = is_array($instance->metadata) ? $instance->metadata : [];
        $metadata['current_presence'] = $presence;
        $instance->metadata = $metadata;
        $instance->save();

        return $response;
    }

    /**
     * Buscar uma instância local validando o tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da instância.
     * @return PlatformUazapiInstance Instância encontrada.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function find(string $tenantId, string $id): PlatformUazapiInstance
    {
        return PlatformUazapiInstance::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }

    /**
     * Extrair o status de uma carga de dados (Payload) do gateway.
     *
     * @param  array<string, mixed>  $payload  Dados brutos da resposta.
     * @param  string  $default  Status padrão em caso de falha na extração.
     * @return string Status extraído (ex: 'connected').
     */
    private function extractStatus(array $payload, string $default = 'disconnected'): string
    {
        $status = $payload['instance']['status'] ?? $payload['status'] ?? $default;

        if (is_string($status)) {
            return $status;
        }

        if (is_bool($status)) {
            return $status ? 'connected' : 'disconnected';
        }

        if (is_array($status)) {
            if (isset($status['status']) && is_string($status['status'])) {
                return $status['status'];
            }

            if (isset($status['connected']) && is_bool($status['connected'])) {
                return $status['connected'] ? 'connected' : 'disconnected';
            }

            if (isset($status['loggedIn']) && is_bool($status['loggedIn'])) {
                return $status['loggedIn'] ? 'connected' : 'disconnected';
            }
        }

        return $default;
    }

    /**
     * Normalizar o status para o padrão aceito pelo sistema.
     *
     * @param  mixed  $status  Status em tipos variados (string, bool, array).
     * @return string Status normalizado.
     */
    private function normalizeStatus(mixed $status): string
    {
        if (is_string($status)) {
            return $status;
        }

        if (is_bool($status)) {
            return $status ? 'connected' : 'disconnected';
        }

        if (is_array($status)) {
            if (isset($status['status']) && is_string($status['status'])) {
                return $status['status'];
            }

            if (isset($status['connected']) && is_bool($status['connected'])) {
                return $status['connected'] ? 'connected' : 'disconnected';
            }

            if (isset($status['loggedIn']) && is_bool($status['loggedIn'])) {
                return $status['loggedIn'] ? 'connected' : 'disconnected';
            }
        }

        return 'disconnected';
    }
}
