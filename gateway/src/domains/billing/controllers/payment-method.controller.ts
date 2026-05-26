import {
  Body,
  Controller,
  HttpCode,
  HttpStatus,
  Post,
  UseGuards,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';
import { AsaasClient } from '../providers/asaas/asaas.client';
import { ValidatePaymentMethodDto } from '../dto/payment-method.dto';

/**
 * PaymentMethodController
 *
 * Endpoint interno para validação de token de cartão sem cobrar.
 * Consulta metadados do cartão tokenizado (brand, last4, expiry) no Asaas.
 *
 * @remarks
 * PCI SAQ-A: nenhum PAN trafega por este endpoint.
 * Autenticado via X-Internal-Token (InternalApiKeyGuard).
 */
@Controller({ path: 'internal/billing' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class PaymentMethodController {
  constructor(private readonly asaasClient: AsaasClient) {}

  /**
   * Valida um token de cartão e retorna metadados sem cobrar.
   *
   * @param payload - `{ customer_id, token }` emitido pelo SDK Asaas.js
   * @returns `{ brand, last4, expiryMonth, expiryYear }`
   */
  @Post('payment-methods')
  @HttpCode(HttpStatus.OK)
  async validatePaymentMethod(@Body() payload: ValidatePaymentMethodDto) {
    const result = await this.asaasClient.getPaymentMethod(
      payload.token,
      payload.customer_id,
    );

    return {
      brand: result.brand ?? null,
      last4: result.last4 ?? null,
      expiryMonth: result.expiryMonth ?? null,
      expiryYear: result.expiryYear ?? null,
    };
  }
}
