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
   * Sends a billing collection notification via WhatsApp.
   *
   * @param payload - Collection send payload
   * @returns Result with success status, messageId and error
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
