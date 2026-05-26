import { Injectable } from '@angular/core';
import { environment } from '@env/environment';

export interface AsaasCardPayload {
  holderName: string;
  number: string;
  expiryMonth: string;
  expiryYear: string;
  ccv: string;
}

export interface AsaasTokenizeResult {
  token: string;
  brand: string;
  last4: string;
}

/**
 * Service wrapper para o SDK JavaScript do Asaas.
 *
 * Responsabilidades:
 * - Injetar o script do SDK no DOM (uma única vez, via flag estática)
 * - Expor `tokenizeCard()` que envia dados ao Asaas e retorna token
 * - NUNCA enviar PAN (número do cartão) para o backend
 *
 * @remarks
 * Conformidade PCI SAQ-A: dados de cartão apenas passam pelo browser
 * para o Asaas, nunca pelo servidor InteraZap.
 *
 * @example
 * ```ts
 * const checkout = inject(AsaasCheckoutService);
 * const result = await checkout.tokenizeCard(payload);
 * // usar result.token para POST /billing/upgrade-from-trial
 * ```
 */
@Injectable({ providedIn: 'root' })
export class AsaasCheckoutService {
  private static scriptLoaded = false;
  private static scriptLoadPromise: Promise<void> | null = null;

  private readonly sdkUrl =
    environment.production
      ? 'https://checkout.asaas.com/checkout/static/widget.js'
      : 'https://sandbox.asaas.com/checkout/static/widget.js';

  /**
   * Tokeniza dados de cartão via SDK Asaas no browser.
   * Injeta o script automaticamente na primeira chamada.
   *
   * @param payload - Dados do cartão (holder, number, expiry, cvv)
   * @returns Promise com token, brand e last4
   * @throws Error se o SDK falhar ou o cartão for inválido
   */
  async tokenizeCard(payload: AsaasCardPayload): Promise<AsaasTokenizeResult> {
    await this.ensureScriptLoaded();

    return new Promise((resolve, reject) => {
      const win = window as Window & { AsaasCheckout?: { tokenize: (p: unknown, cb: (err: unknown, result: unknown) => void) => void } };

      if (!win.AsaasCheckout) {
        reject(new Error('SDK Asaas não carregado. Tente novamente.'));
        return;
      }

      win.AsaasCheckout.tokenize(
        {
          holderName: payload.holderName,
          number: payload.number.replace(/\s/g, ''),
          expiryMonth: payload.expiryMonth,
          expiryYear: payload.expiryYear,
          ccv: payload.ccv,
        },
        (err: unknown, result: unknown) => {
          if (err) {
            reject(this.mapSdkError(err));
            return;
          }

          const tokenResult = result as { creditCardToken?: string; creditCardBrand?: string; creditCardNumber?: string };
          if (!tokenResult?.creditCardToken) {
            reject(new Error('SDK Asaas não retornou token. Tente novamente.'));
            return;
          }

          resolve({
            token: tokenResult.creditCardToken,
            brand: tokenResult.creditCardBrand ?? 'UNKNOWN',
            last4: tokenResult.creditCardNumber ?? '****',
          });
        },
      );
    });
  }

  /**
   * Garante que o script do SDK está carregado no DOM.
   * Usa Promise singleton para evitar múltiplas injeções.
   */
  private ensureScriptLoaded(): Promise<void> {
    if (AsaasCheckoutService.scriptLoaded) {
      return Promise.resolve();
    }

    if (AsaasCheckoutService.scriptLoadPromise) {
      return AsaasCheckoutService.scriptLoadPromise;
    }

    AsaasCheckoutService.scriptLoadPromise = new Promise((resolve, reject) => {
      const existing = document.getElementById('asaas-checkout-sdk');
      if (existing) {
        AsaasCheckoutService.scriptLoaded = true;
        resolve();
        return;
      }

      const script = document.createElement('script');
      script.id = 'asaas-checkout-sdk';
      script.src = this.sdkUrl;
      script.defer = true;
      script.onload = () => {
        AsaasCheckoutService.scriptLoaded = true;
        resolve();
      };
      script.onerror = () => {
        AsaasCheckoutService.scriptLoadPromise = null;
        reject(new Error('Falha ao carregar SDK de pagamento. Verifique sua conexão.'));
      };

      document.head.appendChild(script);
    });

    return AsaasCheckoutService.scriptLoadPromise;
  }

  /**
   * Mapeia erros do SDK para mensagens em PT-BR.
   */
  private mapSdkError(error: unknown): Error {
    const errorMap: Record<string, string> = {
      INVALID_CARD_NUMBER: 'Número do cartão inválido.',
      INVALID_EXPIRY: 'Data de validade inválida.',
      INVALID_CVV: 'CVV inválido.',
      CARD_DECLINED: 'Cartão recusado.',
      INSUFFICIENT_FUNDS: 'Cartão sem limite suficiente.',
      DO_NOT_HONOR: 'Transação não autorizada pelo banco.',
      EXPIRED_CARD: 'Cartão expirado.',
      THREE_DS_CANCELLED: 'Verificação 3D Secure cancelada.',
    };

    if (typeof error === 'object' && error !== null && 'code' in error) {
      const code = (error as { code: string }).code;
      return new Error(errorMap[code] ?? `Erro ao processar pagamento: ${code}`);
    }

    if (typeof error === 'string') {
      return new Error(errorMap[error] ?? `Erro ao processar pagamento.`);
    }

    return new Error('Erro inesperado ao processar pagamento. Tente novamente.');
  }
}
