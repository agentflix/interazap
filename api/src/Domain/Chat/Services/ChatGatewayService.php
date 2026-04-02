<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Platform\Services\UazapiGatewayService;
use Domain\Shared\Infrastructure\Gateway\GatewayHttpClient;
use InvalidArgumentException;

/**
 * Abstração do Gateway de Comunicação (Chat).
 *
 * Atua como uma fachada simplificada para o serviço Uazapi, centralizando
 * as operações de envio de mensagens, mídias, presença e controle de leitura.
 *
 * @category Services
 */
class ChatGatewayService
{
    /**
     * @param  UazapiGatewayService  $uazapi  Implementação específica do gateway para o provedor Uazapi.
     */
    public function __construct(
        private readonly UazapiGatewayService $uazapi,
        private readonly GatewayHttpClient $gateway,
    ) {
        //
    }

    /**
     * Enviar mensagem outbound via gateway usando o provedor especificado.
     *
     * @param  string  $provider  Provedor (ex: zapi).
     * @param  string  $instanceToken  Token da instância (ex: instanceId:tokenId).
     * @param  array<string, mixed>  $payload  Dados da mensagem (type, to, text/media, etc).
     * @return array<string, mixed> Resposta do gateway.
     */
    public function sendOutboundMessage(
        string $provider,
        string $instanceToken,
        string $tenantId,
        string $instanceId,
        array $payload,
    ): array {
        if ($provider !== 'zapi') {
            throw new InvalidArgumentException("Outbound provider not supported: {$provider}");
        }

        $response = $this->gateway->post('/outbound/send', [
            'provider' => $provider,
            'instanceToken' => $instanceToken,
            'tenantId' => $tenantId,
            'instanceId' => $instanceId,
            ...$payload,
        ]);

        if (array_key_exists('messageId', $response)) {
            $response['messageid'] = $response['messageId'];
        }

        return $response;
    }

    /**
     * Enviar uma mensagem de texto simples.
     *
     * @param  string  $token  Token da instância de conexão.
     * @param  array<string, mixed>  $payload  Dados da mensagem (número e texto).
     * @return array<string, mixed> Resposta do provedor externo.
     */
    public function sendText(string $token, array $payload): array
    {
        return $this->uazapi->sendText($token, $payload);
    }

    /**
     * Enviar um arquivo ou imagem.
     *
     * @param  string  $token  Token da instância de conexão.
     * @param  array<string, mixed>  $payload  Dados do arquivo (número, base64/url, legenda).
     * @return array<string, mixed> Resposta do provedor externo.
     */
    public function sendFile(string $token, array $payload): array
    {
        // Uazapi usa o mesmo endpoint de arquivo para áudio
        return $this->uazapi->sendFile($token, $payload);
    }

    /**
     * Enviar um arquivo de áudio ou gravação de voz.
     *
     * @param  string  $token  Token da instância de conexão.
     * @param  array<string, mixed>  $payload  Dados do áudio.
     * @return array<string, mixed> Resposta do provedor externo.
     */
    public function sendAudio(string $token, array $payload): array
    {
        // Uazapi usa o mesmo endpoint de arquivo para áudio
        return $this->uazapi->sendFile($token, $payload);
    }

    /**
     * Envia atualização de presença (digitando/gravando) para um contato.
     *
     * @param  string  $token  Token da instância de conexão.
     * @param  array<string, mixed>  $payload  ['number' => string, 'presence' => 'composing'|'recording'|'paused', 'delay' => int (ms)]
     * @return array<string, mixed> Resposta do provedor externo.
     */
    public function sendPresence(string $token, array $payload): array
    {
        return $this->uazapi->sendPresence($token, $payload);
    }

    /**
     * Marca um chat como lido/não lido no dispositivo remoto.
     *
     * @param  string  $token  Token da instância de conexão.
     * @param  array<string, mixed>  $payload  ['number' => string, 'read' => boolean]
     * @return array<string, mixed> Resposta do provedor externo.
     */
    public function markAsRead(string $token, array $payload): array
    {
        return $this->uazapi->markAsRead($token, $payload);
    }

    /**
     * Enviar cartão de contato (vCard).
     *
     * @param  string  $token  Token da instância de conexão.
     * @param  array<string, mixed>  $payload  { number, fullName, phoneNumber, organization?, email?, url? }
     * @return array<string, mixed> Resposta do provedor externo.
     */
    public function sendContact(string $token, array $payload): array
    {
        return $this->uazapi->sendContact($token, $payload);
    }

    /**
     * Enviar localização geográfica.
     *
     * @param  string  $token  Token da instância de conexão.
     * @param  array<string, mixed>  $payload  { number, latitude, longitude, name?, address? }
     * @return array<string, mixed> Resposta do provedor externo.
     */
    public function sendLocation(string $token, array $payload): array
    {
        return $this->uazapi->sendLocation($token, $payload);
    }

    /**
     * Reagir a uma mensagem com emoji.
     *
     * @param  string  $token  Token da instância de conexão.
     * @param  array<string, mixed>  $payload  { number, id, text (emoji ou vazio para remover) }
     * @return array<string, mixed> Resposta do provedor externo.
     */
    public function reactToMessage(string $token, array $payload): array
    {
        return $this->uazapi->reactToMessage($token, $payload);
    }

    /**
     * Editar uma mensagem enviada.
     *
     * @param  string  $token  Token da instância de conexão.
     * @param  array<string, mixed>  $payload  { id, text }
     * @return array<string, mixed> Resposta do provedor externo.
     */
    public function editMessage(string $token, array $payload): array
    {
        return $this->uazapi->editMessage($token, $payload);
    }

    /**
     * Enviar template de mensagem.
     *
     * @param  string  $token  Token da instância de conexão.
     * @param  array<string, mixed>  $payload  Dados do template.
     * @return array<string, mixed> Resposta do provedor externo.
     */
    public function sendTemplate(string $token, array $payload): array
    {
        return $this->uazapi->sendTemplate($token, $payload);
    }

    /**
     * Excluir uma mensagem para todos.
     *
     * @param  string  $token  Token da instância de conexão.
     * @param  array<string, mixed>  $payload  { id }
     * @return array<string, mixed> Resposta do provedor externo.
     */
    public function deleteMessage(string $token, array $payload): array
    {
        return $this->uazapi->deleteMessage($token, $payload);
    }

    /**
     * Baixar mídia de uma mensagem.
     *
     * @param  string  $token  Token da instância de conexão.
     * @param  string  $messageId  ID da mensagem contendo a mídia.
     * @return array<string, mixed> Resposta com fileURL, mimetype etc.
     */
    public function downloadMedia(string $token, string $messageId): array
    {
        return $this->uazapi->downloadMedia($token, [
            'id' => $messageId,
            'return_link' => true,
            'return_base64' => false,
            'generate_mp3' => false,
            'transcribe' => false,
            'openai_apikey' => null,
            'download_quoted' => false,
        ]);
    }
}
