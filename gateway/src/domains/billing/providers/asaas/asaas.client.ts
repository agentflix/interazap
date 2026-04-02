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

    this.axiosInstance = this.createAxiosInstance({
      baseURL: this.config.baseUrl,
      timeout: 30000,
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${this.config.apiKey}`,
      },
    });
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
   * Cria um novo produto no Asaas.
   *
   * @param payload - Dados do produto a ser criado
   * @returns Resposta com o ID do produto criado
   */
  async createProduct(
    payload: AsaasProductPayload,
  ): Promise<AsaasProductResponse> {
    try {
      const response = await this.axiosInstance.post<AsaasProductResponse>(
        '/products',
        payload,
      );
      return response.data;
    } catch (error) {
      this.handleError('createProduct', error);
      throw error;
    }
  }

  /**
   * Atualiza um produto existente no Asaas.
   *
   * @param productId - Identificador do produto no Asaas
   * @param payload - Dados atualizados do produto
   */
  async updateProduct(
    productId: string,
    payload: AsaasProductPayload,
  ): Promise<void> {
    try {
      await this.axiosInstance.post(`/products/${productId}`, payload);
    } catch (error) {
      this.handleError('updateProduct', error);
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
}
