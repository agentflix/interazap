<?php

declare(strict_types=1);

use Domain\Chat\Jobs\ChatMediaDownloadJob;
use Domain\Chat\Services\ChatBroadcastService;
use Domain\Chat\Services\ChatGatewayService;

require __DIR__.'/../../vendor/autoload.php';
$app = require_once __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== Teste ChatMediaDownloadJob ===\n\n";

$messageId = 'a0ded62e-7fc8-4b56-9095-5c9b57ce922e';
$msg = \Domain\Chat\Models\ChatMessage::query()->find($messageId);

if (! $msg) {
    echo "Mensagem não encontrada: {$messageId}\n";
    exit(1);
}

echo "Message ID: {$msg->id}\n";
echo "Tenant ID: {$msg->tenant_id}\n";
echo 'File URL atual: '.($msg->file_url ?? 'NULL')."\n\n";

$job = new ChatMediaDownloadJob(
    (string) $msg->id,
    (string) $msg->tenant_id,
    '00e01532-d35f-4102-88dc-784d9f4c26be',
    '5514996448268:TEST_MEDIA_DOWNLOAD_001',
    'https://mmg.whatsapp.net/o1/v/t24/f2/m235/AQOs1saw6OY9iphkDeyz73-CRfrEUjuPqY_nJEPBj8t9iuCxc_v2a1DOpLmv_zWVblOwFZNhtrRBzv6w2Grv-DIt21_PvxL1AQ9dR-Lzcg?ccb=9-4&oh=01_Q5Aa3gF5qMhSzGv5Uh-CmcL-_xYmRqZMidJwjlMPxYjxAZF_xw&oe=6994DBE5&_nc_sid=e6ed6c&mms3=true',
    'image/jpeg'
);

echo "Chamando handle() diretamente...\n";

try {
    $gateway = app(ChatGatewayService::class);
    $broadcast = app(ChatBroadcastService::class);

    $job->handle($gateway, $broadcast);

    echo "Handle completado!\n\n";
} catch (\Throwable $e) {
    echo "ERRO: {$e->getMessage()}\n";
    echo "File: {$e->getFile()}:{$e->getLine()}\n";
    echo "Trace:\n{$e->getTraceAsString()}\n";
    exit(1);
}

// Recarregar mensagem
$msg->refresh();

echo "=== Depois do Job ===\n";
echo 'File URL: '.($msg->file_url ?? 'NULL')."\n";
echo 'File Name: '.($msg->file_name ?? 'NULL')."\n";
echo 'Mime Type: '.($msg->mime_type ?? 'NULL')."\n";
echo 'File Size: '.($msg->file_size ?? 'NULL')."\n";

// Verificar storage
$storageDir = storage_path('app/public/chat/media');
if (is_dir($storageDir)) {
    echo "\nArquivos no storage:\n";
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storageDir));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            echo "  - {$file->getPathname()}\n";
        }
    }
} else {
    echo "\nDiretório de mídia não existe: {$storageDir}\n";
}
