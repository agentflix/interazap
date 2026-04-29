<?php

declare(strict_types=1);

use Domain\Chat\Actions\ChatWebhookIngestor;
use Domain\Chat\Events\MetaTemplateStatusUpdated;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
});

it('despacha MetaTemplateStatusUpdated quando event_type = meta.template.status_updated', function (): void {
    Event::fake([MetaTemplateStatusUpdated::class]);

    $payload = [
        'event_type' => 'meta.template.status_updated',
        'tenant_id' => (string) $this->tenant->id,
        'instance_id' => 'instance-uuid-1',
        'template' => [
            'external_id' => 'ext-123',
            'name' => 'welcome_v1',
            'language' => 'pt_BR',
            'status' => 'APPROVED',
            'reason' => null,
        ],
    ];

    app(ChatWebhookIngestor::class)->ingest((string) $this->tenant->id, $payload);

    Event::assertDispatched(
        MetaTemplateStatusUpdated::class,
        function (MetaTemplateStatusUpdated $event): bool {
            return $event->instanceId === 'instance-uuid-1'
                && $event->templateName === 'welcome_v1'
                && $event->language === 'pt_BR'
                && $event->status === 'APPROVED'
                && $event->externalId === 'ext-123'
                && $event->rejectedReason === null;
        }
    );
});

it('aceita direction=template_status como gatilho alternativo', function (): void {
    Event::fake([MetaTemplateStatusUpdated::class]);

    $payload = [
        'direction' => 'template_status',
        'tenant_id' => (string) $this->tenant->id,
        'instance_id' => 'instance-uuid-2',
        'name' => 'goodbye',
        'language' => 'en',
        'event' => 'REJECTED',
        'reason' => 'Body too short',
    ];

    app(ChatWebhookIngestor::class)->ingest((string) $this->tenant->id, $payload);

    Event::assertDispatched(
        MetaTemplateStatusUpdated::class,
        function (MetaTemplateStatusUpdated $event): bool {
            return $event->status === 'REJECTED'
                && $event->rejectedReason === 'Body too short'
                && $event->templateName === 'goodbye'
                && $event->externalId === null;
        }
    );
});

it('não dispara evento quando payload está incompleto', function (): void {
    Event::fake([MetaTemplateStatusUpdated::class]);

    $payload = [
        'event_type' => 'meta.template.status_updated',
        'tenant_id' => (string) $this->tenant->id,
        // sem instance_id, name, language, status
    ];

    app(ChatWebhookIngestor::class)->ingest((string) $this->tenant->id, $payload);

    Event::assertNotDispatched(MetaTemplateStatusUpdated::class);
});
