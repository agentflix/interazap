<?php

declare(strict_types=1);

use Domain\Ai\Services\AiPermissionMatrixService;
use Domain\Ai\Services\ToolDispatcherService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

describe('ToolDispatcherService::getToolDefinitions', function (): void {
    beforeEach(function (): void {
        $this->tenant = PlatformTenant::factory()->create();
        $this->service = new ToolDispatcherService(new AiPermissionMatrixService);
    });

    it('returns all active tools when role is not provided', function (): void {
        $definitions = $this->service->getToolDefinitions((string) $this->tenant->id);

        $toolNames = array_map(
            static fn (array $definition): string => (string) data_get($definition, 'function.name', ''),
            $definitions,
        );

        expect($toolNames)
            ->toContain('send_message')
            ->toContain('update_lead_score');
    });

    it('respects selected tools filter', function (): void {
        $definitions = $this->service->getToolDefinitions(
            (string) $this->tenant->id,
            'general',
            ['send_message'],
        );

        $toolNames = array_map(
            static fn (array $definition): string => (string) data_get($definition, 'function.name', ''),
            $definitions,
        );

        expect($toolNames)
            ->toContain('send_message')
            ->not->toContain('update_lead_score');
    });
});
