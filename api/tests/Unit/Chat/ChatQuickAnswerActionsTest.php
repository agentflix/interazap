<?php

declare(strict_types=1);

use Domain\Chat\Actions\ChatQuickAnswerActions;
use Domain\Chat\DTOs\ChatQuickAnswerDTO;
use Domain\Chat\Models\ChatQuickAnswer;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();
    $this->actions = new ChatQuickAnswerActions;
});

describe('list', function (): void {
    it('returns paginated quick answers for tenant', function (): void {
        ChatQuickAnswer::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->list($this->tenant->id);

        expect($result)->toBeInstanceOf(\Illuminate\Pagination\LengthAwarePaginator::class)
            ->and($result->total())->toBe(5);
    });

    it('filters by search term in name', function (): void {
        ChatQuickAnswer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Greeting message',
            'shortcut' => '/greet',
        ]);

        ChatQuickAnswer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Farewell',
            'shortcut' => '/bye',
        ]);

        $result = $this->actions->list($this->tenant->id, ['search' => 'Greeting']);

        expect($result->total())->toBe(1);
    });

    it('filters by search term in shortcut', function (): void {
        ChatQuickAnswer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Some answer',
            'shortcut' => '/special',
        ]);

        ChatQuickAnswer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Other answer',
            'shortcut' => '/other',
        ]);

        $result = $this->actions->list($this->tenant->id, ['search' => 'special']);

        expect($result->total())->toBe(1);
    });

    it('filters by category', function (): void {
        ChatQuickAnswer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category' => 'sales',
        ]);

        ChatQuickAnswer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category' => 'support',
        ]);

        $result = $this->actions->list($this->tenant->id, ['category' => 'sales']);

        expect($result->total())->toBe(1);
    });

    it('filters by is_active status', function (): void {
        ChatQuickAnswer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        ChatQuickAnswer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
        ]);

        $result = $this->actions->list($this->tenant->id, ['is_active' => true]);

        expect($result->total())->toBe(1);
    });

    it('excludes other tenant data', function (): void {
        $otherTenant = PlatformTenant::factory()->create();

        ChatQuickAnswer::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        ChatQuickAnswer::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $result = $this->actions->list($this->tenant->id);

        expect($result->total())->toBe(3);
    });
});

describe('listAllActive', function (): void {
    it('returns all active quick answers ordered by name', function (): void {
        ChatQuickAnswer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Zebra',
            'is_active' => true,
        ]);

        ChatQuickAnswer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alpha',
            'is_active' => true,
        ]);

        ChatQuickAnswer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inactive',
            'is_active' => false,
        ]);

        $result = $this->actions->listAllActive($this->tenant->id);

        expect($result)->toHaveCount(2)
            ->and($result->first()->name)->toBe('Alpha')
            ->and($result->last()->name)->toBe('Zebra');
    });
});

describe('create', function (): void {
    it('creates a new quick answer', function (): void {
        $dto = new ChatQuickAnswerDTO(
            name: 'Welcome',
            content: 'Welcome to our service!',
            shortcut: '/welcome',
            category: 'greetings',
            isActive: true,
        );

        $result = $this->actions->create($this->tenant->id, $dto);

        expect($result)->toBeInstanceOf(ChatQuickAnswer::class)
            ->and($result->name)->toBe('Welcome')
            ->and($result->shortcut)->toBe('/welcome')
            ->and($result->content)->toBe('Welcome to our service!')
            ->and($result->tenant_id)->toBe($this->tenant->id);
    });
});

describe('update', function (): void {
    it('updates an existing quick answer', function (): void {
        $qa = ChatQuickAnswer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old name',
        ]);

        $dto = new ChatQuickAnswerDTO(
            name: 'New name',
            content: 'Updated content',
            shortcut: '/new',
            category: 'updated',
            isActive: true,
        );

        $result = $this->actions->update($this->tenant->id, $qa->id, $dto);

        expect($result->name)->toBe('New name')
            ->and($result->shortcut)->toBe('/new');
    });
});

describe('delete', function (): void {
    it('deletes a quick answer', function (): void {
        $qa = ChatQuickAnswer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actions->delete($this->tenant->id, $qa->id);

        expect(\Domain\Chat\Models\ChatQuickAnswer::query()->find($qa->id))->toBeNull();
    });
});

describe('find', function (): void {
    it('finds a quick answer by id', function (): void {
        $qa = ChatQuickAnswer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->actions->find($this->tenant->id, $qa->id);

        expect($result->id)->toBe($qa->id);
    });

    it('throws exception when quick answer not found', function (): void {
        $missingId = Str::uuid()->toString();
        expect(fn () => $this->actions->find($this->tenant->id, $missingId))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });

    it('throws exception when accessing other tenant data', function (): void {
        $otherTenant = PlatformTenant::factory()->create();
        $qa = ChatQuickAnswer::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        expect(fn () => $this->actions->find($this->tenant->id, $qa->id))
            ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    });
});
