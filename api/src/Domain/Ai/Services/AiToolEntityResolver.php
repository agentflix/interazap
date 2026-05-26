<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Models\AiAgent;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMNegotiation;
use Illuminate\Support\Str;

/**
 * Serviço de resolução de entidades usadas pelas tools do Autopilot.
 *
 * Contexto: converte referências ambíguas (UUID, nome, e-mail) em modelos concretos
 * do domínio, encapsulando a lógica de fallback de forma reutilizável entre tools.
 */
final class AiToolEntityResolver
{
    /**
     * Resolve o vendedor a partir do input da tool, com múltiplos fallbacks.
     *
     * Ordem de resolução: seller_id (UUID) → seller_email → seller_name →
     * assigned_to do ticket → primeiro usuário ativo do tenant.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  array<string, mixed>  $input  Parâmetros de entrada da tool.
     * @param  string|null  $ticketId  UUID do ticket (para fallback por assigned_to).
     * @return AuthUser|null Vendedor encontrado ou null.
     */
    public function resolveSeller(string $tenantId, array $input, ?string $ticketId = null): ?AuthUser
    {
        $sellerId = $this->stringValue($input['seller_id'] ?? null);
        if ($sellerId !== null && Str::isUuid($sellerId)) {
            $seller = $this->activeTenantUserQuery($tenantId)->find($sellerId);
            if ($seller instanceof AuthUser) {
                return $seller;
            }
        }

        $email = $this->stringValue($input['seller_email'] ?? null);
        if ($email !== null) {
            $seller = $this->activeTenantUserQuery($tenantId)
                ->whereRaw('lower(email) = ?', [mb_strtolower($email)])
                ->first();
            if ($seller instanceof AuthUser) {
                return $seller;
            }
        }

        $name = $this->stringValue($input['seller_name'] ?? $input['seller'] ?? $sellerId);
        if ($name !== null) {
            $seller = $this->findUserByNormalizedName($tenantId, $name);
            if ($seller instanceof AuthUser) {
                return $seller;
            }
        }

        if ($ticketId !== null && Str::isUuid($ticketId)) {
            $assignedTo = ChatTicket::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $ticketId)
                ->value('assigned_to');

            if (is_string($assignedTo) && Str::isUuid($assignedTo)) {
                $seller = $this->activeTenantUserQuery($tenantId)->find($assignedTo);
                if ($seller instanceof AuthUser) {
                    return $seller;
                }
            }
        }

        return $this->activeTenantUserQuery($tenantId)
            ->orderByRaw("CASE WHEN lower(name) LIKE '%rosa%' THEN 0 ELSE 1 END")
            ->orderBy('created_at')
            ->first();
    }

    /**
     * Resolve a negociação a partir do input da tool, com múltiplos fallbacks.
     *
     * Ordem de resolução: negotiation_id (UUID) → contact_id do input ou ticket → mais recente aberta.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  array<string, mixed>  $input  Parâmetros de entrada da tool.
     * @param  string|null  $ticketId  UUID do ticket (para extrair contact_id se ausente no input).
     * @param  string|null  $contactId  UUID do contato passado diretamente (prioridade sobre ticket).
     * @return CRMNegotiation|null Negociação encontrada ou null.
     */
    public function resolveNegotiation(
        string $tenantId,
        array $input,
        ?string $ticketId = null,
        ?string $contactId = null,
    ): ?CRMNegotiation {
        if ($tenantId === '') {
            return null;
        }

        $negotiationId = $this->stringValue($input['negotiation_id'] ?? null);
        if ($negotiationId !== null && Str::isUuid($negotiationId)) {
            $negotiation = CRMNegotiation::query()
                ->where('tenant_id', $tenantId)
                ->find($negotiationId);

            if ($negotiation instanceof CRMNegotiation) {
                return $negotiation;
            }
        }

        $resolvedContactId = $contactId ?? $this->stringValue($input['contact_id'] ?? null);
        if ($resolvedContactId === null && $ticketId !== null && Str::isUuid($ticketId)) {
            $ticketContactId = ChatTicket::query()
                ->where('tenant_id', $tenantId)
                ->where('id', $ticketId)
                ->value('contact_id');
            $resolvedContactId = is_string($ticketContactId) ? $ticketContactId : null;
        }

        if ($resolvedContactId !== null && Str::isUuid($resolvedContactId)) {
            return CRMNegotiation::query()
                ->where('tenant_id', $tenantId)
                ->where('crm_contact_id', $resolvedContactId)
                ->where('status', 'open')
                ->orderByDesc('created_at')
                ->first();
        }

        return null;
    }

    /**
     * Resolve um agente de IA a partir do UUID ou nome normalizado.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $nameOrId  UUID ou nome do agente (comparação case-insensitive após normalização ASCII).
     * @return AiAgent|null Agente ativo encontrado ou null.
     */
    public function resolveAgent(string $tenantId, string $nameOrId): ?AiAgent
    {
        if (Str::isUuid($nameOrId)) {
            $agent = AiAgent::query()
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->find($nameOrId);

            if ($agent instanceof AiAgent) {
                return $agent;
            }
        }

        $normalized = $this->normalize($nameOrId);

        return AiAgent::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get()
            ->first(fn (AiAgent $agent): bool => $this->normalize((string) $agent->name) === $normalized);
    }

    /**
     * Retorna a query base de usuários ativos do tenant.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @return \Illuminate\Database\Eloquent\Builder<AuthUser>
     */
    private function activeTenantUserQuery(string $tenantId): \Illuminate\Database\Eloquent\Builder
    {
        return AuthUser::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true);
    }

    /**
     * Busca usuário ativo pelo nome normalizado (ASCII lowercase).
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $name  Nome a normalizar e comparar.
     * @return AuthUser|null Primeiro usuário encontrado ou null.
     */
    private function findUserByNormalizedName(string $tenantId, string $name): ?AuthUser
    {
        $normalized = $this->normalize($name);

        return $this->activeTenantUserQuery($tenantId)
            ->get()
            ->first(fn (AuthUser $user): bool => $this->normalize((string) $user->name) === $normalized);
    }

    /** Normaliza uma string para comparação (ASCII lowercase sem espaços extras). */
    private function normalize(string $value): string
    {
        return Str::lower(Str::ascii(trim($value)));
    }

    /** Retorna a string trimada ou null se o valor não for string não vazia. */
    private function stringValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
