<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts;

use Domain\Platform\Models\PlatformTenant;

/**
 * Contrato para o serviço de limite de armazenamento da Knowledge Base.
 *
 * Verifica cotas de armazenamento do tenant de acordo com o plano contratado.
 */
interface AiStorageLimitServiceInterface
{
    /**
     * Verifica se o tenant pode fazer upload de um arquivo do tamanho informado.
     */
    public function canUpload(PlatformTenant $tenant, int $fileSize): bool;

    /**
     * Retorna o uso atual de armazenamento do tenant em bytes.
     */
    public function getCurrentUsage(PlatformTenant $tenant): int;

    /**
     * Retorna o limite de armazenamento do plano do tenant em bytes.
     */
    public function getStorageLimit(PlatformTenant $tenant): int;
}
