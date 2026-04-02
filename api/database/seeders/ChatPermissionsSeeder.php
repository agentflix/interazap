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
        'chat.chatbot_rules.view',
        'chat.chatbot_rules.create',
        'chat.chatbot_rules.update',
        'chat.chatbot_rules.delete',
        'chat.campaigns.view',
        'chat.campaigns.create',
        'chat.campaigns.update',
        'chat.campaigns.delete',
    ];

    public function run(): void
    {
        foreach ($this->permissions as $permission) {
            \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'sanctum'], ['id' => (string) Str::orderedUuid()]);
        }
    }
}
