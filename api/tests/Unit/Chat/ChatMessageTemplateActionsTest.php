<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Chat\Actions\ChatMessageTemplateActions;
use Domain\Chat\Models\ChatMessageTemplate;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatMessageTemplateActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_create_and_list_templates(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $actions = new ChatMessageTemplateActions;
        $created = $actions->create((string) $tenant->id, [
            'name' => 'Welcome',
            'content' => 'Olá',
            'category' => 'general',
            'is_active' => true,
        ]);

        $this->assertInstanceOf(ChatMessageTemplate::class, $created);

        $paginator = $actions->list((string) $tenant->id);
        $this->assertSame(1, $paginator->total());
    }
}
