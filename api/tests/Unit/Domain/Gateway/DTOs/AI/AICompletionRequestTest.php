<?php

declare(strict_types=1);

use Domain\Gateway\DTOs\AI\AICompletionRequest;

describe('AICompletionRequest', function (): void {
    it('creates request with messages via create()', function (): void {
        $messages = [
            ['role' => 'system', 'content' => 'You are a helpful assistant.'],
            ['role' => 'user', 'content' => 'Hello!'],
        ];

        $request = AICompletionRequest::create($messages);

        expect($request->messages)->toBe($messages)
            ->and($request->model)->toBeNull()
            ->and($request->maxTokens)->toBeNull()
            ->and($request->temperature)->toBeNull()
            ->and($request->stream)->toBeFalse();
    });

    it('sets model via withModel fluent builder', function (): void {
        $request = AICompletionRequest::create([])
            ->withModel('gpt-4o');

        expect($request->model)->toBe('gpt-4o')
            ->and($request->messages)->toBe([]);
    });

    it('sets maxTokens via withMaxTokens fluent builder', function (): void {
        $request = AICompletionRequest::create([])
            ->withMaxTokens(4096);

        expect($request->maxTokens)->toBe(4096);
    });

    it('sets temperature via withTemperature fluent builder', function (): void {
        $request = AICompletionRequest::create([])
            ->withTemperature(0.7);

        expect($request->temperature)->toBe(0.7);
    });

    it('sets stream via withStream fluent builder', function (): void {
        $request = AICompletionRequest::create([])
            ->withStream(true);

        expect($request->stream)->toBeTrue();
    });

    it('chains multiple fluent builders correctly', function (): void {
        $messages = [['role' => 'user', 'content' => 'Test']];

        $request = AICompletionRequest::create($messages)
            ->withModel('gpt-4o')
            ->withMaxTokens(2048)
            ->withTemperature(0.5)
            ->withStream(true);

        expect($request->messages)->toBe($messages)
            ->and($request->model)->toBe('gpt-4o')
            ->and($request->maxTokens)->toBe(2048)
            ->and($request->temperature)->toBe(0.5)
            ->and($request->stream)->toBeTrue();
    });

    it('filters null values in toArray()', function (): void {
        $request = AICompletionRequest::create([
            ['role' => 'user', 'content' => 'Hello'],
        ]);

        $array = $request->toArray();

        expect($array)->toHaveKeys(['messages', 'stream'])
            ->and($array)->not->toHaveKey('model')
            ->and($array)->not->toHaveKey('maxTokens')
            ->and($array)->not->toHaveKey('temperature');
    });

    it('includes all non-null values in toArray()', function (): void {
        $request = AICompletionRequest::create([['role' => 'user', 'content' => 'Hi']])
            ->withModel('gpt-4o')
            ->withMaxTokens(1000)
            ->withTemperature(0.8)
            ->withStream(true);

        $array = $request->toArray();

        expect($array)->toBe([
            'messages' => [['role' => 'user', 'content' => 'Hi']],
            'model' => 'gpt-4o',
            'maxTokens' => 1000,
            'temperature' => 0.8,
            'stream' => true,
        ]);
    });

    it('preserves immutability when using fluent builders', function (): void {
        $original = AICompletionRequest::create([['role' => 'user', 'content' => 'Test']]);
        $modified = $original->withModel('gpt-4o');

        expect($original->model)->toBeNull()
            ->and($modified->model)->toBe('gpt-4o')
            ->and($original)->not->toBe($modified);
    });
});
