import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import axios, { AxiosError, AxiosInstance } from 'axios';
import {
  MetaGraphTemplatesResponse,
  MetaSendTemplatePayload,
  MetaSendTemplateResponse,
  ListTemplatesFilters,
  MetaTemplateCreatePayload,
} from './meta.dto';
import {
  MetaTemplate,
  SendTemplateRequest,
  SendMessageResult,
} from '../../contracts/meta-provider.interface';

/**
 * Configuracao do provider Meta.
 */
interface MetaConfiguration {
  baseUrl: string;
  appSecret: string;
  graphApiUrl: string;
}

/**
 * Cliente HTTP para comunicacao com a Meta Graph API.
 * Gerencia tokens de acesso e aplicacao de rate limiting.
 */
@Injectable()
export class MetaClient {
  private readonly logger = new Logger(MetaClient.name);
  private readonly http: AxiosInstance;
  private readonly config: MetaConfiguration;

  constructor(private readonly configService: ConfigService) {
    this.config = this.loadConfig();

    this.http = axios.create({
      baseURL: this.config.baseUrl,
      timeout: 30000,
    });

    // Request interceptor to add headers
    this.http.interceptors.request.use((config) => {
      return config;
    });

    // Response interceptor for error handling
    this.http.interceptors.response.use(
      (response) => response,
      (error: AxiosError) => {
        this.logger.error(
          `Meta API error: ${error.message}`,
          error.response?.data
            ? JSON.stringify(error.response.data)
            : undefined,
        );
        return Promise.reject(error);
      },
    );
  }

  /**
   * Carrega configuracao do provider Meta das variaveis de ambiente.
   */
  private loadConfig(): MetaConfiguration {
    return {
      baseUrl:
        this.configService.get<string>('meta.graphApiUrl') ??
        'https://graph.facebook.com/v18.0',
      appSecret: this.configService.get<string>('meta.appSecret') ?? '',
      graphApiUrl:
        this.configService.get<string>('meta.graphApiUrl') ??
        'https://graph.facebook.com/v18.0',
    };
  }

  /**
   * Busca templates de mensagem da conta Business da Meta.
   * Filtra por status APROVED quando especificado.
   *
   * @param accessToken - Token de acesso da aplicacao Meta
   * @param filters - Filtros para a busca de templates
   * @returns Lista de templates normalizados
   */
  async getTemplates(
    accessToken: string,
    filters: ListTemplatesFilters = {},
  ): Promise<MetaTemplate[]> {
    const params: Record<string, string | number> = {
      access_token: accessToken,
    };

    if (filters.limit) {
      params.limit = filters.limit;
    }

    // Build components filter if status is specified
    const statusFilter = filters.status ?? 'APPROVED';

    try {
      const response = await this.http.get<MetaGraphTemplatesResponse>(
        '/message_templates',
        { params },
      );

      const allTemplates = response.data.data;

      // Filter by status if specified
      const filteredTemplates = statusFilter
        ? allTemplates.filter((t) => t.status.toUpperCase() === statusFilter)
        : allTemplates;

      return filteredTemplates.map((template) =>
        this.normalizeTemplate(template),
      );
    } catch (error) {
      this.logger.error(
        `Failed to fetch templates: ${error instanceof Error ? error.message : String(error)}`,
      );
      throw error;
    }
  }

  /**
   * Envia mensagem via template aprovado.
   *
   * @param phoneNumberId - ID do numero de telefone na Meta
   * @param accessToken - Token de acesso da aplicacao Meta
   * @param request - Dados do template a enviar
   * @returns Resultado do envio
   */
  async sendTemplate(
    phoneNumberId: string,
    accessToken: string,
    request: SendTemplateRequest,
  ): Promise<SendMessageResult> {
    const payload: MetaSendTemplatePayload = {
      messaging_product: 'whatsapp',
      to: request.to,
      type: 'template',
      template: {
        name: request.templateName,
        language: {
          code: request.language ?? 'pt_BR',
        },
        components: [],
      },
    };

    // Add template parameters if provided
    if (request.templateParams && request.templateParams.length > 0) {
      payload.template.components = [
        {
          type: 'body',
          parameters: request.templateParams.map((param) => ({
            type: 'text',
            text: param,
          })),
        },
      ];
    }

    try {
      const response = await this.http.post<MetaSendTemplateResponse>(
        `/${phoneNumberId}/messages`,
        payload,
        {
          params: { access_token: accessToken },
        },
      );

      const messageId = response.data.messages?.[0]?.id;

      return {
        success: true,
        messageId,
      };
    } catch (error) {
      const axiosError = error as AxiosError;
      const errorMessage =
        axiosError.response?.data &&
        typeof axiosError.response.data === 'object'
          ? JSON.stringify(axiosError.response.data)
          : axiosError.message;

      this.logger.error(`Failed to send template: ${errorMessage}`);

      return {
        success: false,
        error: errorMessage,
      };
    }
  }

  /**
   * Cria um novo template de mensagem na conta Business da Meta.
   *
   * @param wabaId - ID da conta WhatsApp Business
   * @param accessToken - Token de acesso da aplicacao Meta
   * @param payload - Dados do template a criar
   * @returns ID e status do template criado
   */
  async createTemplate(
    wabaId: string,
    accessToken: string,
    payload: MetaTemplateCreatePayload,
  ): Promise<{ id: string; status: string }> {
    try {
      const response = await this.http.post<{ id: string; status: string }>(
        `/${wabaId}/message_templates`,
        payload,
        {
          params: { access_token: accessToken },
        },
      );

      return response.data;
    } catch (error) {
      throw this.extractError(error);
    }
  }

  /**
   * Remove um template de mensagem da conta Business da Meta.
   *
   * @param wabaId - ID da conta WhatsApp Business
   * @param accessToken - Token de acesso da aplicacao Meta
   * @param name - Nome do template a remover
   */
  async deleteTemplate(
    wabaId: string,
    accessToken: string,
    name: string,
  ): Promise<{ success: boolean }> {
    try {
      await this.http.delete(`/${wabaId}/message_templates`, {
        params: { access_token: accessToken, name },
      });
      return { success: true };
    } catch (error) {
      throw this.extractError(error);
    }
  }

  /**
   * Extrai mensagem de erro da resposta da Meta API.
   */
  private extractError(error: unknown): Error {
    const axiosError = error as AxiosError;
    const metaMessage =
      axiosError.response?.data &&
      typeof axiosError.response.data === 'object' &&
      'error' in axiosError.response.data &&
      axiosError.response.data.error &&
      typeof axiosError.response.data.error === 'object' &&
      'message' in axiosError.response.data.error
        ? String(axiosError.response.data.error.message)
        : axiosError.message;

    return new Error(metaMessage);
  }

  /**
   * Normaliza template da API Graph para o formato interno.
   */
  private normalizeTemplate(
    raw: MetaGraphTemplatesResponse['data'][0],
  ): MetaTemplate {
    return {
      name: raw.name,
      status: raw.status as MetaTemplate['status'],
      category: raw.category,
      language: raw.language,
      components: raw.components.map((component) => ({
        type: component.type as MetaTemplate['components'][0]['type'],
        params: component.example?.body_text?.[0],
      })),
    };
  }
}
