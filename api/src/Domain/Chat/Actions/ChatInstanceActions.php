<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Services\ChatChannelConnector;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Casos de Uso para Instâncias de Chat.
 *
 * Centraliza a lógica de provisionamento de instâncias (WhatsApp/Uazapi),
 * incluindo configuração de webhooks, gestão de conexão (QR Code/Pairing),
 * e isolamento de configurações confidenciais por tenant.
 *
 * @category Actions
 */
final class ChatInstanceActions
{
    public function __construct(private readonly ChatChannelConnector $connector) {}

    /**
     * Listar instâncias do tenant com suporte a busca e filtros de status.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $filters  Critérios de busca (search, is_active).
     * @return LengthAwarePaginator Paginador com instâncias vinculadas.
     */
    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = ChatInstance::query()->where('tenant_id', $tenantId);

        if (! empty($filters['search'])) {
            $query->where('name', 'ilike', '%'.$filters['search'].'%');
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        $sort = $filters['sort_by'] ?? 'created_at';
        $dir = $filters['sort_dir'] ?? 'desc';

        return $query->orderBy($sort, $dir)
            ->paginate($filters['per_page'] ?? 15, ['*'], 'page', $filters['page'] ?? 1);
    }

    /**
     * Criar e configurar uma nova instância no backend e no provedor externo.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $data  Dados da instância (provider, name, token).
     * @return ChatInstance Modelo da instância criada e configurada.
     *
     * @throws ValidationException Se faltar o token para provedores que o exigem.
     * @throws RuntimeException Se houver falha na comunicação com o gateway.
     */
    public function create(string $tenantId, array $data): ChatInstance
    {
        $token = $this->normalizeToken(isset($data['token']) ? (string) $data['token'] : null);

        if (($data['provider'] ?? null) === 'uazapi' && $token === null) {
            throw ValidationException::withMessages([
                'token' => ['Token é obrigatório para canais Uazapi'],
            ]);
        }

        $webhookToken = $this->resolveWebhookToken($data, $token);

        return DB::transaction(function () use ($tenantId, $data, $webhookToken, $token): ChatInstance {
            $instance = new ChatInstance([
                'tenant_id' => $tenantId,
                'provider' => $data['provider'],
                'name' => $data['name'],
                'is_active' => $data['is_active'] ?? true,
                'evaluation_enabled' => (bool) ($data['evaluation_enabled'] ?? false),
                'evaluation_cutoff_score' => (int) ($data['evaluation_cutoff_score'] ?? 3),
                'webhook_token' => $webhookToken,
                'status' => 'disconnected',
                'settings_json' => $this->prepareSettings($data['settings'] ?? [], $token),
            ]);

            $instance->save();

            $webhookUrl = $this->buildWebhookUrl($instance);
            $response = $this->connector->configureWebhook($instance, $webhookUrl);

            if ($instance->provider === 'uazapi' && ! $response) {
                throw new RuntimeException('Falha ao configurar webhook da instância.');
            }

            $instance->settings_json = [
                ...($instance->settings_json ?? []),
                'webhook_url' => $webhookUrl,
                'webhook_response' => $response,
            ];
            $instance->save();

            return $instance;
        });
    }

    /**
     * Atualizar as configurações de uma instância.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da instância.
     * @param  array<string, mixed>  $data  Novos dados (name, is_active, settings).
     * @return ChatInstance Modelo atualizado.
     */
    public function update(string $tenantId, string $id, array $data): ChatInstance
    {
        $instance = $this->find($tenantId, $id);
        $token = $this->normalizeToken(isset($data['token']) ? (string) $data['token'] : null);

        $instance->name = $data['name'] ?? $instance->name;

        if (isset($data['is_active'])) {
            $instance->is_active = $data['is_active'];
        }

        if (array_key_exists('evaluation_enabled', $data)) {
            $instance->evaluation_enabled = (bool) $data['evaluation_enabled'];
        }

        if (array_key_exists('evaluation_cutoff_score', $data)) {
            $instance->evaluation_cutoff_score = max(1, min(5, (int) $data['evaluation_cutoff_score']));
        }

        if (isset($data['settings']) || isset($data['token'])) {
            $currentSettings = $instance->settings_json ?? [];
            $newSettings = $this->prepareSettings($data['settings'] ?? [], $token);
            $instance->settings_json = array_merge($currentSettings, $newSettings);

            if ($token !== null && $instance->provider === 'uazapi') {
                $instance->webhook_token = $token;
            }
        }

        $instance->save();

        return $instance;
    }

    /**
     * Remover uma instância do banco de dados local.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da instância.
     */
    public function delete(string $tenantId, string $id): void
    {
        $instance = $this->find($tenantId, $id);

        if ($this->isConnected($instance)) {
            throw new ConflictHttpException(
                'Não é possível excluir um canal conectado. Desconecte primeiro.'
            );
        }

        $instance->delete();
    }

    /**
     * Iniciar conexão junto ao provedor (Geração de QR Code ou Pairing Code).
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da instância.
     * @param  array<string, mixed>  $data  Dados de conexão (mode: qr|pair, phone).
     * @return array Resultado da conexão incluindo a instância e o payload de conexão (qr/pairing code).
     */
    public function connect(string $tenantId, string $id, array $data = []): array
    {
        $instance = $this->find($tenantId, $id);
        $mode = $data['mode'] ?? 'qr';

        $connection = $this->connector->connect($instance, $mode, $data['phone'] ?? null);

        $instance->status = $mode === 'pair' ? 'connecting' : 'qr';
        $instance->settings_json = [
            ...($instance->settings_json ?? []),
            'last_connection' => $connection,
        ];
        $instance->save();

        return [
            'instance' => $instance,
            'connection' => $connection,
        ];
    }

    /**
     * Desconectar e redefinir o status da instância para desconectado.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da instância.
     * @return ChatInstance Instância reiniciada.
     */
    public function disconnect(string $tenantId, string $id): ChatInstance
    {
        $instance = $this->find($tenantId, $id);
        $instance->status = 'disconnected';
        $instance->settings_json = [
            ...($instance->settings_json ?? []),
            'last_connection' => null,
        ];
        $instance->save();

        return $instance;
    }

    /**
     * Sincronizar e recuperar o status técnico da instância junto ao provedor.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da instância.
     * @return array Conjunto de dados com a instância e o status atualizado.
     */
    public function status(string $tenantId, string $id): array
    {
        $instance = $this->find($tenantId, $id);
        $connection = $instance->settings_json['last_connection'] ?? null;

        return [
            'instance' => $instance,
            'status' => $connection ?? [
                'mode' => 'qr',
                'qr_code' => $this->generateQrData($instance),
                'pair_code' => null,
                'expires_at' => now()->addMinutes(5)->toIso8601String(),
            ],
        ];
    }

    /**
     * Localizar instância garantindo isolamento de tenant.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da instância.
     * @return ChatInstance Modelo da instância.
     */
    public function find(string $tenantId, string $id): ChatInstance
    {
        return ChatInstance::where('tenant_id', $tenantId)->where('id', $id)->firstOrFail();
    }

    /**
     * Alternar se a instância está apta a receber e processar eventos (Habilitar/Desabilitar).
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da instância.
     * @return ChatInstance Instância com flag is_active alternada.
     */
    public function toggleActive(string $tenantId, string $id): ChatInstance
    {
        $instance = $this->find($tenantId, $id);
        $instance->is_active = ! $instance->is_active;
        $instance->save();

        return $instance;
    }

    /**
     * Determina se a instância está conectada.
     *
     * @param  ChatInstance  $instance  Instância a ser verificada.
     * @return bool True se estiver conectada.
     */
    public function isConnected(ChatInstance $instance): bool
    {
        $connectedStatuses = [
            'connected',
            'online',
            'ready',
            'open',
            'authorized',
            'authenticated',
            'authenticated_connected',
            'conectado',
        ];

        $status = strtolower((string) $instance->status);

        if (in_array($status, $connectedStatuses, true)) {
            return true;
        }

        $settings = $instance->settings_json ?? [];
        $lastConnection = $settings['last_connection'] ?? [];

        return ! empty($lastConnection['connected']) || ! empty($lastConnection['logged_in']);
    }

    /**
     * Alterar a imagem de perfil da instância.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da instância.
     * @param  string  $image  URL, base64 ou 'remove'.
     * @return array{instance: ChatInstance, response: array<string, mixed>} Resposta do gateway.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
     */
    public function profileImage(string $tenantId, string $id, string $image): array
    {
        $instance = $this->find($tenantId, $id);

        if (! $this->isConnected($instance)) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException(
                'A instância deve estar conectada para alterar a imagem do perfil.'
            );
        }

        $response = $this->connector->updateProfileImage($instance, $image);

        $settingsJson = is_array($instance->settings_json) ? $instance->settings_json : [];
        if (isset($response['profile']['profilePicUrl'])) {
            $settingsJson['profilePicUrl'] = $response['profile']['profilePicUrl'];
        } elseif (isset($response['profilePicUrl'])) {
            $settingsJson['profilePicUrl'] = $response['profilePicUrl'];
        }
        $instance->settings_json = $settingsJson;
        $instance->save();

        return ['instance' => $instance, 'response' => $response];
    }

    /**
     * Publicar status de presença da instância.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $id  Identificador UUID da instância.
     * @param  string  $presence  'available' ou 'unavailable'.
     * @return array{instance: ChatInstance, response: array<string, mixed>} Resposta do gateway.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException
     */
    public function presence(string $tenantId, string $id, string $presence): array
    {
        $instance = $this->find($tenantId, $id);

        if (! $this->isConnected($instance)) {
            throw new \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException(
                'A instância deve estar conectada para alterar a presença.'
            );
        }

        $response = $this->connector->updatePresence($instance, $presence);

        $settingsJson = is_array($instance->settings_json) ? $instance->settings_json : [];
        $settingsJson['current_presence'] = $presence;
        $instance->settings_json = $settingsJson;
        $instance->save();

        return ['instance' => $instance, 'response' => $response];
    }

    /**
     * Preparar array de settings incorporando o token se fornecido.
     *
     * @param  array<string, mixed>  $settings
     */
    private function prepareSettings(array $settings, ?string $token): array
    {
        if ($token !== null) {
            $settings['token'] = $token;
        }

        if (array_key_exists('channel_fallback_message', $settings)) {
            $settings['channel_fallback_message'] = $this->normalizeChannelFallbackMessage(
                $settings['channel_fallback_message']
            );
        }

        return $settings;
    }

    /**
     * Normaliza a mensagem de fallback por canal preservando comportamento legado.
     */
    private function normalizeChannelFallbackMessage(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Resolver qual token será utilizado para identificação de webhooks.
     *
     * @param  array<string, mixed>  $data
     * @return string Token único.
     */
    private function resolveWebhookToken(array $data, ?string $token): string
    {
        if (($data['provider'] ?? null) === 'uazapi' && $token !== null) {
            return $token;
        }

        return (string) Str::uuid();
    }

    /**
     * Normaliza e valida token informado em integrações.
     *
     * @throws ValidationException
     */
    private function normalizeToken(?string $token): ?string
    {
        if ($token === null) {
            return null;
        }

        $normalized = trim($token);
        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) > 255) {
            throw ValidationException::withMessages([
                'token' => ['Token inválido: tamanho máximo de 255 caracteres.'],
            ]);
        }

        if (str_contains($normalized, 'Application bundle generation failed')) {
            throw ValidationException::withMessages([
                'token' => ['Token inválido para canal. Verifique o valor informado.'],
            ]);
        }

        return $normalized;
    }

    /**
     * Montar a URL de endpoint pública para recepção de webhooks desta instância.
     *
     * @param  ChatInstance  $instance  Instância para contexto.
     * @return string URL completa do webhook.
     */
    private function buildWebhookUrl(ChatInstance $instance): string
    {
        $baseUrl = config('services.channels.webhook_base_url', config('app.url'));

        if (! $baseUrl) {
            throw new RuntimeException('Webhook base URL não configurada');
        }

        $provider = Str::slug($instance->provider);

        return rtrim($baseUrl, '/')."/webhooks/{$provider}/instances/{$instance->webhook_token}";
    }

    /**
     * Gerar payload de dados QR base64 para uso em placeholders (fallback).
     *
     * @param  ChatInstance  $instance  Instância para contexto.
     * @return string Payload base64.
     */
    private function generateQrData(ChatInstance $instance): string
    {
        $payload = json_encode([
            'instance_id' => $instance->id,
            'tenant_id' => $instance->tenant_id,
            'provider' => $instance->provider,
            'generated_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR);

        return 'data:application/json;base64,'.base64_encode($payload);
    }
}
