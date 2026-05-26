<?php

declare(strict_types=1);

namespace Domain\Shared\Infrastructure\WhatsApp\Adapters;

use Carbon\Carbon;
use Domain\Chat\Contracts\WhatsAppProviderPort;
use Domain\Chat\DTOs\ProviderMessageDTO;
use Domain\Chat\DTOs\ProviderStatusDTO;
use Domain\Chat\DTOs\SendMediaPayloadDTO;
use Domain\Chat\DTOs\SendTextPayloadDTO;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Adapter falso para uso em testes automatizados.
 *
 * Simula o comportamento do WhatsAppProviderPort sem realizar chamadas HTTP,
 * permitindo injeção de falhas controladas e verificação de mensagens enviadas.
 */
final class FakeWhatsAppAdapter implements WhatsAppProviderPort
{
    /** @var array<string, ProviderMessageDTO> */
    private array $sentMessages = [];

    /** @var list<array{phone:string,message:string}> */
    private array $sentPayloads = [];

    private bool $shouldFail = false;

    private ?string $failureCode = null;

    /** Retorna o nome do provedor fake para uso em testes. */
    public function getProviderName(): string
    {
        return 'fake';
    }

    /**
     * Simula envio de mensagem de texto, registrando o payload internamente.
     *
     * @param  SendTextPayloadDTO  $payload  Dados da mensagem simulada.
     * @return ProviderMessageDTO Resultado de sucesso ou falha injetada via shouldFailNextWith().
     */
    public function sendText(SendTextPayloadDTO $payload): ProviderMessageDTO
    {
        if ($this->shouldFail) {
            $this->shouldFail = false;

            return ProviderMessageDTO::failed(
                $this->failureCode ?? 'FAKE_ERROR',
                'Simulated failure',
            );
        }

        $dto = ProviderMessageDTO::success(
            providerMessageId: 'fake_'.Str::random(20),
            sentAt: Carbon::now(),
        );

        $this->sentMessages[$dto->providerMessageId] = $dto;
        $this->sentPayloads[] = [
            'phone' => $payload->phone,
            'message' => $payload->message,
        ];

        return $dto;
    }

    /**
     * Simula envio de mídia delegando para sendText com caption ou '[Media]'.
     *
     * @param  SendMediaPayloadDTO  $payload  Dados da mídia simulada.
     * @return ProviderMessageDTO Resultado da operação simulada.
     */
    public function sendMedia(SendMediaPayloadDTO $payload): ProviderMessageDTO
    {
        return $this->sendText(new SendTextPayloadDTO(
            phone: $payload->phone,
            message: $payload->caption ?? '[Media]',
        ));
    }

    /** Sempre retorna verdadeiro sem realizar chamada HTTP. */
    public function markAsRead(string $chatId): bool
    {
        return true;
    }

    /** Sempre retorna verdadeiro sem realizar chamada HTTP. */
    public function sendPresence(string $chatId, string $presence): bool
    {
        return true;
    }

    /** Retorna status de instância fake como conectada. */
    public function getInstanceStatus(): ProviderStatusDTO
    {
        return new ProviderStatusDTO(
            connected: true,
            phone: '5511999999999',
            name: 'Fake Instance',
        );
    }

    /** Sempre retorna verdadeiro, simulando que qualquer número existe. */
    public function checkNumberExists(string $phone): bool
    {
        return true;
    }

    /**
     * Retorna URL de foto de perfil fake ou null se o número for vazio.
     *
     * @param  string  $phone  Número de telefone.
     * @return string|null URL da foto fake ou null.
     */
    public function getProfilePicture(string $phone): ?string
    {
        if ($phone === '') {
            return null;
        }

        return 'https://example.com/fake-profile.jpg';
    }

    /**
     * Configura o adapter para falhar na próxima chamada com o código fornecido.
     *
     * @param  string  $errorCode  Código de erro a retornar na próxima operação.
     */
    public function shouldFailNextWith(string $errorCode): void
    {
        $this->shouldFail = true;
        $this->failureCode = $errorCode;
    }

    /**
     * Retorna todas as mensagens enviadas com sucesso durante o teste.
     *
     * @return array<string, ProviderMessageDTO> Mapa de providerMessageId para DTO.
     */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    /**
     * Limpa o estado interno do adapter para uso entre casos de teste.
     */
    public function reset(): void
    {
        $this->sentMessages = [];
        $this->sentPayloads = [];
        $this->shouldFail = false;
        $this->failureCode = null;
    }

    /**
     * Lança RuntimeException se nenhuma mensagem foi enviada ao número informado.
     *
     * @param  string  $phone  Número de telefone esperado.
     *
     * @throws \RuntimeException Se nenhuma mensagem foi enviada ao número.
     */
    public function assertSentTo(string $phone): void
    {
        foreach ($this->sentPayloads as $payload) {
            if ($payload['phone'] === $phone) {
                return;
            }
        }

        throw new RuntimeException("No messages sent to {$phone}.");
    }
}
