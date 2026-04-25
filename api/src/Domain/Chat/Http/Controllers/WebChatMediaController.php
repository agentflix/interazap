<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Http\Requests\WebChatMediaStoreRequest;
use Domain\Chat\Models\ChatSession;
use Domain\Chat\Services\WebChatJwtService;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class WebChatMediaController extends BaseController
{
    public function __construct(
        private readonly WebChatJwtService $jwtService,
    ) {}

    public function store(WebChatMediaStoreRequest $request): JsonResponse
    {
        $payload = $this->jwtService->validateToken($request->string('token')->toString());

        if ($payload === null) {
            return $this->unauthorized('Token inválido ou expirado');
        }

        $sessionId = $payload['session_id'] ?? null;
        $tenantId  = $payload['tenant_id'] ?? null;
        $ticketId  = $payload['ticket_id'] ?? null;

        if (! $sessionId || ! $tenantId || ! $ticketId) {
            return $this->unauthorized('Token inválido: claims incompletas');
        }

        $session = ChatSession::query()
            ->where('id', $sessionId)
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $ticketId)
            ->first();

        if (! $session) {
            return $this->notFound('Sessão não encontrada ou inativa');
        }

        $file      = $request->file('file');
        $extension = $file->getClientOriginalExtension();
        $mimeType  = $file->getMimeType() ?? $file->getClientMimeType();
        $size      = $file->getSize();
        $fileName  = $file->getClientOriginalName();
        $uuid      = (string) Str::orderedUuid();
        $directory = "chat/webchat/{$tenantId}";
        $storedAs  = "{$uuid}.{$extension}";

        $path = Storage::disk('public')->putFileAs($directory, $file, $storedAs);

        $url = Storage::disk('public')->url($path);

        return $this->created([
            'url'       => $url,
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'size'      => $size,
        ], 'Arquivo enviado com sucesso');
    }
}
