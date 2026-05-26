import {
  Body,
  Controller,
  Logger,
  Param,
  Post,
  UseGuards,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { BillingWebhookService } from '../services/billing-webhook.service';
import {
  Idempotent,
  IdempotentWebhookGuard,
} from '../../../shared/guards/idempotent-webhook.guard';

/**
 * BillingWebhookController
 *
 * Processa webhooks de billing do provider Asaas.
 * Utiliza idempotência para evitar processamento duplicado.
 */
@Controller({
  path: 'billing/webhooks/:provider/instances/:instance_webhook_token',
})
@UseGuards(IdempotentWebhookGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class BillingWebhookController {
  private readonly logger = new Logger(BillingWebhookController.name);

  constructor(private readonly billingWebhookService: BillingWebhookService) {}

  /**
   * Recebe e processa um evento de webhook do provedor de pagamento.
   * Responde imediatamente com ACK e processa o evento em background (fire-and-forget).
   *
   * @param provider - Nome do provedor de billing (ex.: 'asaas')
   * @param token - Token do webhook da instância do tenant
   * @param payload - Payload do evento recebido
   * @returns Confirmação de sucesso
   */
  @Post()
  @Idempotent({ prefix: 'billing', ttlSeconds: 86400 })
  handle(
    @Param('provider') provider: string,
    @Param('instance_webhook_token') token: string,
    @Body() payload: Record<string, unknown>,
  ): Promise<{ success: boolean }> {
    this.logger.log(`Billing webhook received from ${provider}`);

    // Fire-and-forget: ACK the webhook immediately (< 150ms target).
    // Processing (DB writes, stream publish) runs in the background.
    this.billingWebhookService
      .handle(provider, token, payload)
      .catch((error: unknown) => {
        this.logger.error(
          `Billing webhook processing failed: ${error instanceof Error ? error.message : String(error)}`,
          error instanceof Error ? error.stack : undefined,
        );
      });

    return Promise.resolve({ success: true });
  }
}
