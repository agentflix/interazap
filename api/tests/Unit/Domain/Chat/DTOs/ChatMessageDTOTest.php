<?php

declare(strict_types=1);

use Domain\Chat\DTOs\ChatMessageDTO;
use Illuminate\Http\Request;

it('creates dto from request with payload overrides', function (): void {
    $request = Request::create('/', 'POST', [
        'content' => 'Hello!',
        'direction' => 'outgoing',
        'type' => 'file',
        'is_from_contact' => false,
        'external_id' => 'ext-123',
        'contact_id' => 'contact-1',
        'user_id' => 'user-1',
        'metadata' => ['source' => 'widget'],
        'file_url' => 'https://cdn.test/file.pdf',
        'file_name' => 'file.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => '2048',
        'source' => ChatMessageDTO::SOURCE_SYSTEM,
    ]);

    $dto = ChatMessageDTO::fromRequest($request, 'ticket-1');

    expect($dto->ticketId)->toBe('ticket-1')
        ->and($dto->content)->toBe('Hello!')
        ->and($dto->direction)->toBe('outgoing')
        ->and($dto->type)->toBe('file')
        ->and($dto->isFromContact)->toBeFalse()
        ->and($dto->externalId)->toBe('ext-123')
        ->and($dto->contactId)->toBe('contact-1')
        ->and($dto->userId)->toBe('user-1')
        ->and($dto->metadata)->toBe(['source' => 'widget'])
        ->and($dto->fileUrl)->toBe('https://cdn.test/file.pdf')
        ->and($dto->fileName)->toBe('file.pdf')
        ->and($dto->mimeType)->toBe('application/pdf')
        ->and($dto->fileSize)->toBe(2048)
        ->and($dto->source)->toBe(ChatMessageDTO::SOURCE_SYSTEM);
});

it('creates dto from array with defaults and exports array', function (): void {
    $dto = ChatMessageDTO::fromArray([
        'ticket_id' => 'ticket-2',
        'content' => 'Hi',
    ]);

    expect($dto->direction)->toBe('incoming')
        ->and($dto->type)->toBe('text')
        ->and($dto->isFromContact)->toBeTrue()
        ->and($dto->source)->toBe(ChatMessageDTO::SOURCE_WEBHOOK)
        ->and($dto->fileSize)->toBeNull();

    expect($dto->toArray())->toMatchArray([
        'ticket_id' => 'ticket-2',
        'content' => 'Hi',
        'direction' => 'incoming',
        'type' => 'text',
        'is_from_contact' => true,
        'external_id' => null,
        'contact_id' => null,
        'user_id' => null,
        'metadata' => null,
        'file_url' => null,
        'file_name' => null,
        'mime_type' => null,
        'file_size' => null,
        'source' => ChatMessageDTO::SOURCE_WEBHOOK,
    ]);
});
