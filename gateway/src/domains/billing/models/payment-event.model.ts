/**
 * Modelos dos eventos de pagamento normalizados do Asaas.
 */

/** Status aceitos para um pagamento no fluxo de billing. */
export type PaymentStatus =
  | 'PENDING'
  | 'RECEIVED'
  | 'CONFIRMED'
  | 'OVERDUE'
  | 'REFUNDED'
  | 'REFUND_REQUESTED'
  | 'CHARGEBACK_REQUESTED'
  | 'CHARGEBACK_DISPUTE'
  | 'AWAITING_CHARGEBACK_REVERSAL'
  | 'DUNNING_REQUESTED'
  | 'DUNNING_RECEIVED'
  | 'DELETED';

/** Tipos de cobrança aceitos pelo Asaas. */
export type PaymentBillingType = 'BOLETO' | 'CREDIT_CARD' | 'PIX' | 'UNDEFINED';

/** Dados do pagamento já normalizados para consumo interno. */
export interface PaymentEventPayload {
  id: string;
  customer: string;
  value: number;
  netValue?: number;
  description?: string;
  billingType: PaymentBillingType;
  status: PaymentStatus;
  dueDate: string;
  paymentDate?: string;
  invoiceUrl?: string;
  bankSlipUrl?: string;
  pixQrCode?: string;
  pixCopiaECola?: string;
}

/** Evento de pagamento enriquecido e pronto para publicação interna. */
export interface NormalizedPaymentEvent {
  tenantId: string;
  provider: 'asaas';
  eventType: AsaasEventType;
  payment: PaymentEventPayload;
  idempotencyKey: string;
  receivedAt: Date;
  rawPayload: Record<string, unknown>;
}

/** Tipos de evento de webhook reconhecidos do Asaas. */
export type AsaasEventType =
  | 'PAYMENT_CREATED'
  | 'PAYMENT_UPDATED'
  | 'PAYMENT_CONFIRMED'
  | 'PAYMENT_RECEIVED'
  | 'PAYMENT_OVERDUE'
  | 'PAYMENT_DELETED'
  | 'PAYMENT_REFUNDED'
  | 'PAYMENT_REFUND_REQUESTED'
  | 'PAYMENT_CHARGEBACK_REQUESTED'
  | 'PAYMENT_CHARGEBACK_DISPUTE'
  | 'PAYMENT_AWAITING_CHARGEBACK_REVERSAL'
  | 'PAYMENT_DUNNING_REQUESTED'
  | 'PAYMENT_DUNNING_RECEIVED'
  | 'UNKNOWN';

/** Payload bruto recebido do webhook do Asaas. */
export interface AsaasWebhookPayload {
  event: string;
  payment?: {
    id: string;
    customer: string;
    value: number;
    netValue?: number;
    description?: string;
    billingType: string;
    status: string;
    dueDate: string;
    paymentDate?: string;
    invoiceUrl?: string;
    bankSlipUrl?: string;
  };
}
