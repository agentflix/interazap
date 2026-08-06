/**
 * MetaWebhookProcessor
 *
 * Processor BullMQ do webhook Meta: recebe o payload bruto já validado por
 * HMAC, resolve tenant/instância por phone_number_id/waba_id (via adapter),
 * normaliza o lote e delega a publicação no Redis Stream para o
 * `ChatWebhookService.handleNormalizedEvents`.
 *
 * Cada job é processado de forma independente — falha em um item do lote é
 * tratada dentro do processor; falha do job inteiro dispara retry/DLQ do
 * BullMQQueueFactory.
 */

import { Injectable, Logger } from '@nestjs/common';
import { MetaAdapter } from '../providers/meta/meta.adapter';
import { ChatWebhookService } from '../services/chat-webhook.service';
import { MetaWebhookQueueJob } from '../services/meta-webhook-queue.service';

@Injectable()
export class MetaWebhookProcessor {
  private readonly logger = new Logger(MetaWebhookProcessor.name);

  constructor(
    private readonly metaAdapter: MetaAdapter,
    private readonly chatWebhookService: ChatWebhookService,
  ) {}

  /**
   * Processa um job de webhook Meta: lookup + normalização + publicação no stream.
   *
   * @param data - Dados do job com o payload bruto do webhook
   */
  async process(data: MetaWebhookQueueJob): Promise<void> {
    const { payload, receivedAt } = data;

    this.logger.debug(
      `Processing Meta webhook job (receivedAt=${receivedAt})`,
    );

    const events = await this.metaAdapter.normalizeWebhookBatch(payload);

    if (events.length === 0) {
      this.logger.warn(
        'Meta webhook job did not produce any resolvable event (unknown phone_number_id/waba_id or empty payload)',
      );
      return;
    }

    await this.chatWebhookService.handleNormalizedEvents(events);
  }
}
