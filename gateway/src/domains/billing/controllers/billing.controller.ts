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
   * Cria um novo cliente no Asaas.
   *
   * @param payload - Dados do cliente (nome, CPF/CNPJ, e-mail, referência externa)
   * @returns Dados do cliente criado com ID gerado pelo Asaas
   */
  @Post('customers')
  createCustomer(@Body() payload: CreateAsaasCustomerDto) {
    return this.asaasClient.createCustomer(payload);
  }

  /**
   * Cria uma nova cobrança no Asaas.
   *
   * @param payload - Dados da cobrança (cliente, tipo, valor, vencimento, descrição)
   * @returns Dados da cobrança criada com ID, link da fatura e status
   */
  @Post('payments')
  createPayment(@Body() payload: CreateAsaasPaymentDto) {
    return this.asaasClient.createPayment(payload);
  }

  /**
   * Obtém o QR Code PIX de uma cobrança existente.
   *
   * @param paymentId - Identificador da cobrança no Asaas
   * @returns Dados do QR Code PIX (payload, imagem codificada e data de expiração)
   */
  @Get('payments/:paymentId/pix')
  getPixQRCode(@Param('paymentId') paymentId: string) {
    return this.asaasClient.getPixQRCode(paymentId);
  }

  /**
   * Consulta o status atual de uma cobrança.
   *
   * @param paymentId - Identificador da cobrança no Asaas
   * @returns Status, valor e data de confirmação do pagamento
   */
  @Get('payments/:paymentId/status')
  getPaymentStatus(@Param('paymentId') paymentId: string) {
    return this.asaasClient.getPaymentStatus(paymentId);
  }
}
