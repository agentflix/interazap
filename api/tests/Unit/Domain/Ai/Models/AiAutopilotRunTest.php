<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Models;

use Domain\Ai\Models\AiAutopilotPlaybook;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AiAutopilotRunTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_is_ad_hoc_returns_true_when_playbook_id_is_null(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $run = AiAutopilotRun::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'playbook_id' => null,
            'status' => 'queued',
            'playbook_version' => 2,
            'input_context' => [],
        ]);

        $this->assertTrue($run->isAdHoc());
    }

    public function test_is_ad_hoc_returns_false_when_playbook_id_exists(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $playbook = AiAutopilotPlaybook::factory()->create([
            'tenant_id' => (string) $tenant->id,
        ]);
        $run = AiAutopilotRun::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $tenant->id,
            'playbook_id' => (string) $playbook->id,
            'status' => 'queued',
            'playbook_version' => 2,
            'input_context' => [],
        ]);

        $this->assertFalse($run->isAdHoc());
    }
}
