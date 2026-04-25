<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Chat\Models\ChatInstance;
use Domain\Platform\Services\UazapiGatewayService;
use Domain\Shared\Infrastructure\Gateway\GatewayHttpClient;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Conector de canal de chat multi-provedor.
 *
 * Gerencia o ciclo de vida da conexão com provedores externos (ex: Uazapi),
 * incluindo geração de QR Codes, pareamento por código e configuração de Webhooks.
 *
 * @category Services
 */
class ChatChannelConnector
{
    /**
     * Inicializa o conector com o serviço de gateway.
     *
     * @param  UazapiGatewayService  $gateway  Serviço de abstração do provedor Uazapi.
     * @param  GatewayHttpClient  $gatewayHttp  Client HTTP para comunicação com o Gateway.
     */
    public function __construct(
        private readonly UazapiGatewayService $gateway,
        private readonly GatewayHttpClient $gatewayHttp,
    ) {}

    /**
     * Estabelecer uma nova conexão com a instância de chat.
     *
     * Inicia o processo de pareamento, solicitando ao gateway os dados necessários
     * para exibir o QR Code ou código de pareamento conforme o modo selecionado.
     *
     * @param  ChatInstance  $instance  A instância que será conectada.
     * @param  string  $mode  Modo de conexão ('qr' ou 'pair').
     * @param  string|null  $phone  Número de telefone (obrigatório para modo 'pair').
     * @return array{mode:string,qr_code:?string,pair_code:?string,expires_at:string,provider:string,phone?:?string} Dados normalizados da conexão.
     *
     * @throws RuntimeException Caso o provedor não seja suportado ou token esteja inválido.
     */
    public function connect(ChatInstance $instance, string $mode, ?string $phone = null): array
    {
        if ($instance->provider === 'telegram') {
            return $this->connectTelegram($instance);
        }

        $mode = $mode === 'pair' ? 'pair' : 'qr';

        $connection = $this->connectUazapi($instance, $mode, $phone);
        if ($connection === null) {
            throw new RuntimeException('Conector não suportado ou token ausente.');
        }

        return $connection;
    }

    /**
     * Tenta realizar a conexão especificamente via provedor Uazapi.
     *
     * Valida se a instância pertence ao provedor e se possui o token configurado
     * antes de disparar a chamada ao gateway.
     *
     * @param  ChatInstance  $instance  Instância alvo.
     * @param  string  $mode  Modo 'qr' ou 'pair'.
     * @param  string|null  $phone  Telefone para modo pair.
     * @return array{mode:string,qr_code:?string,pair_code:?string,expires_at:string,provider:string,phone:?string}|null Dados da conexão ou null se não aplicável.
     *
     * @throws \Throwable Repassa exceções do gateway.
     */
    private function connectUazapi(ChatInstance $instance, string $mode, ?string $phone): ?array
    {
        if ($instance->provider !== 'uazapi') {
            return null;
        }

        $token = $instance->settings_json['token'] ?? null;
        if ($token === null) {
            return null;
        }

        try {
            $payload = [
                'mode' => $mode,
                'phone' => $mode === 'pair' ? ($phone ? preg_replace('/\D+/', '', $phone) : null) : null,
            ];

            $response = $this->gateway->connectInstance($token, array_filter($payload, fn ($v) => $v !== null));

            $connection = $this->mapGatewayConnection($response, $mode, $phone);

            if ($connection['qr_code'] === null && $connection['pair_code'] === null) {
                throw new RuntimeException('Gateway não retornou QR code nem código de pareamento.');
            }

            return $connection;
        } catch (\Throwable $e) {
            Log::warning('channel.uazapi.connect_failed', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Configurar a URL de Webhook na instância remota.
     *
     * Atualiza as configurações do gateway para garantir que eventos de mensagens
     * e conexão sejam encaminhados para a URL especificada.
     *
     * @param  ChatInstance  $instance  A instância alvo.
     * @param  string  $webhookUrl  URL completa que receberá os POSTs.
     * @return array<string, mixed>|null Retorno bruto do gateway ou null se não suportado.
     *
     * @throws \Throwable Caso a chamada ao gateway falhe.
     */
    public function configureWebhook(ChatInstance $instance, string $webhookUrl): ?array
    {
        if ($instance->provider !== 'uazapi') {
            return null;
        }

        $token = $instance->settings_json['token'] ?? null;
        if ($token === null) {
            return null;
        }

        try {
            return $this->gateway->configureWebhook($token, [
                'enabled' => true,
                'url' => $webhookUrl,
                'events' => ['connection', 'messages', 'messages_update'],
                'action' => 'add',
                'excludeMessages' => ['wasSentByApi'],
            ]);
        } catch (\Throwable $e) {
            Log::warning('channel.uazapi.webhook_failed', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Atualizar a imagem de perfil da instância.
     *
     * @param  ChatInstance  $instance  A instância alvo.
     * @param  string  $image  URL, base64 ou 'remove'.
     * @return array<string, mixed> Resposta do gateway.
     */
    public function updateProfileImage(ChatInstance $instance, string $image): array
    {
        if ($instance->provider !== 'uazapi') {
            return ['error' => 'Provider not supported'];
        }

        $token = $instance->settings_json['token'] ?? null;
        if ($token === null) {
            return ['error' => 'Token not configured'];
        }

        return $this->gateway->updateProfileImage($token, $image);
    }

    /**
     * Publicar status de presença da instância.
     *
     * @param  ChatInstance  $instance  A instância alvo.
     * @param  string  $presence  'available' ou 'unavailable'.
     * @return array<string, mixed> Resposta do gateway.
     */
    public function updatePresence(ChatInstance $instance, string $presence): array
    {
        if ($instance->provider !== 'uazapi') {
            return ['error' => 'Provider not supported'];
        }

        $token = $instance->settings_json['token'] ?? null;
        if ($token === null) {
            return ['error' => 'Token not configured'];
        }

        return $this->gateway->updatePresence($token, $presence);
    }

    /**
     * Desconectar uma instância de chat do provedor externo.
     *
     * @param  ChatInstance  $instance  A instância que será desconectada.
     * @return array{status:string} Dados da desconexão.
     *
     * @throws RuntimeException Caso o provedor não seja suportado.
     */
    public function disconnect(ChatInstance $instance): array
    {
        if ($instance->provider === 'telegram') {
            return $this->disconnectTelegram($instance);
        }

        if ($instance->provider === 'uazapi') {
            $token = $instance->settings_json['token'] ?? null;
            if ($token !== null) {
                $this->gateway->disconnectInstance($token);
            }

            return ['status' => 'disconnected'];
        }

        throw new RuntimeException("Provedor '{$instance->provider}' não suportado para desconexão.");
    }

    /**
     * Conectar instância Telegram via Bot API.
     *
     * Valida o bot_token via getMe, configura o webhook no Gateway e atualiza
     * as settings da instância com bot_id, bot_username e webhook_secret.
     *
     * @param  ChatInstance  $instance  Instância Telegram a conectar.
     * @return array{status:string,bot_username:?string,bot_id:?int} Dados da conexão.
     *
     * @throws RuntimeException Se bot_token estiver ausente ou inválido.
     */
    private function connectTelegram(ChatInstance $instance): array
    {
        $botToken = $instance->settings_json['bot_token'] ?? null;
        if ($botToken === null || $botToken === '') {
            throw new RuntimeException('Bot token ausente para instância Telegram.');
        }

        try {
            $botInfo = $this->gatewayHttp->post('/telegram/validate-token', [
                'bot_token' => $botToken,
            ]);
        } catch (\Throwable $e) {
            Log::warning('channel.telegram.validate_token_failed', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Falha ao validar bot token Telegram: '.$e->getMessage(), 0, $e);
        }

        if (empty($botInfo['id']) || empty($botInfo['username'])) {
            throw new RuntimeException('Resposta inválida do Telegram ao validar bot token.');
        }

        $webhookSecret = Str::random(256);
        $webhookUrl = rtrim((string) config('services.gateway.url'), '/')
            .'/webhooks/telegram/'.$instance->webhook_token;

        try {
            $this->gatewayHttp->post('/telegram/set-webhook', [
                'bot_token' => $botToken,
                'webhook_url' => $webhookUrl,
                'webhook_secret' => $webhookSecret,
            ]);
        } catch (\Throwable $e) {
            Log::warning('channel.telegram.set_webhook_failed', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
            ]);

            throw new RuntimeException('Falha ao configurar webhook Telegram: '.$e->getMessage(), 0, $e);
        }

        $instance->settings_json = [
            ...($instance->settings_json ?? []),
            'bot_id' => $botInfo['id'],
            'bot_username' => $botInfo['username'],
            'webhook_secret' => $webhookSecret,
        ];
        $instance->status = 'connected';
        $instance->save();

        return [
            'status' => 'connected',
            'bot_username' => $botInfo['username'],
            'bot_id' => $botInfo['id'],
        ];
    }

    /**
     * Desconectar instância Telegram removendo o webhook.
     *
     * @param  ChatInstance  $instance  Instância Telegram a desconectar.
     * @return array{status:string} Confirmação da desconexão.
     */
    private function disconnectTelegram(ChatInstance $instance): array
    {
        $botToken = $instance->settings_json['bot_token'] ?? null;

        if ($botToken !== null && $botToken !== '') {
            try {
                $this->gatewayHttp->post('/telegram/delete-webhook', [
                    'bot_token' => $botToken,
                ]);
            } catch (\Throwable $e) {
                Log::warning('channel.telegram.delete_webhook_failed', [
                    'instance_id' => $instance->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $settingsJson = $instance->settings_json ?? [];
        unset($settingsJson['webhook_secret']);
        $instance->settings_json = $settingsJson;
        $instance->status = 'disconnected';
        $instance->save();

        return ['status' => 'disconnected'];
    }

    /**
     * Normalizar a resposta do gateway para o formato interno.
     *
     * @param  array<string, mixed>  $response  Resposta bruta do gateway.
     * @param  string  $mode  Modo solicitado.
     * @param  string|null  $phone  Telefone utilizado.
     * @return array{mode:string,qr_code:?string,pair_code:?string,expires_at:string,provider:string,phone:?string}
     */
    private function mapGatewayConnection(array $response, string $mode, ?string $phone): array
    {
        $payload = $response['connection'] ?? $response;
        $qr = $this->extractQr($payload);
        $pair = $this->extractPairCode($payload);

        return [
            'mode' => $mode,
            'qr_code' => $qr,
            'pair_code' => $pair,
            'expires_at' => $this->extractExpiresAt($payload),
            'provider' => 'uazapi',
            'phone' => $phone,
        ];
    }

    /**
     * Extrair QR Code de múltiplos formatos possíveis de resposta.
     *
     * @param  array<string, mixed>  $response  Payload de dados do gateway.
     * @return string|null String do QR code (base64 ou URL) ou null.
     */
    private function extractQr(array $response): ?string
    {
        $candidates = [
            $response['instance']['qrcode'] ?? null,
            $response['instance']['qrCode'] ?? null,
            $response['qr_code'] ?? null,
            $response['qrcode'] ?? null,
            $response['qr'] ?? null,
            $response['qrcode_base64'] ?? null,
            $response['qr_base64'] ?? null,
            Arr::get($response, 'instance.qrcode'),
            Arr::get($response, 'data.qrcode'),
        ];

        foreach ($candidates as $qr) {
            if (! $qr) {
                continue;
            }

            if (str_starts_with($qr, 'http')) {
                return $qr;
            }

            if (str_starts_with($qr, 'data:image')) {
                return $qr;
            }

            return 'data:image/png;base64,'.ltrim($qr, 'data:image/png;base64,');
        }

        return null;
    }

    /**
     * Extrair código de pareamento de múltiplos formatos.
     *
     * @param  array<string, mixed>  $response  Payload de dados do gateway.
     * @return string|null Código de pareamento de 8 dígitos ou null.
     */
    private function extractPairCode(array $response): ?string
    {
        return Arr::get($response, 'instance.paircode')
            ?? Arr::get($response, 'instance.pairCode')
            ?? $response['pair_code']
            ?? $response['pairCode']
            ?? $response['code']
            ?? Arr::get($response, 'data.code')
            ?? null;
    }

    /**
     * Definir data de expiração do código de conexão.
     *
     * @param  array<string, mixed>  $response  Payload de dados do gateway.
     * @return string Data ISO8601 de expiração.
     */
    private function extractExpiresAt(array $response): string
    {
        return $response['expires_at'] ?? $response['expiration'] ?? now()->addMinutes(5)->toIso8601String();
    }
}
