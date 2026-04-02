import { Injectable, Logger } from '@nestjs/common';
import { sha256 } from '../../../../shared/utils/hash.util';
import {
  AsaasEventType,
  AsaasWebhookPayload,
  NormalizedPaymentEvent,
  PaymentBillingType,
  PaymentEventPayload,
  PaymentStatus,
} from '../../contracts/payment-event.dto';

/**
 * AsaasNormalizer
 *
 * Normaliza os payloads recebidos via webhook do Asaas para o formato interno
 * NormalizedPaymentEvent, garantindo consistência de tipos e chave de idempotência.
 */
@Injectable()
export class AsaasNormalizer {
  private readonly logger = new Logger(AsaasNormalizer.name);

  /**
   * Normaliza um payload bruto do Asaas para o formato interno NormalizedPaymentEvent.
   *
   * @param tenantId - Identificador do tenant dono do evento
   * @param rawPayload - Payload bruto recebido via webhook
   * @returns Evento de pagamento normalizado pronto para publicação
   */
  normalize(
    tenantId: string,
    rawPayload: Record<string, unknown>,
  ): NormalizedPaymentEvent {
    const payload = rawPayload as unknown as AsaasWebhookPayload;
    const eventType = this.normalizeEventType(payload.event);
    const payment = this.normalizePayment(payload.payment);

    const idempotencyKey = this.computeIdempotencyKey(eventType, payment.id);

    return {
      tenantId,
      provider: 'asaas',
      eventType,
      payment,
      idempotencyKey,
      receivedAt: new Date(),
      rawPayload,
    };
  }

  /**
   * Converte a string de evento do Asaas para o tipo interno AsaasEventType.
   *
   * @param event - String do tipo de evento recebida do Asaas
   * @returns Tipo de evento normalizado ou 'UNKNOWN' se não reconhecido
   */
  private normalizeEventType(event?: string): AsaasEventType {
    if (!event) return 'UNKNOWN';

    const eventMap: Record<string, AsaasEventType> = {
      PAYMENT_CREATED: 'PAYMENT_CREATED',
      PAYMENT_UPDATED: 'PAYMENT_UPDATED',
      PAYMENT_CONFIRMED: 'PAYMENT_CONFIRMED',
      PAYMENT_RECEIVED: 'PAYMENT_RECEIVED',
      PAYMENT_OVERDUE: 'PAYMENT_OVERDUE',
      PAYMENT_DELETED: 'PAYMENT_DELETED',
      PAYMENT_REFUNDED: 'PAYMENT_REFUNDED',
      PAYMENT_REFUND_REQUESTED: 'PAYMENT_REFUND_REQUESTED',
      PAYMENT_CHARGEBACK_REQUESTED: 'PAYMENT_CHARGEBACK_REQUESTED',
      PAYMENT_CHARGEBACK_DISPUTE: 'PAYMENT_CHARGEBACK_DISPUTE',
      PAYMENT_AWAITING_CHARGEBACK_REVERSAL:
        'PAYMENT_AWAITING_CHARGEBACK_REVERSAL',
      PAYMENT_DUNNING_REQUESTED: 'PAYMENT_DUNNING_REQUESTED',
      PAYMENT_DUNNING_RECEIVED: 'PAYMENT_DUNNING_RECEIVED',
    };

    return eventMap[event.toUpperCase()] ?? 'UNKNOWN';
  }

  /**
   * Converte os dados brutos do pagamento para o formato PaymentEventPayload.
   * Retorna um payload com valores padrão caso os dados estejam ausentes.
   *
   * @param payment - Dados brutos do pagamento, pode ser undefined
   * @returns Payload de pagamento normalizado
   */
  private normalizePayment(
    payment?: AsaasWebhookPayload['payment'],
  ): PaymentEventPayload {
    if (!payment) {
      return {
        id: 'unknown',
        customer: 'unknown',
        value: 0,
        billingType: 'UNDEFINED',
        status: 'PENDING',
        dueDate: new Date().toISOString().split('T')[0],
      };
    }

    return {
      id: payment.id,
      customer: payment.customer,
      value: payment.value,
      netValue: payment.netValue,
      description: payment.description,
      billingType: this.normalizeBillingType(payment.billingType),
      status: this.normalizeStatus(payment.status),
      dueDate: payment.dueDate,
      paymentDate: payment.paymentDate,
      invoiceUrl: payment.invoiceUrl,
      bankSlipUrl: payment.bankSlipUrl,
    };
  }

  /**
   * Converte a string de tipo de cobrança do Asaas para o tipo interno PaymentBillingType.
   *
   * @param billingType - String do tipo de cobrança (ex.: 'BOLETO', 'PIX')
   * @returns Tipo de cobrança normalizado ou 'UNDEFINED' se não reconhecido
   */
  private normalizeBillingType(billingType?: string): PaymentBillingType {
    const map: Record<string, PaymentBillingType> = {
      BOLETO: 'BOLETO',
      CREDIT_CARD: 'CREDIT_CARD',
      PIX: 'PIX',
    };

    return map[billingType?.toUpperCase() ?? ''] ?? 'UNDEFINED';
  }

  /**
   * Converte a string de status do Asaas para o tipo interno PaymentStatus.
   *
   * @param status - String do status do pagamento (ex.: 'PENDING', 'RECEIVED')
   * @returns Status do pagamento normalizado ou 'PENDING' como fallback
   */
  private normalizeStatus(status?: string): PaymentStatus {
    const map: Record<string, PaymentStatus> = {
      PENDING: 'PENDING',
      RECEIVED: 'RECEIVED',
      CONFIRMED: 'CONFIRMED',
      OVERDUE: 'OVERDUE',
      REFUNDED: 'REFUNDED',
      REFUND_REQUESTED: 'REFUND_REQUESTED',
      CHARGEBACK_REQUESTED: 'CHARGEBACK_REQUESTED',
      CHARGEBACK_DISPUTE: 'CHARGEBACK_DISPUTE',
      AWAITING_CHARGEBACK_REVERSAL: 'AWAITING_CHARGEBACK_REVERSAL',
      DUNNING_REQUESTED: 'DUNNING_REQUESTED',
      DUNNING_RECEIVED: 'DUNNING_RECEIVED',
      DELETED: 'DELETED',
    };

    return map[status?.toUpperCase() ?? ''] ?? 'PENDING';
  }

  /**
   * Calcula a chave de idempotência para o evento a partir do tipo e ID do pagamento.
   *
   * @param eventType - Tipo do evento normalizado
   * @param paymentId - Identificador único do pagamento no Asaas
   * @returns Hash SHA-256 da chave composta
   */
  private computeIdempotencyKey(
    eventType: AsaasEventType,
    paymentId: string,
  ): string {
    const base = `asaas|${eventType}|${paymentId}`;
    return sha256(base);
  }
}
