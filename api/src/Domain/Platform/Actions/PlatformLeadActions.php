<?php

declare(strict_types=1);

namespace Domain\Platform\Actions;

use Domain\Platform\DTOs\PlatformLeadDTO;
use Domain\Platform\Events\PlatformLeadCreated;
use Domain\Platform\Models\PlatformLead;

/**
 * Actions para captura e gestão de leads da plataforma InteraZap.
 */
final class PlatformLeadActions
{
    /**
     * Persiste um novo lead vindo da landing/modal.
     *
     * Comportamento:
     * - Honeypot preenchido → retorna instância "fake" não persistida (silent drop).
     * - Deduplicação: se já existe lead com o mesmo email OU telefone nas últimas
     *   24h, atualiza `updated_at` do existente e o retorna, sem criar novo registro.
     * - Caso contrário, cria o registro e dispara `PlatformLeadCreated`.
     *
     * @param  PlatformLeadDTO  $dto  DTO com dados do lead.
     * @return PlatformLead Lead persistido (ou existente, em caso de dedupe).
     */
    public function store(PlatformLeadDTO $dto): PlatformLead
    {
        if ($dto->isHoneypot) {
            // Silent drop: devolve instância em memória sem tocar o DB.
            $fake = new PlatformLead($dto->toArray());
            $fake->id = '00000000-0000-0000-0000-000000000000';

            return $fake;
        }

        $existing = $this->findRecentDuplicate($dto);

        if ($existing !== null) {
            $existing->touch();

            return $existing->refresh();
        }

        /** @var PlatformLead $lead */
        $lead = PlatformLead::query()->create($dto->toArray());

        PlatformLeadCreated::dispatch($lead->id);

        return $lead->refresh();
    }

    /**
     * Busca duplicata recente (mesmo email OU telefone, últimas 24h).
     *
     * @param  PlatformLeadDTO  $dto  DTO do lead em criação.
     */
    private function findRecentDuplicate(PlatformLeadDTO $dto): ?PlatformLead
    {
        /** @var PlatformLead|null $lead */
        $lead = PlatformLead::query()
            ->where('created_at', '>=', now()->subDay())
            ->where(function ($query) use ($dto): void {
                $query->where('email', $dto->email)
                    ->orWhere('phone', $dto->phone);
            })
            ->orderByDesc('created_at')
            ->first();

        return $lead;
    }
}
