<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Services;

use Domain\Ai\Services\AiToolEntityResolver;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMNegotiation;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * @group ai
 */
class AiToolEntityResolverTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_resolves_seller_by_friendly_name(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $seller = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Rosa Comercial',
        ]);

        $resolver = app(AiToolEntityResolver::class);

        $resolved = $resolver->resolveSeller((string) $tenant->id, ['seller' => 'Rosa Comercial']);

        expect($resolved?->id)->toBe($seller->id);
    }

    public function test_it_resolves_seller_from_ticket_assignment(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $seller = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $tenant->id,
            'assigned_to' => $seller->id,
        ]);

        $resolver = app(AiToolEntityResolver::class);

        $resolved = $resolver->resolveSeller((string) $tenant->id, [], (string) $ticket->id);

        expect($resolved?->id)->toBe($seller->id);
    }

    public function test_it_resolves_open_negotiation_from_ticket_contact(): void
    {
        $negotiation = CRMNegotiation::factory()->create();
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $negotiation->tenant_id,
            'contact_id' => $negotiation->crm_contact_id,
        ]);

        $resolver = app(AiToolEntityResolver::class);

        $resolved = $resolver->resolveNegotiation(
            (string) $negotiation->tenant_id,
            [],
            (string) $ticket->id,
        );

        expect($resolved?->id)->toBe($negotiation->id);
    }
}
