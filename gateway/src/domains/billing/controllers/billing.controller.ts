import {
  Body,
  Controller,
  Get,
  Param,
  Post,
  UseGuards,
  UsePipes,
  ValidationPipe,
} from '@nestjs/common';
import { InternalApiKeyGuard } from '../../realtime/guards/internal-api-key.guard';
import { AsaasClient } from '../providers/asaas/asaas.client';
import { CreateAsaasCustomerDto } from '../dto/asaas-customer.dto';
import { CreateAsaasPaymentDto } from '../dto/asaas-payment.dto';

/**
 * BillingController
 *
 * Endpoints internos para operações de billing via API Asaas.
 * Gerencia clientes, pagamentos e geração de QR Codes PIX.
 */
@Controller({ path: 'internal/billing' })
@UseGuards(InternalApiKeyGuard)
@UsePipes(new ValidationPipe({ whitelist: true, transform: true }))
export class BillingController {
  constructor(private readonly asaasClient: AsaasClient) {}

  /**
   * Creates a new customer in Asaas.
   *
   * @param payload - Customer creation data
   * @returns Created customer data
   */
  @Post('customers')
  createCustomer(@Body() payload: CreateAsaasCustomerDto) {
    return this.asaasClient.createCustomer(payload);
  }

  /**
   * Creates a new payment in Asaas.
   *
   * @param payload - Payment creation data
   * @returns Created payment data
   */
  @Post('payments')
  createPayment(@Body() payload: CreateAsaasPaymentDto) {
    return this.asaasClient.createPayment(payload);
  }

  /**
   * Gets PIX QR code for a payment.
   *
   * @param paymentId - Payment ID
   * @returns PIX QR code data
   */
  @Get('payments/:paymentId/pix')
  getPixQRCode(@Param('paymentId') paymentId: string) {
    return this.asaasClient.getPixQRCode(paymentId);
  }

  /**
   * Gets payment status.
   *
   * @param paymentId - Payment ID
   * @returns Payment status data
   */
  @Get('payments/:paymentId/status')
  getPaymentStatus(@Param('paymentId') paymentId: string) {
    return this.asaasClient.getPaymentStatus(paymentId);
  }
}
