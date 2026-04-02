<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Chat\Models\ChatInstance;
use Domain\Platform\Services\UazapiGatewayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Executa teste real de envio de mídia via Gateway.
 *
 * @category Commands
 */
final class ChatUazapiSendMediaCommand extends Command
{
    protected $signature = 'chat:uazapi:send-media
        {--file= : Caminho do arquivo de mídia}
        {--number=5514996448268 : Número destino em E.164}
        {--text= : Texto da mensagem}
        {--delay=2000 : Delay em ms}';

    protected $description = 'Executa envio real de mídia via Gateway.';

    public function handle(UazapiGatewayService $gateway): int
    {
        $instance = ChatInstance::query()
            ->where('provider', 'uazapi')
            ->where('status', 'connected')
            ->where('is_active', true)
            ->first();

        if (! $instance) {
            $this->error('Nenhuma instância conectada encontrada.');
            logger()->warning('Nenhuma instância conectada para envio de mídia Uazapi.');

            return self::FAILURE;
        }

        $settings = (array) $instance->settings_json;
        $token = $settings['token'] ?? null;
        if (! $token) {
            $this->error('Token da instância não encontrado.');
            logger()->warning('Token da instância não encontrado para envio de mídia.', ['instance_id' => $instance->id]);

            return self::FAILURE;
        }

        $baseUrl = $settings['base_url'] ?? null;
        if (is_string($baseUrl) && $baseUrl !== '') {
            config(['services.uazapi.base_url' => $baseUrl]);
        }

        $sourcePath = (string) ($this->option('file') ?? '');
        $targetPath = storage_path('app/testing/unnamed.png');
        if ($sourcePath !== '') {
            if (! file_exists($sourcePath)) {
                $this->error('Arquivo informado não existe.');
                logger()->warning('Arquivo de mídia não encontrado.', ['path' => $sourcePath]);

                return self::FAILURE;
            }

            @mkdir(dirname($targetPath), 0775, true);
            if (! copy($sourcePath, $targetPath)) {
                throw new RuntimeException('Falha ao copiar o arquivo para o storage.');
            }
        }

        if (! file_exists($targetPath)) {
            $this->error('Arquivo de teste não encontrado em storage/app/testing/unnamed.png.');
            logger()->warning('Arquivo de teste não encontrado no storage.', ['path' => $targetPath]);

            return self::FAILURE;
        }

        $mime = mime_content_type($targetPath) ?: 'image/png';
        $base64 = 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($targetPath));

        $number = preg_replace('/\D/', '', (string) $this->option('number'));
        $text = (string) ($this->option('text') ?? '');
        if ($text === '') {
            $text = 'Teste de integração de imagem - '.now()->format('Y-m-d H:i:s');
        }
        $delay = (int) $this->option('delay');

        $maskedToken = str_repeat('*', max(0, strlen((string) $token) - 6)).substr((string) $token, -6);
        logger()->info('Iniciando envio de mídia via Gateway.', [
            'instance_id' => $instance->id,
            'number' => $number,
            'token' => $maskedToken,
        ]);

        $response = $gateway->sendFile($token, [
            'number' => $number,
            'type' => 'image',
            'file' => $base64,
            'text' => $text,
            'delay' => $delay,
        ]);

        try {
            $json = json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $json = '{}';
        }

        $this->line($json);

        $messageId = $response['messageid'] ?? $response['messageId'] ?? $response['message_id'] ?? null;
        if ($messageId) {
            logger()->info('Envio de mídia via Gateway concluído.', [
                'instance_id' => $instance->id,
                'messageid' => $messageId,
                'number' => $number,
            ]);

            Storage::delete('testing/unnamed.png');

            return self::SUCCESS;
        }

        logger()->warning('Envio de mídia via Gateway retornou sem messageid.', [
            'instance_id' => $instance->id,
            'number' => $number,
            'response' => $response,
        ]);

        return self::FAILURE;
    }
}
