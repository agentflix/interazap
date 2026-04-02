<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ChatTicket>
 */
class ChatTicketFactory extends Factory
{
    protected $model = ChatTicket::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => PlatformTenant::factory(),
            'channel' => 'whatsapp',
            'remote_jid' => '55'.$this->faker->numerify('###########').'@wa',
            'status' => 'pending',
            'priority' => 'normal',
            'subject' => 'Support',
        ];
    }

    public function forTenant(string $tenantId): self
    {
        return $this->state(fn (): array => ['tenant_id' => $tenantId]);
    }
}
