<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Actions\ChatTicketActions;
use Domain\Chat\Actions\ChatWebhookIngestor;
use Domain\Chat\DTOs\ChatTicketDTO;
use Domain\Chat\Http\Resources\ChatTicketResource;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Http\Resources\CRMContactResource;
use Domain\CRM\Models\CRMContact;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = AuthUser::factory()->create();
    $this->tenantId = (string) $this->user->tenant_id;

    $this->instance = ChatInstance::factory()->create([
        'tenant_id' => $this->tenantId,
    ]);

    $this->mock(\Domain\Chat\Services\ChatGatewayService::class, function ($mock): void {
        $mock->shouldIgnoreMissing();
    });
});

// ---------------------------------------------------------------------------
// findOrCreateByRemoteJid — profile picture on existing ticket
// ---------------------------------------------------------------------------

it('updates profile_picture_url on existing ticket when picture changes', function (): void {
    $remoteJid = '5511999990001@s.whatsapp.net';
    $oldPicture = 'https://pps.whatsapp.net/v/t61.24694-24/old.jpg';
    $newPicture = 'https://pps.whatsapp.net/v/t61.24694-24/new.jpg';

    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $this->tenantId,
        'instance_id' => $this->instance->id,
        'remote_jid' => $remoteJid,
        'status' => 'open',
        'profile_picture_url' => $oldPicture,
    ]);

    $dto = ChatTicketDTO::fromArray([
        'channel' => 'whatsapp',
        'instance_id' => $this->instance->id,
        'remote_jid' => $remoteJid,
        'profile_picture_url' => $newPicture,
    ]);

    $result = app(ChatTicketActions::class)->findOrCreateByRemoteJid($this->tenantId, $dto);

    expect($result->id)->toBe($ticket->id)
        ->and($result->profile_picture_url)->toBe($newPicture);

    // Confirm persisted
    $ticket->refresh();
    expect($ticket->profile_picture_url)->toBe($newPicture);
});

// ---------------------------------------------------------------------------
// findOrCreateByRemoteJid — syncs avatar to CRM contact
// ---------------------------------------------------------------------------

it('syncs avatar_url to CRM contact when profile picture changes', function (): void {
    $remoteJid = '5511999990002@s.whatsapp.net';
    $oldPicture = 'https://pps.whatsapp.net/v/t61.24694-24/old.jpg';
    $newPicture = 'https://pps.whatsapp.net/v/t61.24694-24/new.jpg';

    $contact = CRMContact::factory()->create([
        'tenant_id' => $this->tenantId,
        'avatar_url' => $oldPicture,
        'whatsapp' => '5511999990002',
    ]);

    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $this->tenantId,
        'instance_id' => $this->instance->id,
        'remote_jid' => $remoteJid,
        'contact_id' => $contact->id,
        'status' => 'open',
        'profile_picture_url' => $oldPicture,
    ]);

    $dto = ChatTicketDTO::fromArray([
        'channel' => 'whatsapp',
        'instance_id' => $this->instance->id,
        'remote_jid' => $remoteJid,
        'profile_picture_url' => $newPicture,
    ]);

    app(ChatTicketActions::class)->findOrCreateByRemoteJid($this->tenantId, $dto);

    $contact->refresh();
    expect($contact->avatar_url)->toBe($newPicture);
});

// ---------------------------------------------------------------------------
// findOrCreateByRemoteJid — skips update when picture unchanged
// ---------------------------------------------------------------------------

it('does not update ticket when profile picture is unchanged', function (): void {
    $remoteJid = '5511999990003@s.whatsapp.net';
    $samePicture = 'https://pps.whatsapp.net/v/t61.24694-24/same.jpg';

    $contact = CRMContact::factory()->create([
        'tenant_id' => $this->tenantId,
        'avatar_url' => $samePicture,
        'whatsapp' => '5511999990003',
    ]);

    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $this->tenantId,
        'instance_id' => $this->instance->id,
        'remote_jid' => $remoteJid,
        'contact_id' => $contact->id,
        'status' => 'open',
        'profile_picture_url' => $samePicture,
    ]);

    $originalUpdatedAt = $contact->updated_at;

    // Advance time so we can detect unwanted writes
    $this->travel(5)->seconds();

    $dto = ChatTicketDTO::fromArray([
        'channel' => 'whatsapp',
        'instance_id' => $this->instance->id,
        'remote_jid' => $remoteJid,
        'profile_picture_url' => $samePicture,
    ]);

    app(ChatTicketActions::class)->findOrCreateByRemoteJid($this->tenantId, $dto);

    $contact->refresh();
    // Contact avatar should remain the same and not trigger an update
    expect($contact->avatar_url)->toBe($samePicture)
        ->and($contact->updated_at->toDateTimeString())
        ->toBe($originalUpdatedAt->toDateTimeString());
});

// ---------------------------------------------------------------------------
// findOrCreateContact — creates contact with avatar_url
// ---------------------------------------------------------------------------

it('creates a new contact with avatar_url when ticket is created via webhook', function (): void {
    $remoteJid = '5511999990004@s.whatsapp.net';
    $pictureUrl = 'https://pps.whatsapp.net/v/t61.24694-24/avatar.jpg';

    $dto = ChatTicketDTO::fromArray([
        'channel' => 'whatsapp',
        'instance_id' => $this->instance->id,
        'remote_jid' => $remoteJid,
        'push_name' => 'João WhatsApp',
        'profile_picture_url' => $pictureUrl,
    ]);

    $ticket = app(ChatTicketActions::class)->findOrCreateByRemoteJid($this->tenantId, $dto);

    expect($ticket->contact_id)->not->toBeNull();

    $contact = CRMContact::query()->find($ticket->contact_id);
    expect($contact)->not->toBeNull()
        ->and($contact->avatar_url)->toBe($pictureUrl)
        ->and($contact->name)->toBe('João WhatsApp');
});

// ---------------------------------------------------------------------------
// findOrCreateContact — updates existing contact avatar_url
// ---------------------------------------------------------------------------

it('updates avatar_url on existing contact when picture changes via ticket creation', function (): void {
    $phone = '5511999990005';
    $remoteJid = $phone.'@s.whatsapp.net';
    $oldPicture = 'https://pps.whatsapp.net/v/t61.24694-24/old-avatar.jpg';
    $newPicture = 'https://pps.whatsapp.net/v/t61.24694-24/new-avatar.jpg';

    $contact = CRMContact::factory()->create([
        'tenant_id' => $this->tenantId,
        'whatsapp' => $phone,
        'avatar_url' => $oldPicture,
    ]);

    // No active ticket exists, so findOrCreateByRemoteJid will create a new one
    // and internally call findOrCreateContact which should update the contact avatar
    $dto = ChatTicketDTO::fromArray([
        'channel' => 'whatsapp',
        'instance_id' => $this->instance->id,
        'remote_jid' => $remoteJid,
        'push_name' => 'Maria',
        'profile_picture_url' => $newPicture,
    ]);

    $ticket = app(ChatTicketActions::class)->findOrCreateByRemoteJid($this->tenantId, $dto);

    expect($ticket->contact_id)->toBe($contact->id);

    $contact->refresh();
    expect($contact->avatar_url)->toBe($newPicture);
});

// ---------------------------------------------------------------------------
// ChatTicketResource — exposes contact.profile_picture_url
// ---------------------------------------------------------------------------

it('includes profile_picture_url at ticket level and contact.profile_picture_url in resource', function (): void {
    $pictureUrl = 'https://pps.whatsapp.net/v/t61.24694-24/resource.jpg';

    $contact = CRMContact::factory()->create([
        'tenant_id' => $this->tenantId,
        'avatar_url' => $pictureUrl,
    ]);

    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $this->tenantId,
        'contact_id' => $contact->id,
        'profile_picture_url' => $pictureUrl,
    ]);

    $ticket->load('contact');

    $resource = (new ChatTicketResource($ticket))->toArray(request());

    expect($resource['profile_picture_url'])->toBe($pictureUrl)
        ->and($resource['contact']['profile_picture_url'])->toBe($pictureUrl);
});

// ---------------------------------------------------------------------------
// CRMContactResource — exposes avatar_url
// ---------------------------------------------------------------------------

it('includes avatar_url in CRMContactResource', function (): void {
    $pictureUrl = 'https://pps.whatsapp.net/v/t61.24694-24/crm-avatar.jpg';

    $contact = CRMContact::factory()->create([
        'tenant_id' => $this->tenantId,
        'avatar_url' => $pictureUrl,
    ]);

    $resource = (new CRMContactResource($contact))->toArray(request());

    expect($resource['avatar_url'])->toBe($pictureUrl);
});

// ---------------------------------------------------------------------------
// ChatWebhookIngestor — filter_var FILTER_VALIDATE_URL
// ---------------------------------------------------------------------------

it('filters out invalid profile_picture_url via ChatWebhookIngestor', function (): void {
    \Illuminate\Support\Facades\Event::fake([
        \Domain\Chat\Events\MessagePersisted::class,
        \Domain\Configuration\Events\TicketCreatedEvent::class,
        \Domain\Configuration\Events\TicketAssignedEvent::class,
        \Domain\Ai\Events\AutopilotTriggerFired::class,
    ]);

    $this->instance->update([
        'webhook_token' => 'test-token-profile-pic',
        'settings_json' => ['token' => 'test-token-profile-pic'],
    ]);

    $payload = [
        'provider' => 'uazapi',
        'event_type' => 'messages',
        'instance_webhook_token' => 'test-token-profile-pic',
        'tenant_id' => $this->tenantId,
        'instance_id' => $this->instance->id,
        'direction' => 'incoming',
        'channel' => 'whatsapp',
        'remote_jid' => '5511999990006@s.whatsapp.net',
        'push_name' => 'Invalid Pic User',
        'profile_picture_url' => 'not-a-valid-url',
        'message' => [
            'id' => (string) Str::uuid(),
            'from' => '5511999990006@s.whatsapp.net',
            'to' => 'me',
            'body' => 'Hello',
            'type' => 'text',
            'chatid' => '5511999990006@s.whatsapp.net',
            'fromMe' => false,
        ],
    ];

    app(ChatWebhookIngestor::class)->ingest($this->tenantId, $payload);

    $ticket = ChatTicket::query()
        ->where('tenant_id', $this->tenantId)
        ->where('remote_jid', '5511999990006@s.whatsapp.net')
        ->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->profile_picture_url)->toBeNull();
});
