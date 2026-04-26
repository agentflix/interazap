<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Casos de uso para arquivos de negociações.
 */
final class CRMNegotiationFileActions
{
    /**
     * @return Collection<int, CRMNegotiationFile>
     */
    public function list(string $tenantId, string $negotiationId): Collection
    {
        $this->ensureNegotiation($tenantId, $negotiationId);

        return CRMNegotiationFile::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_id', $negotiationId)
            ->orderByDesc('created_at')
            ->get();
    }

    public function create(string $tenantId, ?string $userId, string $negotiationId, UploadedFile $uploadedFile): CRMNegotiationFile
    {
        $this->ensureNegotiation($tenantId, $negotiationId);

        $filename = Str::orderedUuid().'_'.$uploadedFile->getClientOriginalName();
        $directory = "tenants/{$tenantId}/crm/negotiations/{$negotiationId}";
        $path = Storage::disk('public')->putFileAs($directory, $uploadedFile, $filename);

        return CRMNegotiationFile::query()->create([
            'tenant_id' => $tenantId,
            'crm_negotiation_id' => $negotiationId,
            'auth_user_id' => $userId,
            'name' => $uploadedFile->getClientOriginalName(),
            'path' => $path,
            'size' => $uploadedFile->getSize(),
            'mime_type' => $uploadedFile->getMimeType(),
        ]);
    }

    public function delete(string $tenantId, string $negotiationId, string $fileId): void
    {
        $this->ensureNegotiation($tenantId, $negotiationId);

        $file = CRMNegotiationFile::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_id', $negotiationId)
            ->findOrFail($fileId);

        Storage::disk('public')->delete($file->path);
        $file->delete();
    }

    private function ensureNegotiation(string $tenantId, string $negotiationId): CRMNegotiation
    {
        return CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($negotiationId);
    }
}
