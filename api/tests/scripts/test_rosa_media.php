<?php

/**
 * Script para testar o download de mídia da mensagem da Rosa Lopes
 *
 * Usage: php tests/scripts/test_rosa_media.php
 */

require_once __DIR__.'/../../vendor/autoload.php';

$app = require_once __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Domain\Chat\Jobs\ChatMediaDownloadJob;
use Domain\Chat\Services\ChatBroadcastService;
use Domain\Chat\Services\ChatGatewayService;

echo "=== Teste de Download de Mídia - Rosa Lopes ===\n\n";

// ID da mensagem real
$messageId = 'a0ded62e-7fc8-4b56-9095-5c9b57ce922e';

$msg = \Domain\Chat\Models\ChatMessage::query()->find($messageId);

if (! $msg) {
    echo "Mensagem não encontrada\n";
    exit(1);
}

echo "Mensagem: {$msg->id}\n";
echo "External ID: {$msg->external_id}\n";
echo 'Instance Token: '.($msg->ticket?->instance?->token ?? 'NULL')."\n";
echo 'file_url antes: '.($msg->file_url ?? 'NULL')."\n";
echo 'media_type antes: '.($msg->media_type ?? 'NULL')."\n\n";

// Pegar o token da instância
$instanceToken = $msg->ticket?->instance?->token ?? '00e01532-d35f-4102-88dc-784d9f4c26be';
$tenantId = $msg->ticket?->tenant_id ?? 'unknown';

echo "Criando e executando job...\n\n";

$job = new ChatMediaDownloadJob(
    messageId: $msg->id,
    tenantId: $tenantId,
    instanceToken: $instanceToken,
    externalMessageId: $msg->external_id,
    originalUrl: 'https://mmg.whatsapp.net/o1/v/t24/f2/m235/AQOs1saw6OY9iphkDeyz73-CRfrEUjuPqY_nJEPBj8t9iuCxc_v2a1DOpLmv_zWVblOwFZNhtrRBzv6w2Grv-DIt21_PvxL1AQ9dR-Lzcg?ccb=9-4&oh=01_Q5Aa3gF5qMhSzGv5Uh-CmcL-_xYmRqZMidJwjlMPxYjxAZF_xw&oe=6994DBE5&_nc_sid=e6ed6c&mms3=true',
    mimeType: 'image/jpeg',
);

try {
    $job->handle(app(ChatGatewayService::class), app(ChatBroadcastService::class));
    echo "Job executado!\n\n";

    // Recarregar a mensagem
    $msg->refresh();
    echo 'file_url depois: '.($msg->file_url ?? 'NULL')."\n";
    echo 'media_type depois: '.($msg->media_type ?? 'NULL')."\n";
} catch (Exception $e) {
    echo 'ERRO: '.$e->getMessage()."\n";
    echo 'Trace: '.$e->getTraceAsString()."\n";
}

// Verificar se o arquivo existe no storage
if ($msg->file_url && ! str_starts_with((string) $msg->file_url, 'http')) {
    $localPath = storage_path('app/public/'.$msg->file_url);
    echo "\nVerificando arquivo local: {$localPath}\n";
    echo 'Existe: '.(file_exists($localPath) ? 'SIM' : 'NÃO')."\n";
    if (file_exists($localPath)) {
        echo 'Tamanho: '.filesize($localPath)." bytes\n";
    }
}

echo "\n=== Fim do Teste ===\n";
