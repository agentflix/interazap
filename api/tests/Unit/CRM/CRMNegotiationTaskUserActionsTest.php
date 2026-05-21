<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Actions\CRMNegotiationTaskUserActions;
use Domain\CRM\Models\CRMNegotiationTask;
use Domain\Platform\Models\PlatformTenant;

uses(\Illuminate\Foundation\Testing\LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actions = new CRMNegotiationTaskUserActions;
});

describe('CRMNegotiationTaskUserActions', function (): void {
    describe('listForUser', function (): void {
        it('returns tasks assigned to user', function (): void {
            CRMNegotiationTask::factory()->count(3)->create([
                'tenant_id' => $this->tenant->id,
                'auth_user_id' => $this->user->id,
            ]);

            $result = $this->actions->listForUser($this->tenant->id, $this->user->id);

            expect($result)->toHaveCount(3);
        });

        it('excludes tasks from other users', function (): void {
            $otherUser = AuthUser::factory()->create(['tenant_id' => $this->tenant->id]);

            CRMNegotiationTask::factory()->count(2)->create([
                'tenant_id' => $this->tenant->id,
                'auth_user_id' => $this->user->id,
            ]);

            CRMNegotiationTask::factory()->count(3)->create([
                'tenant_id' => $this->tenant->id,
                'auth_user_id' => $otherUser->id,
            ]);

            $result = $this->actions->listForUser($this->tenant->id, $this->user->id);

            expect($result)->toHaveCount(2);
        });

        it('excludes tasks from other tenants', function (): void {
            $otherTenant = PlatformTenant::factory()->create();
            $otherUser = AuthUser::factory()->create(['tenant_id' => $otherTenant->id]);

            CRMNegotiationTask::factory()->count(2)->create([
                'tenant_id' => $this->tenant->id,
                'auth_user_id' => $this->user->id,
            ]);

            CRMNegotiationTask::factory()->count(3)->create([
                'tenant_id' => $otherTenant->id,
                'auth_user_id' => $otherUser->id,
            ]);

            $result = $this->actions->listForUser($this->tenant->id, $this->user->id);

            expect($result)->toHaveCount(2);
        });

        it('orders tasks by due date', function (): void {
            $laterTask = CRMNegotiationTask::factory()->create([
                'tenant_id' => $this->tenant->id,
                'auth_user_id' => $this->user->id,
                'due_date' => now()->addDays(10),
                'title' => 'Later Task',
            ]);

            $earlierTask = CRMNegotiationTask::factory()->create([
                'tenant_id' => $this->tenant->id,
                'auth_user_id' => $this->user->id,
                'due_date' => now()->addDays(2),
                'title' => 'Earlier Task',
            ]);

            $result = $this->actions->listForUser($this->tenant->id, $this->user->id);

            expect($result->first()->title)->toBe('Earlier Task')
                ->and($result->last()->title)->toBe('Later Task');
        });

        it('returns empty collection when no tasks exist', function (): void {
            $result = $this->actions->listForUser($this->tenant->id, $this->user->id);

            expect($result)->toBeEmpty();
        });
    });
});
