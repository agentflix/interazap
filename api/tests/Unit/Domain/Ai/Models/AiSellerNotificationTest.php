<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Ai\Models;

use Domain\Ai\Enums\AiNotificationChannel;
use Domain\Ai\Enums\AiNotificationReason;
use Domain\Ai\Models\AiSellerNotification;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * @group ai
 * @group notifications
 */
class AiSellerNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_can_be_created_with_factory(): void
    {
        $notification = AiSellerNotification::factory()->create();

        expect($notification)->toBeInstanceOf(AiSellerNotification::class);
        expect($notification->id)->toBeString();
        expect($notification->seller_id)->toBeString();
        expect($notification->message)->toBeString();
    }

    public function test_it_has_correct_table_name(): void
    {
        $notification = new AiSellerNotification;
        expect($notification->getTable())->toBe('ai_seller_notifications');
    }

    public function test_it_casts_channel_to_enum(): void
    {
        $notification = AiSellerNotification::factory()->create([
            'channel' => 'email',
        ]);

        expect($notification->channel)->toBe(AiNotificationChannel::EMAIL);
    }

    public function test_it_casts_reason_to_enum(): void
    {
        $notification = AiSellerNotification::factory()->create([
            'reason' => 'hot_lead',
        ]);

        expect($notification->reason)->toBe(AiNotificationReason::HOT_LEAD);
    }

    public function test_it_tracks_delivery_attempts(): void
    {
        $notification = AiSellerNotification::factory()->create([
            'attempts' => 0,
        ]);

        $notification->incrementAttempts();

        expect($notification->refresh()->attempts)->toBe(1);
    }

    public function test_it_marks_as_delivered(): void
    {
        $notification = AiSellerNotification::factory()->create([
            'delivered_at' => null,
        ]);

        $notification->markAsDelivered();

        expect($notification->refresh()->delivered_at)->not->toBeNull();
        expect($notification->isDelivered())->toBeTrue();
    }

    public function test_it_marks_as_failed(): void
    {
        $notification = AiSellerNotification::factory()->create([
            'failed_at' => null,
        ]);

        $notification->markAsFailed('Connection timeout');

        expect($notification->refresh()->failed_at)->not->toBeNull();
        expect($notification->error_message)->toBe('Connection timeout');
    }

    public function test_it_falls_back_to_whatsapp_after_email_failure(): void
    {
        $notification = AiSellerNotification::factory()->create([
            'channel' => 'email',
            'attempts' => 3,
        ]);

        $notification->switchToFallbackChannel();

        expect($notification->refresh()->channel)->toBe(AiNotificationChannel::WHATSAPP);
        expect($notification->attempts)->toBe(0);
    }

    public function test_it_belongs_to_tenant(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $notification = AiSellerNotification::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        expect($notification->tenant)->toBeInstanceOf(PlatformTenant::class);
        expect($notification->tenant->id)->toBe($tenant->id);
    }

    public function test_it_belongs_to_seller(): void
    {
        $seller = AuthUser::factory()->create();
        $notification = AiSellerNotification::factory()->create([
            'seller_id' => $seller->id,
        ]);

        expect($notification->seller)->toBeInstanceOf(AuthUser::class);
        expect($notification->seller->id)->toBe($seller->id);
    }

    public function test_it_scopes_pending_notifications(): void
    {
        AiSellerNotification::factory()->create(['delivered_at' => null, 'failed_at' => null]);
        AiSellerNotification::factory()->create(['delivered_at' => now(), 'failed_at' => null]);
        AiSellerNotification::factory()->create(['delivered_at' => null, 'failed_at' => now()]);

        $pending = AiSellerNotification::pending()->get();

        expect($pending)->toHaveCount(1);
    }

    public function test_it_orders_by_priority(): void
    {
        AiSellerNotification::factory()->create(['priority' => 'low']);
        AiSellerNotification::factory()->create(['priority' => 'urgent']);
        AiSellerNotification::factory()->create(['priority' => 'high']);

        $notifications = AiSellerNotification::orderByPriority()->get();

        expect($notifications->first()->priority)->toBe('urgent');
    }
}
