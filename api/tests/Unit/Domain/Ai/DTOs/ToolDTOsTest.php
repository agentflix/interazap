<?php

declare(strict_types=1);

use Domain\Ai\DTOs\ToolInputDTO;
use Domain\Ai\DTOs\ToolResultDTO;

describe('ToolInputDTO', function (): void {
    it('creates from constructor with all fields', function (): void {
        $dto = new ToolInputDTO(
            toolName: 'update_lead_score',
            parameters: ['score' => 90],
            context: ['ticket_id' => 'uuid-123', 'negotiation_id' => 'uuid-456']
        );

        expect($dto->toolName)->toBe('update_lead_score')
            ->and($dto->parameters)->toBe(['score' => 90])
            ->and($dto->context)->toBe(['ticket_id' => 'uuid-123', 'negotiation_id' => 'uuid-456']);
    });

    it('has default empty context', function (): void {
        $dto = new ToolInputDTO(
            toolName: 'get_contact_info',
            parameters: []
        );

        expect($dto->context)->toBe([]);
    });

    it('can be created from array', function (): void {
        $dto = ToolInputDTO::fromArray([
            'tool_name' => 'create_note',
            'parameters' => ['content' => 'Test note'],
            'context' => ['user_id' => 'user-1'],
        ]);

        expect($dto->toolName)->toBe('create_note')
            ->and($dto->parameters)->toBe(['content' => 'Test note'])
            ->and($dto->context)->toBe(['user_id' => 'user-1']);
    });
});

describe('ToolResultDTO', function (): void {
    it('creates success result', function (): void {
        $dto = ToolResultDTO::success('Lead score updated to 90', ['new_score' => 90]);

        expect($dto->success)->toBeTrue()
            ->and($dto->message)->toBe('Lead score updated to 90')
            ->and($dto->data)->toBe(['new_score' => 90]);
    });

    it('creates failure result', function (): void {
        $dto = ToolResultDTO::failure('Score must be between 0 and 100');

        expect($dto->success)->toBeFalse()
            ->and($dto->message)->toBe('Score must be between 0 and 100')
            ->and($dto->data)->toBe([]);
    });

    it('can be converted to array', function (): void {
        $dto = ToolResultDTO::success('Done', ['id' => 'new-id']);

        $array = $dto->toArray();

        expect($array)->toBe([
            'success' => true,
            'message' => 'Done',
            'data' => ['id' => 'new-id'],
        ]);
    });
});
