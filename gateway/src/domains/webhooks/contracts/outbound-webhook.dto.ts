/**
 * Contratos de webhook de saída — barrel que re-exporta os tipos do model canônico.
 * Todos os tipos são definidos em `../models/outbound-webhook.model.ts`.
 */

export type {
  HttpMethod,
  OutboundWebhookRequest,
  OutboundWebhookResult,
  WebhookDeliveryAttempt,
  WebhookConfig,
} from '../models/outbound-webhook.model';
