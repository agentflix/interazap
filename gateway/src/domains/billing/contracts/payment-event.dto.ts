/**
 * Re-exporta os tipos de evento de pagamento do Asaas.
 * @deprecated Importe diretamente de `../models/payment-event.model`.
 */
export type {
  PaymentStatus,
  PaymentBillingType,
  PaymentEventPayload,
  NormalizedPaymentEvent,
  AsaasEventType,
  AsaasWebhookPayload,
} from '../models/payment-event.model';
