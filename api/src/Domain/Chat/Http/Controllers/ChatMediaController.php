<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Http\Requests\ChatMediaStoreRequest;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Models\SharedMedia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controlador de Mídias para Chat.
 *
 * Responsável pelo recebimento e armazenamento temporário ou permanente de arquivos
 * enviados via chat (comprovantes, áudios, imagens), garantindo que as URLs
 * de acesso sejam geradas corretamente para o frontend.
 *
 * @category Controllers
 */
final class ChatMediaController extends BaseController
{
    /**
     * Realizar o upload de um arquivo de mídia.
     *
     * @param  ChatMediaStoreRequest  $request  Solicitação contendo o arquivo binário.
     * @return JsonResponse Dados do arquivo armazenado incluindo a URL pública.
     */
    public function store(ChatMediaStoreRequest $request): JsonResponse
    {
        $this->authorize('create', SharedMedia::class);

        $tenantId = (string) $request->user()->tenant_id;
        $file = $request->file('file');
        $folder = $tenantId !== '' ? "chat/{$tenantId}" : 'chat/public';
        $path = $file->store($folder, 'public');
        if ($path === false) {
            return $this->error('Falha ao armazenar arquivo.', 500);
        }

        $baseUrl = rtrim($request->getSchemeAndHttpHost(), '/');
        $publicUrl = $baseUrl.'/storage/'.$path;

        return $this->success([
            'url' => $publicUrl,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
            'metadata' => ['path' => $path, 'id' => (string) Str::orderedUuid()],
        ], 'Mídia enviada');
    }

    /**
     * Download de mídia via signed URL temporária.
     *
     * @param  Request  $request  Solicitação HTTP com assinatura.
     * @return BinaryFileResponse|JsonResponse Arquivo binário ou erro.
     */
    public function download(Request $request): BinaryFileResponse|JsonResponse
    {
        if (! $request->hasValidSignature()) {
            return $this->forbidden('Link expirado. Solicite novamente.');
        }

        $path = $request->query('path');
        if (! is_string($path) || ! Storage::disk('public')->exists($path)) {
            return $this->error('File not found', 404);
        }

        // Ensure file is in the chat directory (security)
        if (! str_starts_with($path, 'chat/')) {
            return $this->forbidden('Access denied.');
        }

        $fullPath = Storage::disk('public')->path($path);
        $fileName = $request->query('name', basename($path));

        return response()->download($fullPath, is_string($fileName) ? $fileName : basename($path));
    }
}
