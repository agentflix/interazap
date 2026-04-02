/**
 * Modelos de payload e resposta do cliente HTTP do Asaas.
 */

/** Payload de criação de cliente no Asaas. */
export interface AsaasCustomerPayload {
  name: string;
  cpfCnpj: string;
  email: string;
  externalReference: string;
}

/** Payload de criação de pagamento no Asaas. */
export interface AsaasPaymentPayload {
  customer: string;
  billingType: string;
  value: number;
  dueDate: string;
  description: string;
  externalReference: string;
  creditCard?: {
    holderName: string;
    number: string;
    expiryMonth: string;
    expiryYear: string;
    ccv: string;
  };
  creditCardHolderInfo?: {
    name: string;
    email: string;
    cpfCnpj: string;
    postalCode: string;
    addressNumber: string;
    phone: string;
  };
}

/** Payload de criação/atualização de produto no Asaas. */
export interface AsaasProductPayload {
  name: string;
  description: string;
  value: number;
  externalReference?: string;
}

/** Resposta de criação de cliente no Asaas. */
export interface AsaasCustomerResponse {
  id?: string;
}

/** Resposta de criação de pagamento no Asaas. */
export interface AsaasPaymentResponse {
  id?: string;
  invoiceUrl?: string;
  status?: string;
}

/** Resposta com dados de QR Code PIX. */
export interface AsaasPixResponse {
  payload?: string;
  encodedImage?: string;
  expirationDate?: string;
}

/** Resposta com status atual do pagamento. */
export interface AsaasPaymentStatusResponse {
  status?: string;
  value?: number;
  confirmedDate?: string;
}

/** Resposta de criação de produto no Asaas. */
export interface AsaasProductResponse {
  id?: string;
}
