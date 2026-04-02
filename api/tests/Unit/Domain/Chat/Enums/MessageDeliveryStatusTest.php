<?php

declare(strict_types=1);

use Domain\Chat\Enums\MessageDeliveryStatus;

it('maps uzapi status values to delivery status', function (): void {
    expect(MessageDeliveryStatus::fromUzapi('pending'))->toBe(MessageDeliveryStatus::PENDING)
        ->and(MessageDeliveryStatus::fromUzapi('queued'))->toBe(MessageDeliveryStatus::PENDING)
        ->and(MessageDeliveryStatus::fromUzapi('sent'))->toBe(MessageDeliveryStatus::SENT)
        ->and(MessageDeliveryStatus::fromUzapi('delivered'))->toBe(MessageDeliveryStatus::DELIVERED)
        ->and(MessageDeliveryStatus::fromUzapi('read'))->toBe(MessageDeliveryStatus::READ)
        ->and(MessageDeliveryStatus::fromUzapi('played'))->toBe(MessageDeliveryStatus::PLAYED)
        ->and(MessageDeliveryStatus::fromUzapi('error'))->toBe(MessageDeliveryStatus::FAILED)
        ->and(MessageDeliveryStatus::fromUzapi('failed'))->toBe(MessageDeliveryStatus::FAILED)
        ->and(MessageDeliveryStatus::fromUzapi('unknown'))->toBe(MessageDeliveryStatus::PENDING);
});

it('maps zapi status values to delivery status', function (): void {
    expect(MessageDeliveryStatus::fromZApi('PENDING'))->toBe(MessageDeliveryStatus::PENDING)
        ->and(MessageDeliveryStatus::fromZApi('SENT'))->toBe(MessageDeliveryStatus::SENT)
        ->and(MessageDeliveryStatus::fromZApi('RECEIVED'))->toBe(MessageDeliveryStatus::DELIVERED)
        ->and(MessageDeliveryStatus::fromZApi('READ'))->toBe(MessageDeliveryStatus::READ)
        ->and(MessageDeliveryStatus::fromZApi('PLAYED'))->toBe(MessageDeliveryStatus::PLAYED)
        ->and(MessageDeliveryStatus::fromZApi('ERROR'))->toBe(MessageDeliveryStatus::FAILED)
        ->and(MessageDeliveryStatus::fromZApi('FAILED'))->toBe(MessageDeliveryStatus::FAILED)
        ->and(MessageDeliveryStatus::fromZApi('UNKNOWN'))->toBe(MessageDeliveryStatus::PENDING);
});
