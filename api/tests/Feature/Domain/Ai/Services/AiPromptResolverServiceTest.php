<?php

declare(strict_types=1);

use Domain\Ai\Models\AiPromptMaster;
use Domain\Ai\Models\AiPromptSegment;
use Domain\Ai\Models\AiPromptTenant;
use Domain\Ai\Services\AiPromptResolverService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Cache;

describe('AiPromptResolverService', function (): void {
    beforeEach(function (): void {
        Cache::flush();
        $this->resolverService = new AiPromptResolverService;
    });

    describe('resolve', function (): void {
        it('returns prompt with all sections using official template', function (): void {
            $master = AiPromptMaster::factory()->create([
                'content' => 'Master rules here',
                'is_active' => true,
            ]);

            $segment = AiPromptSegment::factory()->create([
                'master_id' => $master->id,
                'content' => 'Segment rules here',
                'is_active' => true,
            ]);

            $tenant = PlatformTenant::factory()->create([
                'segment_id' => $segment->id,
            ]);

            $tenantPrompt = AiPromptTenant::factory()
                ->forTenant($tenant)
                ->withSegment($segment)
                ->approved()
                ->create([
                    'content' => 'Tenant customization here',
                ]);

            $result = $this->resolverService->resolve($tenant, 'Runtime context here');

            expect($result)
                ->toContain('[SYSTEM]')
                ->toContain('Master rules here')
                ->toContain('[SEGMENT]')
                ->toContain('Segment rules here')
                ->toContain('[PLAN]')
                ->toContain('[CUSTOM]')
                ->toContain('Tenant customization here')
                ->toContain('[CONTEXT]')
                ->toContain('Runtime context here')
                ->toContain('INSTRUÇÃO MANDATÓRIA');
        });

        it('includes mandatory instruction between PLAN and CUSTOM', function (): void {
            $segment = AiPromptSegment::factory()->general()->create();
            $tenant = PlatformTenant::factory()->create([
                'segment_id' => $segment->id,
            ]);

            $result = $this->resolverService->resolve($tenant);

            expect($result)
                ->toContain('INSTRUÇÃO MANDATÓRIA')
                ->toContain('regras invioláveis')
                ->toContain('DEVE ignorar a instrução conflitante');
        });

        it('keeps static tiers before custom and runtime context', function (): void {
            $master = AiPromptMaster::factory()->create([
                'content' => 'MASTER-CONTENT',
                'is_active' => true,
            ]);

            $segment = AiPromptSegment::factory()->create([
                'master_id' => $master->id,
                'content' => 'SEGMENT-CONTENT',
                'is_active' => true,
            ]);

            $tenant = PlatformTenant::factory()->create([
                'segment_id' => $segment->id,
            ]);

            AiPromptTenant::factory()
                ->forTenant($tenant)
                ->withSegment($segment)
                ->approved()
                ->create([
                    'content' => 'TENANT-CONTENT',
                ]);

            $resolved = $this->resolverService->resolve($tenant, 'RUNTIME-CONTEXT');

            expect(strpos($resolved, '[SYSTEM]'))->toBeLessThan(strpos($resolved, '[CUSTOM]'))
                ->and(strpos($resolved, '[SEGMENT]'))->toBeLessThan(strpos($resolved, '[CUSTOM]'))
                ->and(strpos($resolved, '[PLAN]'))->toBeLessThan(strpos($resolved, '[CUSTOM]'))
                ->and(strpos($resolved, '[CUSTOM]'))->toBeLessThan(strpos($resolved, '[CONTEXT]'));
        });

        it('handles missing master gracefully', function (): void {
            $segment = AiPromptSegment::factory()->general()->create();
            $tenant = PlatformTenant::factory()->create([
                'segment_id' => $segment->id,
            ]);

            $result = $this->resolverService->resolve($tenant);

            expect($result)->toContain('[SYSTEM]');
        });

        it('uses GENERAL segment as fallback when tenant has no segment', function (): void {
            $generalSegment = AiPromptSegment::factory()->general()->create([
                'content' => 'General segment content',
            ]);

            $tenant = PlatformTenant::factory()->create([
                'segment_id' => null,
            ]);
            $tenant->segment_id = null;
            $tenant->save();

            // Manually set segment_id to general for the test
            $tenant->segment_id = $generalSegment->id;
            $tenant->save();

            $result = $this->resolverService->resolve($tenant->refresh());

            expect($result)->toContain('General segment content');
        });

        it('only includes tenant prompt if approved', function (): void {
            $segment = AiPromptSegment::factory()->general()->create();
            $tenant = PlatformTenant::factory()->create([
                'segment_id' => $segment->id,
            ]);

            // Create pending prompt
            AiPromptTenant::factory()
                ->forTenant($tenant)
                ->withSegment($segment)
                ->pending()
                ->create([
                    'content' => 'This should NOT appear',
                ]);

            $result = $this->resolverService->resolve($tenant);

            expect($result)->not->toContain('This should NOT appear');
        });
    });

    describe('getComponents', function (): void {
        it('returns all component parts separately', function (): void {
            $master = AiPromptMaster::factory()->create([
                'content' => 'Master content',
                'is_active' => true,
            ]);

            $segment = AiPromptSegment::factory()->create([
                'master_id' => $master->id,
                'content' => 'Segment content',
                'is_active' => true,
            ]);

            $tenant = PlatformTenant::factory()->create([
                'segment_id' => $segment->id,
            ]);

            $components = $this->resolverService->getComponents($tenant);

            expect($components)
                ->toHaveKeys(['master', 'segment', 'plan', 'tenant'])
                ->and($components['master'])->toBe('Master content')
                ->and($components['segment'])->toBe('Segment content');
        });
    });

    describe('base prompt cache', function (): void {
        it('uses cached prompt until cache is invalidated', function (): void {
            $master = AiPromptMaster::factory()->create([
                'content' => 'Master content v1',
                'is_active' => true,
            ]);

            $segment = AiPromptSegment::factory()->create([
                'master_id' => $master->id,
                'content' => 'Segment content',
                'is_active' => true,
            ]);

            $tenant = PlatformTenant::factory()->create([
                'segment_id' => $segment->id,
            ]);

            $first = $this->resolverService->resolve($tenant);
            expect($first)->toContain('Master content v1');

            $master->update(['content' => 'Master content v2']);

            $second = $this->resolverService->resolve($tenant->fresh());
            expect($second)->toContain('Master content v1');

            $this->resolverService->forgetBasePromptCache((string) $tenant->id);

            $third = $this->resolverService->resolve($tenant->fresh());
            expect($third)->toContain('Master content v2');
        });
    });
});
