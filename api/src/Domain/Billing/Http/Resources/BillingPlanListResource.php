<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Resources;

use Domain\Platform\Models\PlatformPlan;
use Domain\Shared\Http\Resources\BaseJsonResource;
use Illuminate\Http\Request;

/**
 * Resource para serialização do catálogo de planos (visão tenant).
 *
 * Inclui limites, features e informações de excedente de cada plano disponível.
 */
final class BillingPlanListResource extends BaseJsonResource
{
    /**
     * @return array<string, mixed>
     */
    protected function data(Request $request): array
    {
        /** @var PlatformPlan $plan */
        $plan = $this->resource;

        return [
            'id' => $plan->id,
            'name' => $plan->name,
            'slug' => $plan->slug,
            'price_monthly' => number_format((float) $plan->price_monthly, 2, '.', ''),
            'limit_users' => (int) $plan->limit_users,
            'chat_channels_limit' => (int) $plan->chat_channels_limit,
            'storage_mode' => $plan->storage_mode->value,
            'storage_limit_bytes' => $plan->storage_limit_bytes,
            'storage_formatted' => $plan->storage_limit_bytes ? $this->formatBytes((int) $plan->storage_limit_bytes) : 'Ilimitado',
            'ai_enabled' => (bool) $plan->ai_enabled,
            'negotiations_mode' => $plan->negotiations_mode->value,
            'negotiations_limit' => $plan->negotiations_limit,
            'is_current' => (bool) ($plan->is_current ?? false),
            'message_limit_monthly' => $plan->message_limit_monthly,
            'overage_mode' => $plan->overage_mode instanceof \BackedEnum ? $plan->overage_mode->value : $plan->overage_mode,
            'overage_price_per_message' => $plan->overage_price_per_message,
            'cycle_days' => (int) ($plan->cycle_days ?? 30),
            'is_trial' => (bool) ($plan->is_trial ?? false),
            'features' => [
                ['label' => sprintf('%d usuários', (int) $plan->limit_users), 'included' => true],
                ['label' => sprintf('%d canal WhatsApp', (int) $plan->chat_channels_limit), 'included' => true],
                ['label' => $plan->storage_limit_bytes ? $this->formatBytes((int) $plan->storage_limit_bytes).' de storage' : 'Storage ilimitado', 'included' => true],
                ['label' => 'Chatbot (respostas automáticas)', 'included' => true],
                ['label' => 'Inteligência Artificial', 'included' => (bool) $plan->ai_enabled],
                ['label' => 'Negociações ilimitadas', 'included' => true],
            ],
        ];
    }

    /** Formata bytes para unidade legível (B, KB, MB, GB, TB). */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $size = $bytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return number_format($size, 1).' '.$units[$unit];
    }
}
