<?php

declare(strict_types=1);

use Domain\Ai\Models\AiConversationSummary;
use Domain\Ai\Services\AiConversationSummaryService;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('AiConversationSummaryService', function (): void {
    it('generates and persists conversation summary', function (): void {
        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->forTenant((string) $tenant->id)->create();

        foreach (range(1, 8) as $index) {
            ChatMessage::factory()->create([
                'tenant_id' => $tenant->id,
                'ticket_id' => $ticket->id,
                'content' => 'Mensagem '.$index,
                'is_from_contact' => $index % 2 === 0,
            ]);
        }

        $service = app(AiConversationSummaryService::class);
        $summary = $service->summarize((string) $tenant->id, (string) $ticket->id);

        expect($summary->summary)->toContain('Mensagem')
            ->and($summary->message_count)->toBe(8);

        $this->assertDatabaseHas('ai_conversation_summaries', [
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
        ]);

        expect(AiConversationSummary::query()->count())->toBe(1);
    });
});
