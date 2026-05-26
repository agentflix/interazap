<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Resources;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMNegotiationFile;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Services\PlatformPlanEnforcementService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource de detalhes completos do tenant (empresa, plano contratado e recursos).
 */
final class PlatformTenantDetailsResource extends JsonResource
{
    /**
     * Transforma o tenant em array com empresa, plano contratado e recursos consumidos.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tenantId = (string) $this->id;
        $plan = app(PlatformPlanEnforcementService::class)->getCurrentPlan($tenantId);

        return [
            'company' => $this->buildCompanyData(),
            'contracted_plan' => $this->buildPlanData($plan),
            'resources' => $this->buildResourcesData($tenantId, $plan),
        ];
    }

    /**
     * Constrói o bloco de dados da empresa do tenant.
     *
     * @return array<string, mixed>
     */
    private function buildCompanyData(): array
    {
        $address = collect([$this->street, $this->number, $this->complement, $this->district])
            ->filter(fn (mixed $part): bool => is_string($part) && $part !== '')
            ->implode(', ');

        return [
            'id' => $this->id,
            'name' => $this->name,
            'tenant_code' => $this->tenant_code,
            'document' => $this->document,
            'primary_email' => $this->primary_email,
            'phone' => $this->phone,
            'address' => $address ?: null,
            'street' => $this->street,
            'number' => $this->number,
            'complement' => $this->complement,
            'district' => $this->district,
            'city' => $this->city,
            'state' => $this->state,
            'zip_code' => $this->zip_code,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->format(\DATE_ATOM),
        ];
    }

    /**
     * Constrói o bloco de dados do plano contratado.
     *
     * @param  PlatformPlan|null  $plan  Plano vigente ou null se não houver.
     * @return array<string, mixed>|null Dados do plano ou null.
     */
    private function buildPlanData(?PlatformPlan $plan): ?array
    {
        if (! $plan) {
            return null;
        }

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'price_monthly' => $plan->price_monthly,
            'is_active' => (bool) $plan->is_active,
        ];
    }

    /**
     * Constrói o bloco de dados de recursos consumidos pelo tenant (usuários, instâncias, storage, negociações).
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  PlatformPlan|null  $plan  Plano vigente para calcular limites.
     * @return array<string, mixed>
     */
    private function buildResourcesData(string $tenantId, ?PlatformPlan $plan): array
    {
        $usersCount = AuthUser::query()->where('tenant_id', $tenantId)->count();
        $instancesCount = ChatInstance::query()->where('tenant_id', $tenantId)->count();
        $storageUsedBytes = $this->calculateStorageUsedBytes($tenantId);

        $usersLimit = ($plan && $plan->limit_users > 0) ? $plan->limit_users : null;
        $instancesLimit = ($plan && $plan->chat_channels_limit > 0) ? $plan->chat_channels_limit : null;

        $storageLimitBytes = null;
        $storageMode = 'UNLIMITED';
        if ($plan && $plan->isStorageLimited() && $plan->storage_limit_bytes && $plan->storage_limit_bytes > 0) {
            $storageLimitBytes = $plan->storage_limit_bytes;
            $storageMode = 'LIMITED';
        }

        $negotiationsCount = CRMNegotiation::query()->where('tenant_id', $tenantId)->count();
        $negotiationsLimit = null;
        $negotiationsMode = 'UNLIMITED';
        if ($plan && $plan->isNegotiationsLimited() && $plan->negotiations_limit && $plan->negotiations_limit > 0) {
            $negotiationsLimit = $plan->negotiations_limit;
            $negotiationsMode = 'LIMITED';
        }

        return [
            'users' => [
                'current' => $usersCount,
                'limit' => $usersLimit,
                'available' => $usersLimit !== null ? max(0, $usersLimit - $usersCount) : null,
            ],
            'instances' => [
                'current' => $instancesCount,
                'limit' => $instancesLimit,
                'available' => $instancesLimit !== null ? max(0, $instancesLimit - $instancesCount) : null,
            ],
            'storage' => [
                'used_bytes' => $storageUsedBytes,
                'limit_bytes' => $storageLimitBytes,
                'available_bytes' => $storageLimitBytes !== null ? max(0, $storageLimitBytes - $storageUsedBytes) : null,
                'used_gb' => round($storageUsedBytes / 1073741824, 2),
                'limit_gb' => $storageLimitBytes !== null ? round($storageLimitBytes / 1073741824, 2) : null,
                'available_gb' => $storageLimitBytes !== null ? round(max(0, $storageLimitBytes - $storageUsedBytes) / 1073741824, 2) : null,
                'mode' => $storageMode,
            ],
            'ai' => [
                'enabled' => $plan ? (bool) $plan->ai_enabled : false,
            ],
            'negotiations' => [
                'current' => $negotiationsCount,
                'limit' => $negotiationsLimit,
                'available' => $negotiationsLimit !== null ? max(0, $negotiationsLimit - $negotiationsCount) : null,
                'mode' => $negotiationsMode,
            ],
        ];
    }

    /**
     * Calcula o uso total de armazenamento do tenant em bytes.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @return int Total de bytes utilizados.
     */
    private function calculateStorageUsedBytes(string $tenantId): int
    {
        $crmFilesSize = (int) CRMNegotiationFile::query()
            ->where('tenant_id', $tenantId)
            ->sum('size');

        $chatStorageSize = 0;
        $path = "chat/{$tenantId}";
        $disk = \Illuminate\Support\Facades\Storage::disk('public');

        if ($disk->exists($path)) {
            foreach ($disk->allFiles($path) as $file) {
                try {
                    $chatStorageSize += $disk->size($file);
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return $crmFilesSize + $chatStorageSize;
    }
}
