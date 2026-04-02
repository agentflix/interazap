<?php

declare(strict_types=1);

namespace Domain\Platform\Services;

use Domain\Shared\Infrastructure\Gateway\GatewayHttpClient;

/**
 * Serviço de orquestração da integração Uazapi.
 *
 * Age como fachada para o domínio, delegando I/O ao adapter HTTP
 * para facilitar portabilidade futura (Gateway ↔ Backend).
 *
 * @category Services
 */
class UazapiGatewayService
{
    public function __construct(private readonly GatewayHttpClient $gateway) {}

    /**
     * Solicitar a criação de nova instância no provider.
     *
     * @param  array<string, mixed>  $payload  Configurações iniciais da instância.
     * @return array Resposta do gateway contendo dados da instância criada.
     */
    public function initInstance(array $payload): array
    {
        return $this->gateway->post('/uazapi/instances', $payload);
    }

    /**
     * Listar todas as instâncias disponíveis no gateway.
     *
     * @return array Lista de instâncias.
     */
    public function listInstances(): array
    {
        return $this->gateway->get('/uazapi/instances');
    }

    /**
     * Iniciar processo de conexão de uma instância.
     *
     * @param  string  $token  Token identificador da instância.
     * @param  array<string, mixed>  $payload  Dados opcionais (ex: mode, phone).
     * @return array Dados para exibição do QR Code ou Pair Code.
     */
    public function connectInstance(string $token, array $payload = []): array
    {
        return $this->gateway->post("/uazapi/instances/{$token}/connect", $payload);
    }

    /**
     * Desconectar (logout) a sessão da instância.
     *
     * @param  string  $token  Token da instância.
     * @return array Confirmação de desconexão.
     */
    public function disconnectInstance(string $token): array
    {
        return $this->gateway->post("/uazapi/instances/{$token}/disconnect");
    }

    /**
     * Remover a instância do gateway.
     *
     * @param  string  $token  Token da instância.
     * @return array Confirmação de remoção.
     */
    public function deleteInstance(string $token): array
    {
        return $this->gateway->post("/uazapi/instances/{$token}/delete");
    }

    /**
     * Consultar o status atual da conexão da instância.
     *
     * @param  string  $token  Token da instância.
     * @return array Dados do status (ex: connected, disconnected).
     */
    public function status(string $token): array
    {
        return $this->gateway->get("/uazapi/instances/{$token}/status");
    }

    /**
     * Configurar Webhook para uma instância.
     *
     * Define onde o gateway deve entregar os eventos recebidos.
     *
     * @param  string  $token  Token da instância.
     * @param  array<string, mixed>  $payload  Configurações do webhook (url, events, etc).
     * @return array Confirmação de configuração.
     */
    public function configureWebhook(string $token, array $payload): array
    {
        return $this->gateway->post("/uazapi/instances/{$token}/webhook", $payload);
    }

    /**
     * Enviar mensagem de texto.
     *
     * @param  string  $token  Token da instância emissora.
     * @param  array<string, mixed>  $payload  Dados da mensagem (to, body, etc).
     * @return array Resposta do envio.
     */
    public function sendText(string $token, array $payload): array
    {
        return $this->gateway->post('/send/text', $payload, $this->tokenHeaders($token));
    }

    /**
     * Sync contacts list.
     *
     * @param  array<int, array{number:string,name:string}>  $contacts
     * @return array<string, mixed>
     */
    public function syncContactsList(string $token, array $contacts): array
    {
        return $this->gateway->post("/uazapi/instances/{$token}/contacts/list", ['contacts' => $contacts]);
    }

    /**
     * Enviar arquivo/mídia.
     *
     * @param  string  $token  Token da instância emissora.
     * @param  array<string, mixed>  $payload  Dados do arquivo (to, url, caption, etc).
     * @return array Resposta do envio.
     */
    public function sendFile(string $token, array $payload): array
    {
        return $this->gateway->post('/send/file', $payload, $this->tokenHeaders($token));
    }

    /**
     * Enviar estado de presença (compondo, gravando).
     *
     * @param  string  $token  Token da instância.
     * @param  array<string, mixed>  $payload  Dados de presença (to, state).
     * @return array Resposta da ação.
     */
    public function sendPresence(string $token, array $payload): array
    {
        return $this->gateway->post('/message/presence', $payload, $this->tokenHeaders($token));
    }

    /**
     * Marcar chat/mensagens como lidas.
     *
     * @param  string  $token  Token da instância.
     * @param  array<string, mixed>  $payload  Identificação do chat/mensagem.
     * @return array Confirmação.
     */
    public function markAsRead(string $token, array $payload): array
    {
        return $this->gateway->post('/chat/read', $payload, $this->tokenHeaders($token));
    }

    /**
     * Listar contatos da agenda da instância.
     *
     * @param  string  $token  Token da instância.
     * @param  array<string, mixed>  $payload  Filtros opcionais.
     * @return array<int, mixed>|array<string, mixed> Lista de contatos.
     */
    public function listContacts(string $token, array $payload = []): array
    {
        if ($payload === []) {
            return $this->gateway->get("/uazapi/instances/{$token}/contacts");
        }

        return $this->gateway->post("/uazapi/instances/{$token}/contacts/list", $payload);
    }

    /**
     * Adicionar um novo contato à agenda.
     *
     * @param  string  $token  Token da instância.
     * @param  array<string, mixed>  $payload  Dados do contato (phone, name).
     * @return array Dados do contato criado.
     */
    public function addContact(string $token, array $payload): array
    {
        return $this->gateway->post("/uazapi/instances/{$token}/contacts", $payload);
    }

    /**
     * Remover um contato da agenda.
     *
     * @param  string  $token  Token da instância.
     * @param  array<string, mixed>  $payload  Dados para identificação do contato.
     * @return array Confirmação de remoção.
     */
    public function removeContact(string $token, array $payload): array
    {
        return $this->gateway->post("/uazapi/instances/{$token}/contacts/remove", $payload);
    }

    /**
     * Enviar cartão de contato (vCard).
     *
     * @param  string  $token  Token da instância.
     * @param  array<string, mixed>  $payload  { number, fullName, phoneNumber, organization?, email?, url? }
     * @return array<string, mixed> Resposta do envio.
     */
    public function sendContact(string $token, array $payload): array
    {
        return $this->gateway->post('/send/contact', $payload, $this->tokenHeaders($token));
    }

    /**
     * Enviar localização geográfica.
     *
     * @param  string  $token  Token da instância.
     * @param  array<string, mixed>  $payload  { number, latitude, longitude, name?, address? }
     * @return array<string, mixed> Resposta do envio.
     */
    public function sendLocation(string $token, array $payload): array
    {
        return $this->gateway->post('/send/location', $payload, $this->tokenHeaders($token));
    }

    /**
     * Reagir a uma mensagem com emoji.
     *
     * @param  string  $token  Token da instância.
     * @param  array<string, mixed>  $payload  { number, id, text (emoji ou vazio para remover) }
     * @return array<string, mixed> Resposta da ação.
     */
    public function reactToMessage(string $token, array $payload): array
    {
        return $this->gateway->post('/message/react', $payload, $this->tokenHeaders($token));
    }

    /**
     * Editar uma mensagem enviada.
     *
     * @param  string  $token  Token da instância.
     * @param  array<string, mixed>  $payload  { id, text }
     * @return array<string, mixed> Resposta da edição.
     */
    public function editMessage(string $token, array $payload): array
    {
        return $this->gateway->post('/message/edit', $payload, $this->tokenHeaders($token));
    }

    /**
     * Enviar template de mensagem.
     *
     * @param  string  $token  Token da instância.
     * @param  array<string, mixed>  $payload  { number, templateId, language, components }
     * @return array<string, mixed> Resposta do envio.
     */
    public function sendTemplate(string $token, array $payload): array
    {
        return $this->gateway->post('/send/template', $payload, $this->tokenHeaders($token));
    }

    /**
     * Excluir uma mensagem para todos.
     *
     * @param  string  $token  Token da instância.
     * @param  array<string, mixed>  $payload  { id }
     * @return array<string, mixed> Confirmação de exclusão.
     */
    public function deleteMessage(string $token, array $payload): array
    {
        return $this->gateway->post('/message/delete', $payload, $this->tokenHeaders($token));
    }

    /**
     * Baixar mídia de uma mensagem.
     *
     * @param  string  $token  Token da instância.
     * @param  array<string, mixed>  $payload  { id, return_link?, return_base64?, generate_mp3? }
     * @return array<string, mixed> Resposta com fileURL, mimetype, base64Data etc.
     */
    public function downloadMedia(string $token, array $payload): array
    {
        return $this->gateway->post('/message/download', $payload, $this->tokenHeaders($token));
    }

    /**
     * Atualizar a imagem de perfil da instância.
     *
     * @param  string  $token  Token da instância.
     * @param  string  $image  URL, base64 ou 'remove'.
     * @return array<string, mixed> Resposta da alteração de imagem.
     */
    public function updateProfileImage(string $token, string $image): array
    {
        return $this->gateway->post('/profile/image', ['image' => $image], $this->tokenHeaders($token));
    }

    /**
     * Publicar status de presença da instância (available/unavailable).
     *
     * @param  string  $token  Token da instância.
     * @param  string  $presence  'available' ou 'unavailable'.
     * @return array<string, mixed> Resposta da alteração de presença.
     */
    public function updatePresence(string $token, string $presence): array
    {
        return $this->gateway->post('/instance/presence', ['presence' => $presence], $this->tokenHeaders($token));
    }

    /**
     * @return array<string, string>
     */
    private function tokenHeaders(string $token): array
    {
        return ['token' => $token];
    }
}
