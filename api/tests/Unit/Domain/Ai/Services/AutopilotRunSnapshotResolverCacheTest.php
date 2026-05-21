<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Services;

use Domain\Ai\Models\AiAgent;
use Domain\Ai\Services\AutopilotRunSnapshotResolver;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AutopilotRunSnapshotResolverCacheTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_second_resolve_with_same_tenant_agent_ticket_hits_cache_without_new_queries(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $agent = AiAgent::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'is_active' => true,
        ]);
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => (string) $tenant->id,
        ]);

        $resolver = app(AutopilotRunSnapshotResolver::class);

        $resolver->resolve((string) $tenant->id, $agent, (string) $ticket->id);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $resolver->resolve((string) $tenant->id, $agent, (string) $ticket->id);

        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_message_creation_invalidates_cached_snapshot_for_ticket(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $agent = AiAgent::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'is_active' => true,
        ]);
        $ticket = ChatTicket::factory()->create([
            'tenant_id' => (string) $tenant->id,
        ]);

        $resolver = app(AutopilotRunSnapshotResolver::class);
        $resolver->resolve((string) $tenant->id, $agent, (string) $ticket->id);

        ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'ticket_id' => (string) $ticket->id,
            'content' => 'nova mensagem',
            'type' => 'text',
            'direction' => 'incoming',
            'is_from_contact' => true,
            'status' => 'received',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $resolver->resolve((string) $tenant->id, $agent, (string) $ticket->id);

        $this->assertGreaterThan(0, count(DB::getQueryLog()));
    }
}
