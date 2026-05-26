import {
  Body,
  Controller,
  Post,
  UseGuards,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';
import { BillingCollectionSendDto } from '../dto/billing-collection.dto';
import { BillingCollectionService } from '../services/billing-collection.service';

/**
 * BillingCollectionController
 *
 * Gerencia o envio de notificações de cobrança via WhatsApp
 * através do provider de SMS/Voice configurado.
 */
@Controller({ path: 'internal/billing/collection' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class BillingCollectionController {
  constructor(
    private readonly billingCollectionService: BillingCollectionService,
  ) {}

  /**
   * Envia uma notificação de cobrança via WhatsApp.
   *
   * @param payload - Dados para envio da cobrança (tenantId, telefone, templateId, variáveis)
   * @returns Resultado com status de sucesso, messageId e erro
   */
  @Post('send')
  async send(@Body() payload: BillingCollectionSendDto) {
    const result = await this.billingCollectionService.send(
      payload.tenantId,
      payload.phone,
      payload.templateId,
      payload.variables ?? {},
    );

    return {
      success: result.success,
      messageId: result.messageId,
      error: result.error,
    };
  }
}
