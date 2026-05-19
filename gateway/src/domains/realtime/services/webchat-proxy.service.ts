import { Injectable, Logger, HttpException } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import axios, { AxiosError } from 'axios';

/**
 * WebChatProxyService
 *
 * Faz proxy das requisições HTTP do webchat para a API Laravel.
 * Os endpoints públicos do webchat são implementados na API Laravel e
 * este serviço os expõe via gateway sem necessidade de autenticação Sanctum.
 */
@Injectable()
export class WebChatProxyService {
  private readonly logger = new Logger(WebChatProxyService.name);
  private readonly apiUrl: string;
  private readonly timeoutMs: number;

  constructor(private readonly configService: ConfigService) {
    this.apiUrl =
      this.configService.get<string>('api.url') ?? 'http://localhost:8000';
    this.timeoutMs =
      this.configService.get<number>('api.authTimeoutMs') ?? 10000;
  }

  /**
   * Cria uma nova sessão de webchat.
   * POST /api/webchat/sessions
   */
  async createSession(body: Record<string, unknown>): Promise<unknown> {
    this.logger.log('Proxying POST /api/webchat/sessions');
    return this.post('/api/webchat/sessions', body);
  }

  /**
   * Envia uma mensagem de texto no webchat.
   * POST /api/webchat/messages
   */
  async sendMessage(body: Record<string, unknown>): Promise<unknown> {
    this.logger.log('Proxying POST /api/webchat/messages');
    return this.post('/api/webchat/messages', body);
  }

  /**
   * Faz upload de mídia para o webchat.
   * POST /api/webchat/media  (multipart/form-data)
   */
  async uploadMedia(
    token: string,
    file: Express.Multer.File,
  ): Promise<unknown> {
    this.logger.log('Proxying POST /api/webchat/media');

    const formData = new FormData();
    formData.append('token', token);

    const blob = new Blob([new Uint8Array(file.buffer)], {
      type: file.mimetype,
    });
    formData.append('file', blob, file.originalname);

    try {
      const response = await axios.post(
        `${this.apiUrl}/api/webchat/media`,
        formData,
        {
          timeout: this.timeoutMs,
        },
      );
      return response.data;
    } catch (error: unknown) {
      this.handleProxyError('POST /api/webchat/media', error);
    }
  }

  /**
   * Encerra o ticket do webchat.
   * POST /api/webchat/close
   */
  async closeTicket(body: Record<string, unknown>): Promise<unknown> {
    this.logger.log('Proxying POST /api/webchat/close');
    return this.post('/api/webchat/close', body);
  }

  /**
   * Busca o histórico de mensagens de uma sessão.
   * GET /api/webchat/sessions/:id/messages
   */
  async getSessionMessages(sessionId: string, token: string): Promise<unknown> {
    this.logger.log(`Proxying GET /api/webchat/sessions/${sessionId}/messages`);
    try {
      const response = await axios.get(
        `${this.apiUrl}/api/webchat/sessions/${sessionId}/messages`,
        {
          params: { token },
          timeout: this.timeoutMs,
        },
      );
      return response.data;
    } catch (error: unknown) {
      this.handleProxyError(
        `GET /api/webchat/sessions/${sessionId}/messages`,
        error,
      );
    }
  }

  /**
   * Busca informações do tenant para o webchat.
   * GET /api/webchat/tenant/:tenantId
   */
  async getTenantInfo(tenantId: string): Promise<unknown> {
    this.logger.log(`Proxying GET /api/webchat/tenant/${tenantId}`);
    try {
      const response = await axios.get(
        `${this.apiUrl}/api/webchat/tenant/${tenantId}`,
        { timeout: this.timeoutMs },
      );
      return response.data;
    } catch (error: unknown) {
      this.handleProxyError(`GET /api/webchat/tenant/${tenantId}`, error);
    }
  }

  /**
   * Busca uma sessão de webchat por ID.
   * GET /api/webchat/sessions/:id?tenant_id=:tenantId
   */
  async getSession(sessionId: string, tenantId: string): Promise<unknown> {
    this.logger.log(`Proxying GET /api/webchat/sessions/${sessionId}`);
    try {
      const response = await axios.get(
        `${this.apiUrl}/api/webchat/sessions/${sessionId}`,
        { params: { tenant_id: tenantId }, timeout: this.timeoutMs },
      );
      return response.data;
    } catch (error: unknown) {
      this.handleProxyError(`GET /api/webchat/sessions/${sessionId}`, error);
    }
  }

  // ─── Private helpers ────────────────────────────────────────────────────────

  private async post(
    path: string,
    body: Record<string, unknown>,
  ): Promise<unknown> {
    try {
      const response = await axios.post(`${this.apiUrl}${path}`, body, {
        headers: { 'Content-Type': 'application/json' },
        timeout: this.timeoutMs,
      });
      return response.data;
    } catch (error: unknown) {
      this.handleProxyError(`POST ${path}`, error);
    }
  }

  private handleProxyError(context: string, error: unknown): never {
    if (axios.isAxiosError(error)) {
      const axiosError = error as AxiosError<{ message?: string }>;
      const status = axiosError.response?.status ?? 502;
      const message =
        axiosError.response?.data?.message ??
        axiosError.message ??
        'Erro ao comunicar com a API';

      this.logger.error(
        `Proxy error on ${context}: HTTP ${status} - ${message}`,
      );
      throw new HttpException(message, status);
    }

    const msg = error instanceof Error ? error.message : String(error);
    this.logger.error(`Unexpected proxy error on ${context}: ${msg}`);
    throw new HttpException('Erro interno ao processar requisição', 500);
  }
}
