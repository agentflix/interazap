<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Database\Seeders\PlatformPlanExtraSeeder;
use Database\Seeders\PlatformPlanSeeder;
use Domain\Platform\Enums\PlatformNegotiationsMode;
use Domain\Platform\Enums\PlatformReportsMode;
use Domain\Platform\Enums\PlatformStorageMode;
use Domain\Platform\Models\PlatformPlan;
use Illuminate\Support\Str;

uses()->group('seeders', 'platform');

beforeEach(function (): void {
    PlatformPlan::query()->delete();

    $this->seed(PlatformPlanSeeder::class);
    $this->seed(PlatformPlanExtraSeeder::class);
});

describe('PlatformPlanSeeder', function (): void {

    it('creates exactly 3 plans', function (): void {
        expect(PlatformPlan::query()->count())->toBe(3);
    });

    it('creates starter plan with correct attributes', function (): void {
        $plan = PlatformPlan::query()->where('slug', 'starter')->first();

        expect($plan)->not()->toBeNull()
            ->and($plan->name)->toBe('Starter')
            ->and($plan->limit_users)->toBe(5)
            ->and($plan->chat_channels_limit)->toBe(1)
            ->and($plan->storage_mode)->toBe(PlatformStorageMode::LIMITED)
            ->and($plan->storage_limit_bytes)->toBe(1024 * 1024 * 1024)
            // IA habilitada em todos os planos desde 55989d9 (feat: habilita IA em todos os planos e ajusta cotas mensais).
            ->and($plan->ai_enabled)->toBeTrue()
            ->and($plan->negotiations_mode)->toBe(PlatformNegotiationsMode::LIMITED)
            ->and($plan->negotiations_limit)->toBe(50)
            ->and($plan->reports_mode)->toBe(PlatformReportsMode::BASIC)
            ->and((float) $plan->price_monthly)->toBe(97.00)
            ->and($plan->is_active)->toBeTrue();
    });

    it('creates professional plan with correct attributes', function (): void {
        $plan = PlatformPlan::query()->where('slug', 'professional')->first();

        expect($plan)->not()->toBeNull()
            ->and($plan->name)->toBe('Professional')
            ->and($plan->limit_users)->toBe(20)
            ->and($plan->chat_channels_limit)->toBe(5)
            ->and($plan->storage_mode)->toBe(PlatformStorageMode::LIMITED)
            ->and($plan->storage_limit_bytes)->toBe(5 * 1024 * 1024 * 1024)
            ->and($plan->ai_enabled)->toBeTrue()
            ->and($plan->negotiations_mode)->toBe(PlatformNegotiationsMode::LIMITED)
            ->and($plan->negotiations_limit)->toBe(500)
            ->and($plan->reports_mode)->toBe(PlatformReportsMode::ADVANCED)
            ->and((float) $plan->price_monthly)->toBe(297.00)
            ->and($plan->is_active)->toBeTrue();
    });

    it('creates business plan with correct attributes', function (): void {
        $plan = PlatformPlan::query()->where('slug', 'business')->first();

        expect($plan)->not()->toBeNull()
            ->and($plan->name)->toBe('Business')
            ->and($plan->limit_users)->toBe(100)
            ->and($plan->chat_channels_limit)->toBe(25)
            ->and($plan->storage_mode)->toBe(PlatformStorageMode::UNLIMITED)
            ->and($plan->storage_limit_bytes)->toBeNull()
            ->and($plan->ai_enabled)->toBeTrue()
            ->and($plan->negotiations_mode)->toBe(PlatformNegotiationsMode::UNLIMITED)
            ->and($plan->negotiations_limit)->toBeNull()
            ->and($plan->reports_mode)->toBe(PlatformReportsMode::FULL)
            ->and((float) $plan->price_monthly)->toBe(897.00)
            ->and($plan->is_active)->toBeTrue();
    });

    it('all plans use uuid primary keys', function (): void {
        $plans = PlatformPlan::query()->get();

        foreach ($plans as $plan) {
            expect(Str::isUuid($plan->id))->toBeTrue();
        }
    });

    it('starter and professional have limited storage and negotiations', function (): void {
        $starter = PlatformPlan::query()->where('slug', 'starter')->first();
        $professional = PlatformPlan::query()->where('slug', 'professional')->first();

        expect($starter->isStorageLimited())->toBeTrue()
            ->and($starter->isNegotiationsLimited())->toBeTrue()
            ->and($professional->isStorageLimited())->toBeTrue()
            ->and($professional->isNegotiationsLimited())->toBeTrue();
    });

    it('business has unlimited storage and negotiations', function (): void {
        $business = PlatformPlan::query()->where('slug', 'business')->first();

        expect($business->isStorageLimited())->toBeFalse()
            ->and($business->isNegotiationsLimited())->toBeFalse();
    });

    it('upsert is idempotent — running twice does not duplicate plans', function (): void {
        $countBefore = PlatformPlan::query()->count();

        $this->seed(PlatformPlanSeeder::class);

        expect(PlatformPlan::query()->count())->toBe($countBefore);
    });

    it('extra seeder creates no plans (intentionally empty)', function (): void {
        $countBefore = PlatformPlan::query()->count();

        $this->seed(PlatformPlanExtraSeeder::class);

        expect(PlatformPlan::query()->count())->toBe($countBefore);
    });
});
