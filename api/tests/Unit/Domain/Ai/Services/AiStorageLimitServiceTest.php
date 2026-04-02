<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Services;

use Domain\Ai\Models\AiKnowledgeDocument;
use Domain\Ai\Services\AiStorageLimitService;
use Domain\Platform\Enums\PlatformStorageMode;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(LazilyRefreshDatabase::class);

describe('AiStorageLimitService', function (): void {
    beforeEach(function (): void {
        $this->service = new AiStorageLimitService;
        $this->tenant = PlatformTenant::factory()->create();
    });

    describe('getCurrentUsage()', function (): void {
        it('returns 0 for tenant with no documents', function (): void {
            $usage = $this->service->getCurrentUsage($this->tenant);
            expect($usage)->toBe(0);
        });

        it('sums file sizes of active documents', function (): void {
            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->withFileSize(1000)
                ->create();

            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->withFileSize(2000)
                ->create();

            $usage = $this->service->getCurrentUsage($this->tenant);
            expect($usage)->toBe(3000);
        });

        it('excludes inactive documents', function (): void {
            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->withFileSize(1000)
                ->create();

            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->withFileSize(2000)
                ->inactive()
                ->create();

            $usage = $this->service->getCurrentUsage($this->tenant);
            expect($usage)->toBe(1000);
        });

        it('excludes documents from other tenants', function (): void {
            $otherTenant = PlatformTenant::factory()->create();

            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->withFileSize(1000)
                ->create();

            AiKnowledgeDocument::factory()
                ->forTenant($otherTenant)
                ->withFileSize(5000)
                ->create();

            $usage = $this->service->getCurrentUsage($this->tenant);
            expect($usage)->toBe(1000);
        });
    });

    describe('getStorageLimit()', function (): void {
        it('returns default limit when no plan is assigned', function (): void {
            $limit = $this->service->getStorageLimit($this->tenant);
            expect($limit)->toBe(104857600); // 100MB default
        });

        it('returns unlimited when plan has unlimited storage', function (): void {
            Schema::create('billing_subscriptions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('plan_id');
                $table->string('status');
                $table->timestamps();
            });

            try {
                $plan = PlatformPlan::factory()->create([
                    'storage_mode' => PlatformStorageMode::UNLIMITED,
                    'storage_limit_bytes' => 0,
                ]);

                DB::table('billing_subscriptions')->insert([
                    'id' => (string) \Illuminate\Support\Str::orderedUuid(),
                    'tenant_id' => $this->tenant->id,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $limit = $this->service->getStorageLimit($this->tenant);

                expect($limit)->toBe(0)
                    ->and($this->service->getRemainingStorage($this->tenant))->toBe(PHP_INT_MAX)
                    ->and($this->service->getUsagePercentage($this->tenant))->toBe(0.0);
            } finally {
                Schema::dropIfExists('billing_subscriptions');
            }
        });
    });

    describe('canUpload()', function (): void {
        it('returns true when under limit', function (): void {
            $canUpload = $this->service->canUpload($this->tenant, 1000);
            expect($canUpload)->toBeTrue();
        });

        it('returns false when would exceed limit', function (): void {
            // Create documents that use most of the default limit
            AiKnowledgeDocument::factory()
                ->forTenant($this->tenant)
                ->withFileSize(104857500) // Almost at 100MB limit
                ->create();

            $canUpload = $this->service->canUpload($this->tenant, 1000);
            expect($canUpload)->toBeFalse();
        });

        it('returns true when exactly at limit', function (): void {
            $canUpload = $this->service->canUpload($this->tenant, 104857600);
            expect($canUpload)->toBeTrue();
        });

        it('returns false when plan limit would be exceeded', function (): void {
            Schema::create('billing_subscriptions', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id');
                $table->uuid('plan_id');
                $table->string('status');
                $table->timestamps();
            });

            try {
                $plan = PlatformPlan::factory()->create([
                    'storage_mode' => PlatformStorageMode::LIMITED,
                    'storage_limit_bytes' => 1000,
                ]);

                DB::table('billing_subscriptions')->insert([
                    'id' => (string) \Illuminate\Support\Str::orderedUuid(),
                    'tenant_id' => $this->tenant->id,
                    'plan_id' => $plan->id,
                    'status' => 'active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                AiKnowledgeDocument::factory()
                    ->forTenant($this->tenant)
                    ->withFileSize(800)
                    ->create();

                $canUpload = $this->service->canUpload($this->tenant, 300);

                expect($canUpload)->toBeFalse();
            } finally {
                Schema::dropIfExists('billing_subscriptions');
            }
        });
    });
});
