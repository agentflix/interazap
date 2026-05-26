<?php

declare(strict_types=1);

namespace Domain\Chat\Listeners;

use Domain\Chat\Events\MetaTemplateStatusUpdated;
use Domain\Chat\Models\ChatMessageTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Listener que reage ao evento {@see MetaTemplateStatusUpdated} para refletir
 * o status do template Meta na base local. Localiza o registro por
 * (chat_instance_id, external_id) preferencialmente, com fallback para
 * (chat_instance_id, name, language). Não cria registros novos.
 */
final class UpdateMetaTemplateStatusListener implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    /**
     * Localiza o template Meta local e atualiza seu status e motivo de rejeição.
     */
    public function handle(MetaTemplateStatusUpdated $event): void
    {
        $query = ChatMessageTemplate::query()
            ->where('chat_instance_id', $event->instanceId);

        $template = null;

        if ($event->externalId !== null && $event->externalId !== '') {
            $template = (clone $query)
                ->where('external_id', $event->externalId)
                ->first();
        }

        if ($template === null) {
            $template = $query
                ->where('name', $event->templateName)
                ->where('language', $event->language)
                ->first();
        }

        if (! $template instanceof ChatMessageTemplate) {
            Log::warning('[UpdateMetaTemplateStatusListener] Template Meta não encontrado para webhook', [
                'tenant_id' => $event->tenantId,
                'instance_id' => $event->instanceId,
                'template_name' => $event->templateName,
                'language' => $event->language,
                'status' => $event->status,
            ]);

            return;
        }

        $template->forceFill([
            'status' => strtolower($event->status),
            'rejected_reason' => $event->rejectedReason,
            'last_synced_at' => Carbon::now(),
        ])->save();
    }
}
