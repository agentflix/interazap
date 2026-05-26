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
  AsaasPaymentMethodPayload,
  AsaasPaymentMethodResponse,
  AsaasChargeWithTokenPayload,
  AsaasChargeWithTokenResponse,
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
   * NO-OP: a API Asaas v3 não expõe o endpoint `/products`.
   * A implementação anterior acessava uma rota inexistente e retornava 404 em produção
   * (degradado silenciosamente para `null` no chamador Laravel).
   *
   * A sincronização Plano ↔ Asaas, quando necessária, deverá ser modelada sobre
   * `/subscriptions` ou `/checkouts`. Até isso ser implementado, retorna um stub
   * para não lançar exceção — `asaas_product_id` permanecerá `null` para o plano,
   * o que todos os call-sites atuais já toleram.
   *
   * @param payload - Payload do produto (mantido para estabilidade de API)
   * @returns Resposta stub com `id: null`
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
   * NO-OP: consulte `createProduct` para contexto.
   * A API Asaas v3 não expõe o endpoint `/products`.
   *
   * @param _productId - ID do produto (mantido para estabilidade de API)
   * @param _payload - Payload do produto (mantido para estabilidade de API)
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
   * Consulta metadados de um cartão tokenizado no Asaas sem cobrar.
   *
   * @param token - Token do cartão emitido pelo Asaas.js SDK
   * @param customer - ID do cliente no Asaas
   * @returns Metadados do cartão (brand, last4, expiry)
   */
  async getPaymentMethod(
    token: string,
    customer: string,
  ): Promise<AsaasPaymentMethodResponse> {
    this.assertApiKeyConfigured();

    try {
      // Asaas tokenization: fetch card metadata via credit card tokenization endpoint
      const payload: AsaasPaymentMethodPayload = { token, customer };
      const response = await this.axiosInstance.post<AsaasPaymentMethodResponse>(
        '/creditCards/tokenize',
        payload,
      );
      return response.data;
    } catch (error) {
      this.handleError('getPaymentMethod', error);
      throw error;
    }
  }

  /**
   * Cria cobrança usando token de cartão salvo, sem PAN trafegando.
   *
   * @param customerId - ID do cliente no Asaas
   * @param token - Token do cartão previamente tokenizado
   * @param amount - Valor da cobrança em reais
   * @param metadata - Metadados adicionais (description, externalReference)
   * @returns Resultado da cobrança com paymentId, status e dados do cartão
   */
  async chargeWithToken(
    customerId: string,
    token: string,
    amount: number,
    metadata: { description?: string; externalReference?: string } = {},
  ): Promise<AsaasChargeWithTokenResponse> {
    this.assertApiKeyConfigured();

    try {
      const payload: AsaasChargeWithTokenPayload = {
        customer: customerId,
        billingType: 'CREDIT_CARD',
        value: amount,
        dueDate: new Date().toISOString().split('T')[0],
        description: metadata.description ?? 'Assinatura InteraZap',
        externalReference: metadata.externalReference ?? '',
        creditCardToken: token,
      };

      const response = await this.axiosInstance.post<AsaasChargeWithTokenResponse>(
        '/payments',
        payload,
      );
      return response.data;
    } catch (error) {
      this.handleError('chargeWithToken', error);
      throw error;
    }
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

  /**
   * Garante que a API key do Asaas está configurada antes de realizar chamadas.
   *
   * @throws Error se `ASAAS_API_KEY` não estiver configurada
   */
  private assertApiKeyConfigured(): void {
    if (!this.config.apiKey || this.config.apiKey.trim() === '') {
      throw new Error('ASAAS_API_KEY is not configured');
    }
  }
}
