<?php

declare(strict_types=1);

use Domain\Chat\DTOs\ChatTicketDTO;
use Illuminate\Http\Request;

it('creates ticket dto from request with defaults', function (): void {
    $request = Request::create('/', 'POST', [
        'contact_id' => 'contact-1',
        'instance_id' => 'instance-1',
        'remote_jid' => 'remote-1',
        'subject' => 'Support',
        'priority' => 'high',
        'push_name' => 'John Doe',
        'profile_picture_url' => 'https://cdn.test/avatar.png',
        'phone' => '+55 11 99999-0000',
        'phone_e164' => '+5511999990000',
    ]);

    $dto = ChatTicketDTO::fromRequest($request);

    expect($dto->channel)->toBe('whatsapp')
        ->and($dto->contactId)->toBe('contact-1')
        ->and($dto->instanceId)->toBe('instance-1')
        ->and($dto->remoteJid)->toBe('remote-1')
        ->and($dto->subject)->toBe('Support')
        ->and($dto->priority)->toBe('high')
        ->and($dto->pushName)->toBe('John Doe')
        ->and($dto->profilePictureUrl)->toBe('https://cdn.test/avatar.png')
        ->and($dto->phone)->toBe('+55 11 99999-0000')
        ->and($dto->phoneE164)->toBe('+5511999990000');
});

it('creates ticket dto from array and exports array', function (): void {
    $dto = ChatTicketDTO::fromArray([
        'channel' => 'whatsapp',
        'contact_id' => 'contact-2',
        'instance_id' => 'instance-2',
    ]);

    expect($dto->channel)->toBe('whatsapp')
        ->and($dto->priority)->toBe('normal')
        ->and($dto->subject)->toBeNull();

    expect($dto->toArray())->toMatchArray([
        'channel' => 'whatsapp',
        'contact_id' => 'contact-2',
        'instance_id' => 'instance-2',
        'remote_jid' => null,
        'subject' => null,
        'priority' => 'normal',
        'push_name' => null,
        'profile_picture_url' => null,
        'phone' => null,
        'phone_e164' => null,
    ]);
});
