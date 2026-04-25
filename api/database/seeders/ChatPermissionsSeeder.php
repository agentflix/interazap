<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ChatPermissionsSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private array $permissions = [
        'chat.tickets.view',
        'chat.tickets.create',
        'chat.tickets.update',
        'chat.tickets.delete',
        'chat.messages.view',
        'chat.messages.create',
        'chat.quick_answers.view',
        'chat.quick_answers.create',
        'chat.quick_answers.update',
        'chat.quick_answers.delete',
        'chat.auto_reply_rules.view',
        'chat.auto_reply_rules.create',
        'chat.auto_reply_rules.update',
        'chat.auto_reply_rules.delete',
        'chat.transmission_lists.view',
        'chat.transmission_lists.create',
        'chat.transmission_lists.update',
        'chat.transmission_lists.delete',
    ];

    public function run(): void
    {
        foreach ($this->permissions as $permission) {
            \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum'], ['id' => (string) Str::orderedUuid()]);
        }
    }
}
