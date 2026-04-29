import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { AxiosError, AxiosInstance } from 'axios';
import { AsaasConfiguration } from '../../../../core/config/configuration';
import { AbstractHttpClient } from '../../../../shared/http/abstract-http-client';
import {
  AsaasCustomerPayload,
  AsaasPaymentPayload,
  AsaasProductPayload,
  AsaasCustomerResponse,
  AsaasPaymentResponse,
  AsaasPixResponse,
  AsaasPaymentStatusResponse,
  AsaasProductResponse,
} from '../../models/asaas-client.model';

/**
 * AsaasClient
 *
 * Cliente HTTP para comunicação com a API REST do Asaas.
 * Gerencia criação de clientes, pagamentos e produtos,
 * além de consulta de status e QR Code PIX.
 */
@Injectable()
export class AsaasClient extends AbstractHttpClient {
  protected readonly logger = new Logger(AsaasClient.name);
  private readonly axiosInstance: AxiosInstance;
  private readonly config: AsaasConfiguration;

  constructor(private readonly configService: ConfigService) {
    super();

    this.config = this.configService.get<AsaasConfiguration>('asaas') ?? {
      baseUrl: 'https://sandbox.asaas.com/api/v3',
      apiKey: '',
      webhookSecret: '',
    };

    // Asaas v3 expects the API key in the `access_token` header — NOT a
    // Bearer Authorization header. Sending Bearer makes every request return
    // 401 Unauthorized (silently logged here and surfaced as
    // `DomainException` upstream). Reference: https://docs.asaas.com/
    this.axiosInstance = this.createAxiosInstance({
      baseURL: this.config.baseUrl,
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json',
        access_token: this.config.apiKey,
      },
    });

    if (!this.config.apiKey || this.config.apiKey.trim() === '') {
      this.logger.warn(
        '[Asaas] ASAAS_API_KEY is not configured; billing calls will fail fast',
      );
    }
  }

  /**
   * Cria um novo cliente no Asaas.
   *
   * @param payload - Dados do cliente a ser criado
   * @returns Resposta com o ID do cliente criado
   */
  async createCustomer(
    payload: AsaasCustomerPayload,
  ): Promise<AsaasCustomerResponse> {
    this.assertApiKeyConfigured();

    try {
      const response = await this.axiosInstance.post<AsaasCustomerResponse>(
        '/customers',
        payload,
      );
      return response.data;
    } catch (error) {
      this.handleError('createCustomer', error);
      throw error;
    }
  }

  /**
   * Cria uma nova cobrança no Asaas.
   *
   * @param payload - Dados da cobrança a ser criada
   * @returns Resposta com ID, link da fatura e status
   */
  async createPayment(
    payload: AsaasPaymentPayload,
  ): Promise<AsaasPaymentResponse> {
    this.assertApiKeyConfigured();

    try {
      const response = await this.axiosInstance.post<AsaasPaymentResponse>(
        '/payments',
        payload,
      );
      return response.data;
    } catch (error) {
      this.handleError('createPayment', error);
      throw error;
    }
  }

  /**
   * Obtém os dados do QR Code PIX de uma cobrança.
   *
   * @param paymentId - Identificador da cobrança no Asaas
   * @returns Dados do QR Code PIX (payload, imagem e data de expiração)
   */
  async getPixQRCode(paymentId: string): Promise<AsaasPixResponse> {
    this.assertApiKeyConfigured();

    try {
      const response = await this.axiosInstance.get<AsaasPixResponse>(
        `/payments/${paymentId}/pixQrCode`,
      );
      return response.data;
    } catch (error) {
      this.handleError('getPixQRCode', error);
      throw error;
    }
  }

  /**
   * Consulta o status atual de uma cobrança no Asaas.
   *
   * @param paymentId - Identificador da cobrança no Asaas
   * @returns Status, valor e data de confirmação do pagamento
   */
  async getPaymentStatus(
    paymentId: string,
  ): Promise<AsaasPaymentStatusResponse> {
    this.assertApiKeyConfigured();

    try {
      const response = await this.axiosInstance.get<AsaasPaymentStatusResponse>(
        `/payments/${paymentId}`,
      );
      return response.data;
    } catch (error) {
      this.handleError('getPaymentStatus', error);
      throw error;
    }
  }

  /**
   * NO-OP: Asaas v3 does not expose a `/products` endpoint. The previous
   * implementation hit a non-existent route and returned 404 in production
   * (silently degraded to `null` in the Laravel caller).
   *
   * Plan ↔ Asaas synchronization, when needed, must be modelled on top of
   * `/subscriptions` or `/checkouts`. Until that work lands, we resolve with
   * a stub so callers do not raise — `asaas_product_id` will simply remain
   * `null` for the plan, which all current call-sites already tolerate.
   *
   * @param payload - Product payload (kept for API stability)
   * @returns Stub response with `id: null`
   */

  async createProduct(
    _payload: AsaasProductPayload,
  ): Promise<AsaasProductResponse> {
    this.logger.warn(
      '[Asaas] createProduct is a no-op: Asaas v3 has no /products endpoint',
    );
    return Promise.resolve({ id: null } as unknown as AsaasProductResponse);
  }

  /**
   * NO-OP: see `createProduct` for context.
   *
   * @param productId - Product ID (kept for API stability)
   * @param payload - Product payload (kept for API stability)
   */

  async updateProduct(
    _productId: string,
    _payload: AsaasProductPayload,
  ): Promise<void> {
    this.logger.warn(
      '[Asaas] updateProduct is a no-op: Asaas v3 has no /products endpoint',
    );
    return Promise.resolve();
  }

  /**
   * Registra erros de chamadas HTTP no logger do cliente.
   *
   * @param method - Nome do método onde o erro ocorreu
   * @param error - Erro capturado (pode ser AxiosError ou desconhecido)
   */
  private handleError(method: string, error: unknown): void {
    if (error instanceof AxiosError) {
      this.logger.error(
        `[Asaas] ${method} failed: ${error.message}`,
        error.response?.data,
      );
      return;
    }

    this.logger.error(`[Asaas] ${method} failed with unknown error`, error);
  }

  private assertApiKeyConfigured(): void {
    if (!this.config.apiKey || this.config.apiKey.trim() === '') {
      throw new Error('ASAAS_API_KEY is not configured');
    }
  }
}
