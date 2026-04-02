/**
 * Modelos do fluxo de cobrança via WhatsApp.
 */

/** Instância conectada de provedor de chat usada para envio de cobrança. */
export interface BillingCollectionInstance {
  id: string;
  tenant_id: string;
  provider: 'uazapi' | 'zapi';
  token: string;
}

/** Resultado da tentativa de envio da cobrança. */
export interface BillingCollectionSendResult {
  success: boolean;
  messageId: string | null;
  error: string | null;
}

/** Variáveis aceitas na renderização de templates de cobrança. */
export type BillingCollectionTemplateVariables = Record<
  string,
  string | number | boolean | null
>;
