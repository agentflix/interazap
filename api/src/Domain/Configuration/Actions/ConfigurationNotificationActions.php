<?php

declare(strict_types=1);

namespace Domain\Configuration\Actions;

use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Models\ConfigurationNotification;
use Domain\Configuration\Models\ConfigurationNotificationPreference;
use Domain\Configuration\Models\ConfigurationPushSubscription;

/**
 * Casos de uso para Notificações e Preferências.
 *
 * Centraliza a lógica de leitura, marcação de leitura e persistência de
 * configurações de canais para o usuário autenticado.
 *
 * @category Actions
 */
final class ConfigurationNotificationActions
{
    /**
     * Criar um registro de notificação para um usuário/canal.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $userId  Identificador do destinatário.
     * @param  string  $type  Tipo da notificação.
     * @param  string  $title  Título exibido.
     * @param  string  $body  Mensagem principal.
     * @param  string  $channel  Canal alvo (ui|email|push|whatsapp|webhook).
     * @param  array<string, mixed>  $data  Payload adicional.
     * @param  string  $status  Status inicial (pending|sent|failed|read).
     * @return ConfigurationNotification Registro persistido.
     */
    public function create(
        string $tenantId,
        string $userId,
        string $type,
        string $title,
        string $body,
        string $channel,
        array $data = [],
        string $status = ConfigurationNotification::STATUS_PENDING,
    ): ConfigurationNotification {
        return ConfigurationNotification::query()->create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'channel' => $channel,
            'status' => $status,
        ]);
    }

    /**
     * Listar notificações não lidas para o usuário autenticado.
     *
     * @param  AuthUser  $user  Instância do usuário.
     * @param  int  $limit  Limite de registros.
     * @return \Illuminate\Database\Eloquent\Collection<int, ConfigurationNotification> Coleção de notificações.
     */
    public function listUnread(AuthUser $user, int $limit = 20)
    {
        $tenantId = $this->tenantId($user);

        return ConfigurationNotification::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->unread()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Contar o total de notificações não lidas.
     *
     * @param  AuthUser  $user  Instância do usuário.
     * @return int Total de registros com status 'unread'.
     */
    public function unreadCount(AuthUser $user): int
    {
        $tenantId = $this->tenantId($user);

        return ConfigurationNotification::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->unread()
            ->count();
    }

    /**
     * Marcar uma notificação individual como lida.
     *
     * @param  AuthUser  $user  Instância do usuário.
     * @param  string  $notificationId  Identificador UUID da notificação.
     * @return bool True se marcada com sucesso, false caso não exista.
     */
    public function markAsRead(AuthUser $user, string $notificationId): bool
    {
        $tenantId = $this->tenantId($user);

        $notification = ConfigurationNotification::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('id', $notificationId)
            ->first();

        if (! $notification) {
            return false;
        }

        $notification->markAsRead();

        return true;
    }

    /**
     * Marcar todas as notificações do usuário como lidas em lote.
     *
     * @param  AuthUser  $user  Instância do usuário.
     * @return int Quantidade de registros afetados.
     */
    public function markAllAsRead(AuthUser $user): int
    {
        $tenantId = $this->tenantId($user);

        return ConfigurationNotification::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->unread()
            ->update([
                'status' => ConfigurationNotification::STATUS_READ,
                'read_at' => now(),
            ]);
    }

    /**
     * Obter todas as preferências configuradas ou instanciar defaults.
     *
     * @param  AuthUser  $user  Instância do usuário.
     * @return array<string, ConfigurationNotificationPreference> Mapa de preferências por tipo.
     */
    public function getAllPreferences(AuthUser $user): array
    {
        $tenantId = $this->tenantId($user);

        $existing = ConfigurationNotificationPreference::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('notification_type');

        $preferences = [];
        foreach (ConfigurationNotificationPreference::TYPES as $type => $label) {
            if ($existing->has($type)) {
                $preferences[$type] = $existing->get($type);

                continue;
            }

            $preferences[$type] = new ConfigurationNotificationPreference([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'notification_type' => $type,
                'channels' => ['ui'],
                'enabled' => true,
            ]);
        }

        return $preferences;
    }

    /**
     * Atualizar ou criar a preferência para um tipo de notificação.
     *
     * @param  AuthUser  $user  Instância do usuário.
     * @param  string  $type  Tipo da notificação.
     * @param  list<string>  $channels  Canais ativos.
     * @param  bool  $enabled  Status de ativação.
     * @param  ?string  $quietStart  Início do horário de silêncio.
     * @param  ?string  $quietEnd  Fim do horário de silêncio.
     * @return ConfigurationNotificationPreference Preferência atualizada.
     */
    public function updatePreference(
        AuthUser $user,
        string $type,
        array $channels,
        bool $enabled = true,
        ?string $quietStart = null,
        ?string $quietEnd = null,
    ): ConfigurationNotificationPreference {
        $tenantId = $this->tenantId($user);

        return ConfigurationNotificationPreference::updateOrCreate(
            ['user_id' => $user->id, 'notification_type' => $type],
            [
                'tenant_id' => $tenantId,
                'channels' => $channels,
                'enabled' => $enabled,
                'quiet_start' => $quietStart,
                'quiet_end' => $quietEnd,
            ]
        );
    }

    /**
     * Atualizar múltiplas preferências de uma só vez.
     *
     * @param  AuthUser  $user  Instância do usuário.
     * @param  array<int, array<string, mixed>>  $preferences  Lista de objetos de preferência.
     * @return array<int, ConfigurationNotificationPreference> Lista de registros atualizados.
     */
    public function updateAllPreferences(AuthUser $user, array $preferences): array
    {
        $updated = [];

        foreach ($preferences as $pref) {
            if (! isset($pref['type']) || ! array_key_exists($pref['type'], ConfigurationNotificationPreference::TYPES)) {
                continue;
            }

            $updated[] = $this->updatePreference(
                user: $user,
                type: (string) $pref['type'],
                channels: $pref['channels'] ?? ['ui'],
                enabled: (bool) ($pref['enabled'] ?? true),
                quietStart: $pref['quiet_start'] ?? null,
                quietEnd: $pref['quiet_end'] ?? null,
            );
        }

        return $updated;
    }

    /**
     * Registrar ou atualizar assinatura de push web do usuário.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  string  $endpoint  Endpoint da assinatura no navegador.
     * @param  string  $p256dh  Chave pública do assinante.
     * @param  string  $auth  Chave de autenticação.
     * @param  string  $contentEncoding  Encoding da assinatura (ex: aes128gcm).
     * @return ConfigurationPushSubscription Assinatura persistida.
     */
    public function upsertPushSubscription(
        AuthUser $user,
        string $endpoint,
        string $p256dh,
        string $auth,
        string $contentEncoding = 'aes128gcm',
    ): ConfigurationPushSubscription {
        $tenantId = $this->tenantId($user);

        return ConfigurationPushSubscription::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'endpoint' => $endpoint,
            ],
            [
                'p256dh' => $p256dh,
                'auth' => $auth,
                'content_encoding' => $contentEncoding,
                'is_active' => true,
                'last_seen_at' => now(),
            ],
        );
    }

    /**
     * Desativar assinatura push web por endpoint.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  string  $endpoint  Endpoint da assinatura a desativar.
     * @return bool True quando assinatura foi localizada.
     */
    public function deactivatePushSubscription(AuthUser $user, string $endpoint): bool
    {
        $tenantId = $this->tenantId($user);

        $subscription = ConfigurationPushSubscription::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->where('endpoint', $endpoint)
            ->first();

        if ($subscription === null) {
            return false;
        }

        $subscription->update([
            'is_active' => false,
        ]);

        return true;
    }

    private function tenantId(AuthUser $user): string
    {
        return $user->tenant_id ?? (string) config('app.default_tenant_id');
    }
}
