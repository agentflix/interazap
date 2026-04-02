<?php

declare(strict_types=1);

use Domain\Ai\Models\AiConversationSummary;
use Domain\Ai\Services\ContextWindowManagerService;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

describe('ContextWindowManagerService', function (): void {
    it('builds adaptive window and includes summary when available', function (): void {
        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->forTenant((string) $tenant->id)->create();

        foreach (range(1, 35) as $index) {
            ChatMessage::factory()->create([
                'tenant_id' => $tenant->id,
                'ticket_id' => $ticket->id,
                'content' => 'Message '.$index,
                'is_from_contact' => $index % 2 === 0,
            ]);
        }

        AiConversationSummary::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'summary' => 'Resumo consolidado',
            'message_count' => 35,
            'generated_at' => now(),
        ]);

        $service = app(ContextWindowManagerService::class);
        $window = $service->buildWindow((string) $tenant->id, (string) $ticket->id);

        expect($window)->toHaveCount(31)
            ->and($window[0]['kind'] ?? null)->toBe('summary')
            ->and($window[0]['content'] ?? null)->toBe('Resumo consolidado');
    });
});
