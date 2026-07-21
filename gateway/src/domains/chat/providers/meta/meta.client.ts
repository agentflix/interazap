import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import axios, { AxiosError, AxiosInstance } from 'axios';
import {
  MetaApiError,
  MetaGraphTemplatesResponse,
  MetaSendTemplatePayload,
  MetaSendTemplateResponse,
  MetaSendTextPayload,
  ListTemplatesFilters,
  MetaTemplateCreatePayload,
} from './meta.dto';
import {
  MetaTemplate,
  SendTemplateRequest,
  SendMessageResult,
} from '../../contracts/meta-provider.interface';

/** Código de erro da Meta para reengajamento fora da janela de atendimento de 24h. */
const RE_ENGAGEMENT_ERROR_CODE = 131047;

/**
 * Configuracao do provider Meta carregada das variaveis de ambiente.
 */
interface MetaConfiguration {
  /** URL base da Meta Graph API. */
  baseUrl: string;
  /** Segredo da aplicacao Meta para validacao HMAC. */
  appSecret: string;
  /** URL da Meta Graph API para chamadas diretas. */
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

    // Interceptor de requisicao para adicionar cabecalhos
    this.http.interceptors.request.use((config) => {
      return config;
    });

    // Interceptor de resposta para tratamento de erros
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
        'https://graph.facebook.com/v25.0',
      appSecret: this.configService.get<string>('meta.appSecret') ?? '',
      graphApiUrl:
        this.configService.get<string>('meta.graphApiUrl') ??
        'https://graph.facebook.com/v25.0',
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

    // Constroi filtro de componentes quando status estiver especificado
    const statusFilter = filters.status ?? 'APPROVED';

    try {
      const response = await this.http.get<MetaGraphTemplatesResponse>(
        '/message_templates',
        { params },
      );

      const allTemplates = response.data.data;

      // Filtra por status quando especificado
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

    // Adiciona parametros do template quando fornecidos
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

      // A Meta pode retornar HTTP 200 com `errors[]` preenchido no corpo.
      if (response.data.errors && response.data.errors.length > 0) {
        return this.buildErrorResult(response.data.errors, 'template');
      }

      const messageId = response.data.messages?.[0]?.id;

      return {
        success: true,
        messageId,
      };
    } catch (error) {
      return this.handleSendError(error, 'template');
    }
  }

  /**
   * Envia mensagem de texto livre via Meta Cloud API.
   * Só é aceita pela Meta enquanto a janela de atendimento (24h/72h) estiver
   * aberta — fora dela, a Meta retorna o código 131047 (re-engagement),
   * mapeado para uma mensagem clara indicando o uso de template.
   *
   * @param phoneNumberId - ID do numero de telefone na Meta
   * @param accessToken - Token de acesso da aplicacao Meta
   * @param request - Destinatario e corpo da mensagem de texto
   * @returns Resultado do envio
   */
  async sendText(
    phoneNumberId: string,
    accessToken: string,
    request: { to: string; body: string },
  ): Promise<SendMessageResult> {
    const payload: MetaSendTextPayload = {
      messaging_product: 'whatsapp',
      to: request.to,
      type: 'text',
      text: { body: request.body },
    };

    try {
      const response = await this.http.post<MetaSendTemplateResponse>(
        `/${phoneNumberId}/messages`,
        payload,
        {
          params: { access_token: accessToken },
        },
      );

      // A Meta pode retornar HTTP 200 com `errors[]` preenchido no corpo
      // (ex.: 131047 quando o texto livre é enviado fora da janela de 24h).
      if (response.data.errors && response.data.errors.length > 0) {
        return this.buildErrorResult(response.data.errors, 'text');
      }

      const messageId = response.data.messages?.[0]?.id;

      return {
        success: true,
        messageId,
      };
    } catch (error) {
      return this.handleSendError(error, 'text');
    }
  }

  /**
   * Trata erro de requisicao HTTP (nao-2xx) no envio de mensagem, extraindo
   * `errors[]` do corpo da resposta da Meta quando disponivel.
   */
  private handleSendError(
    error: unknown,
    context: 'template' | 'text',
  ): SendMessageResult {
    const axiosError = error as AxiosError<{ error?: MetaApiError }>;
    const metaError = axiosError.response?.data?.error;

    if (metaError) {
      return this.buildErrorResult([metaError], context);
    }

    const errorMessage =
      axiosError.response?.data && typeof axiosError.response.data === 'object'
        ? JSON.stringify(axiosError.response.data)
        : axiosError.message;

    this.logger.error(`Failed to send ${context} message: ${errorMessage}`);

    return {
      success: false,
      error: errorMessage,
    };
  }

  /**
   * Constroi o resultado de erro a partir de `errors[]` retornado pela Meta.
   * Mapeia o código 131047 (re-engagement / fora da janela de 24h) para uma
   * mensagem clara indicando o uso de template — nunca mascara a falha.
   */
  private buildErrorResult(
    errors: MetaApiError[],
    context: 'template' | 'text',
  ): SendMessageResult {
    const primary = errors[0];
    this.logger.error(
      `Failed to send ${context} message: code=${primary.code} title=${primary.title}`,
      JSON.stringify(errors),
    );

    if (primary.code === RE_ENGAGEMENT_ERROR_CODE) {
      return {
        success: false,
        error: `Fora da janela de atendimento de 24h (code ${primary.code}): envie um template aprovado para reengajar o contato.`,
      };
    }

    const details = primary.error_data?.details;
    const messagePart = primary.message ? `: ${primary.message}` : '';
    const detailsPart = details ? ` (${details})` : '';

    return {
      success: false,
      error: `${primary.title}${messagePart}${detailsPart} (code ${primary.code})`,
    };
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
