<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Chat\Models\ChatInstance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatInstance>
 */
class ChatInstanceFactory extends Factory
{
    protected $model = ChatInstance::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) Str::orderedUuid(),
            'provider' => 'uazapi',
            'name' => 'instance-'.Str::random(6),
            'mode' => 'production',
            'status' => 'connected',
            'webhook_token' => (string) Str::uuid(),
            'settings_json' => null,
        ];
    }
}
