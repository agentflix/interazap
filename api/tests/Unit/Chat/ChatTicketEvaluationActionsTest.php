<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Chat\Actions\ChatTicketEvaluationActions;
use Domain\Chat\DTOs\ChatTicketEvaluationDTO;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTicketEvaluation;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatTicketEvaluationActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_create_and_list_evaluations(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create();

        $actions = new ChatTicketEvaluationActions;
        $evaluation = $actions->create((string) $tenant->id, new ChatTicketEvaluationDTO((string) $ticket->id, 5, 'Ok'));

        $this->assertInstanceOf(ChatTicketEvaluation::class, $evaluation);

        $paginator = $actions->list((string) $tenant->id);
        $this->assertSame(1, $paginator->total());
    }
}
