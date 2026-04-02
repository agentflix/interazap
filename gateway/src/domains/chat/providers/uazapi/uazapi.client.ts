import axios, { AxiosError, AxiosInstance, AxiosRequestConfig } from 'axios';
import { HttpException, Injectable, Logger, Optional } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import {
  CircuitBreakerService,
  CircuitOpenException,
} from '../../../../shared/services/circuit-breaker';
import { getCircuitBreakerOptions } from '../../../../core/config/circuit-breaker.config';
import {
  JsonRecord,
  asRecord,
  getRecord,
  getString,
  isRecord,
} from '../../../../shared/utils/type-guards';
import { maskSecrets } from '../../../../shared/utils/secret-masker';
import { Headers, WebhookMetadata } from '../../models/uazapi.model';
/**
 * Cliente HTTP para operacoes de instancia e envio na integracao Uazapi.
 */
@Injectable()
export class UazapiClient {
  private readonly http: AxiosInstance;
  private readonly logger = new Logger(UazapiClient.name);
  private readonly adminToken?: string;
  private readonly debugHttp: boolean;
  private readonly webhookUrl?: string;
  private readonly webhookEvents: string[];
  private readonly webhookExcludeMessages: string[];
  private readonly webhookRetries: number;
  private readonly webhookProvider = 'uazapi';
  private readonly circuitBreaker: CircuitBreakerService;
  private readonly circuitName = 'whatsapp:uazapi';

  constructor(
    private readonly configService: ConfigService,
    @Optional() circuitBreaker?: CircuitBreakerService,
  ) {
    const baseURL =
      this.configService.get<string>('UAZAPI_BASE_URL') ??
      'https://free.uazapi.com';
    this.adminToken = this.configService.get<string>('UAZAPI_ADMIN_TOKEN');
    this.debugHttp =
      this.configService.get<boolean>('GATEWAY_DEBUG_HTTP') === true ||
      String(
        this.configService.get<string>('GATEWAY_DEBUG_HTTP'),
      ).toLowerCase() === 'true';
    this.webhookUrl = this.configService.get<string>('UAZAPI_WEBHOOK_URL');
    this.webhookEvents = this.parseList('UAZAPI_WEBHOOK_EVENTS', [
      'connection',
      'messages',
      'messages_update',
    ]);
    this.webhookExcludeMessages = this.parseList(
      'UAZAPI_WEBHOOK_EXCLUDE_MESSAGES',
      ['wasSentByApi'],
    );
    const retries = Number(
      this.configService.get<string>('UAZAPI_WEBHOOK_RETRIES') ?? 3,
    );
    this.webhookRetries =
      Number.isFinite(retries) && retries > 0 ? Math.trunc(retries) : 3;
    this.http = axios.create({ baseURL });
    this.circuitBreaker = circuitBreaker ?? new CircuitBreakerService();

    if (this.debugHttp) {
      this.http.interceptors.request.use((config) => {
        this.logger.log(
          `[Uazapi][HTTP OUT] ${config.method?.toUpperCase()} ${config.url} ` +
            `${config.headers?.token ? '(token set)' : ''}`,
          {
            headers: maskSecrets(config.headers ?? {}),
            data: maskSecrets(config.data ?? {}),
          },
        );
        return config;
      });

      this.http.interceptors.response.use(
        (response) => {
          this.logger.log(
            `[Uazapi][HTTP IN] ${response.status} ${response.config.url}`,
            { data: maskSecrets(response.data) },
          );
          return response;
        },
        (error: unknown) => {
          if (axios.isAxiosError(error)) {
            const { response, config, message } = error;
            if (response) {
              this.logger.error(
                `[Uazapi][HTTP ERR] ${response.status} ${config?.url} -> ${message}`,
                maskSecrets(response.data),
              );
            } else {
              this.logger.error(`[Uazapi][HTTP ERR] ${message}`);
            }
            return Promise.reject(error);
          }

          if (error instanceof Error) {
            this.logger.error(`[Uazapi][HTTP ERR] ${error.message}`);
            return Promise.reject(error);
          }

          this.logger.error('[Uazapi][HTTP ERR] unknown error');
          return Promise.reject(new Error('Uazapi request failed'));
        },
      );
    }
  }

  /**
   * Monta objeto de cabecalhos com token e campos extras opcionais.
   *
   * @param token - Token de autenticacao da instancia
   * @param extra - Cabecalhos adicionais a mesclar
   * @returns Objeto de cabecalhos HTTP
   */
  private headers(token?: string, extra: Headers = {}): Headers {
    const headers: Headers = { ...extra };
    if (token) headers['token'] = token;
    return headers;
  }

  /**
   * Monta cabecalhos de autenticacao administrativa.
   *
   * @returns Cabecalhos com admintoken configurado
   * @throws Error quando UAZAPI_ADMIN_TOKEN nao esta configurado
   */
  private adminHeaders(): Headers {
    if (!this.adminToken) {
      throw new Error('UAZAPI_ADMIN_TOKEN is not configured');
    }
    return { admintoken: this.adminToken };
  }

  /**
   * Inicializa uma nova instancia na Uazapi e tenta configurar webhook.
   */
  async initInstance(body: JsonRecord): Promise<unknown> {
    const instance = await this.post<unknown>('/instance/init', body, {
      headers: this.adminHeaders(),
    });

    if (!isRecord(instance)) {
      return instance;
    }

    const token = this.extractInstanceToken(instance);
    if (!token) {
      this.logger.warn(
        '[Uazapi] initInstance returned without token; skipping webhook configuration',
      );
      return instance;
    }

    const webhook = await this.ensureWebhookConfigured(token);
    if (webhook) {
      instance.webhook = webhook;
    }

    return instance;
  }

  /**
   * Lista instancias disponiveis na conta administrativa da Uazapi.
   */
  async listInstances(): Promise<unknown> {
    return this.get<unknown>('/instance/all', { headers: this.adminHeaders() });
  }

  /**
   * Solicita conexao de uma instancia na Uazapi.
   */
  async connectInstance(
    token: string,
    body?: Record<string, unknown>,
  ): Promise<unknown> {
    return this.post<unknown>('/instance/connect', body ?? {}, {
      headers: this.headers(token),
    });
  }

  /**
   * Solicita desconexao de uma instancia na Uazapi.
   */
  async disconnectInstance(token: string): Promise<unknown> {
    return this.post<unknown>(
      '/instance/disconnect',
      {},
      { headers: this.headers(token) },
    );
  }

  /**
   * Consulta o status atual de uma instancia na Uazapi.
   */
  async instanceStatus(token: string): Promise<unknown> {
    return this.get<unknown>('/instance/status', {
      headers: this.headers(token),
    });
  }

  /**
   * Remove uma instancia na Uazapi.
   */
  async deleteInstance(token: string): Promise<unknown> {
    try {
      const response = await this.http.delete<unknown>('/instance', {
        headers: this.headers(token),
      });
      return response.data;
    } catch (error) {
      this.handleError(error);
    }
  }

  /**
   * Configura webhook de uma instancia na Uazapi.
   */
  async configureWebhook(
    token: string,
    body: Record<string, unknown>,
  ): Promise<unknown> {
    return this.post<unknown>('/webhook', body, {
      headers: this.headers(token),
    });
  }

  /**
   * Envia mensagem de texto utilizando endpoint da Uazapi.
   */
  async sendText(
    token: string,
    body: Record<string, unknown>,
  ): Promise<unknown> {
    return this.post<unknown>('/send/text', body, {
      headers: this.headers(token),
    });
  }

  /**
   * Envia arquivo de midia utilizando endpoint da Uazapi.
   */
  async sendFile(
    token: string,
    body: Record<string, unknown>,
  ): Promise<unknown> {
    // Uazapi spec: POST /send/media for all media types
    return this.post<unknown>('/send/media', body, {
      headers: this.headers(token),
    });
  }

  /**
   * Atualiza estado de digitacao/presenca em conversa na Uazapi.
   */
  async sendPresence(
    token: string,
    body: Record<string, unknown>,
  ): Promise<unknown> {
    return this.post<unknown>('/message/presence', body, {
      headers: this.headers(token),
    });
  }

  /**
   * Atualiza imagem de perfil da instancia na Uazapi.
   */
  async updateProfileImage(token: string, image: string): Promise<unknown> {
    return this.post<unknown>(
      '/profile/image',
      { image },
      {
        headers: this.headers(token),
      },
    );
  }

  /**
   * Atualiza presenca global da instancia na Uazapi.
   */
  async updatePresence(
    token: string,
    presence: 'available' | 'unavailable',
  ): Promise<unknown> {
    return this.post<unknown>(
      '/instance/presence',
      { presence },
      {
        headers: this.headers(token),
      },
    );
  }

  /**
   * Solicita download de midia recebida via Uazapi.
   */
  async downloadMedia(
    token: string,
    body: Record<string, unknown>,
  ): Promise<unknown> {
    return this.post<unknown>('/message/download', body, {
      headers: this.headers(token),
    });
  }

  /**
   * Marca chat como lido na Uazapi.
   */
  async markAsRead(
    token: string,
    body: Record<string, unknown>,
  ): Promise<unknown> {
    return this.post<unknown>('/chat/read', body, {
      headers: this.headers(token),
    });
  }

  /**
   * Lista contatos na Uazapi com suporte a filtro opcional.
   */
  async listContacts(
    token: string,
    body?: Record<string, unknown>,
  ): Promise<unknown> {
    if (body && Object.keys(body).length > 0) {
      return this.post<unknown>('/contacts/list', body, {
        headers: this.headers(token),
      });
    }
    return this.get<unknown>('/contacts', { headers: this.headers(token) });
  }

  /**
   * Adiciona contato no provedor Uazapi.
   */
  async addContact(
    token: string,
    body: Record<string, unknown>,
  ): Promise<unknown> {
    return this.post<unknown>('/contact/add', body, {
      headers: this.headers(token),
    });
  }

  /**
   * Remove contato no provedor Uazapi.
   */
  async removeContact(
    token: string,
    body: Record<string, unknown>,
  ): Promise<unknown> {
    return this.post<unknown>('/contact/remove', body, {
      headers: this.headers(token),
    });
  }

  /**
   * Executa chamada HTTP GET com circuit breaker.
   *
   * @param url - Endpoint relativo da API
   * @param config - Configuracao Axios opcional
   * @returns Dados da resposta tipados como T
   */
  private async get<T = unknown>(
    url: string,
    config?: AxiosRequestConfig,
  ): Promise<T> {
    try {
      return await this.circuitBreaker.call(
        this.circuitName,
        async () => {
          const res = await this.http.get<T>(url, config);
          return res.data;
        },
        getCircuitBreakerOptions('whatsapp', { name: this.circuitName }),
      );
    } catch (error) {
      this.handleError(error);
    }
  }

  /**
   * Executa chamada HTTP POST com circuit breaker.
   *
   * @param url - Endpoint relativo da API
   * @param data - Corpo da requisicao
   * @param config - Configuracao Axios opcional
   * @returns Dados da resposta tipados como T
   */
  private async post<T = unknown>(
    url: string,
    data?: unknown,
    config?: AxiosRequestConfig,
  ): Promise<T> {
    try {
      return await this.circuitBreaker.call(
        this.circuitName,
        async () => {
          const res = await this.http.post<T>(url, data, config);
          return res.data;
        },
        getCircuitBreakerOptions('whatsapp', { name: this.circuitName }),
      );
    } catch (error) {
      this.handleError(error);
    }
  }

  /**
   * Trata erros de circuit breaker e Axios lancando HttpException adequada.
   *
   * @param error - Erro capturado na chamada HTTP
   */
  private handleError(error: unknown): never {
    if (error instanceof CircuitOpenException) {
      throw new HttpException('Uazapi circuit breaker is open', 503);
    }
    if (axios.isAxiosError(error)) {
      const err = error as AxiosError<unknown>;
      const status = err.response?.status ?? 500;
      const message = this.extractErrorMessage(err) ?? err.message;
      this.logger.error(`Uazapi error (${status}): ${message}`);
      throw new HttpException(message ?? 'Erro ao chamar Uazapi', status);
    }

    if (error instanceof Error) {
      this.logger.error('Uazapi unexpected error', error.stack);
    } else {
      this.logger.error('Uazapi unexpected error', JSON.stringify(error));
    }
    throw new HttpException('Erro interno', 500);
  }

  /**
   * Extrai mensagem de erro legivel de um AxiosError.
   *
   * @param err - Erro Axios capturado
   * @returns Mensagem de erro ou undefined
   */
  private extractErrorMessage(err: AxiosError<unknown>): string | undefined {
    const data = asRecord(err.response?.data);
    if (data) {
      const errorField = data.error;
      if (typeof errorField === 'string') {
        return errorField;
      }
      const messageField = data.message;
      if (typeof messageField === 'string') {
        return messageField;
      }
    }
    return typeof err.message === 'string' ? err.message : undefined;
  }

  private async ensureWebhookConfigured(
    token: string,
  ): Promise<WebhookMetadata | null> {
    if (!this.webhookUrl) {
      this.logger.warn(
        '[Uazapi] UAZAPI_WEBHOOK_URL not configured, skipping webhook setup',
      );
      return null;
    }

    const payload: JsonRecord = {
      enabled: true,
      url: this.buildWebhookUrl(token),
      events: this.webhookEvents,
      excludeMessages: this.webhookExcludeMessages,
    };

    const response = await this.retry(async (attempt) => {
      this.logger.log(`[Uazapi] Configuring webhook (attempt ${attempt})`);
      return this.configureWebhook(token, payload);
    }, this.webhookRetries);

    const metadata: WebhookMetadata = {
      url: payload.url as string,
      events: this.webhookEvents,
      excludeMessages: this.webhookExcludeMessages,
      response,
      configuredAt: new Date().toISOString(),
    };

    return metadata;
  }

  /**
   * Analisa a lista de strings de uma variavel de ambiente separada por virgulas.
   *
   * @param key - Nome da variavel de ambiente
   * @param defaults - Valor padrao quando a variavel nao estiver configurada
   * @returns Array de strings sem espacos
   */
  private parseList(key: string, defaults: string[]): string[] {
    const raw = this.configService.get<string>(key);
    if (!raw) {
      return defaults;
    }

    return raw
      .split(',')
      .map((item) => item.trim())
      .filter((item) => item.length > 0);
  }

  /**
   * Constroi a URL de webhook para a instancia informada.
   *
   * @param token - Token de autenticacao da instancia
   * @returns URL completa de callback de webhook
   */
  private buildWebhookUrl(token: string): string {
    const normalizedBase = this.webhookUrl?.replace(/\/+$/, '') ?? '';
    return `${normalizedBase}/webhooks/${this.webhookProvider}/instances/${encodeURIComponent(token)}`;
  }

  /**
   * Executa uma funcao assincrona com retentativas e atraso exponencial.
   *
   * @param fn - Funcao a executar recebendo o numero da tentativa
   * @param attempts - Numero maximo de tentativas
   * @param delayMs - Atraso base entre tentativas em milissegundos
   * @returns Resultado da funcao quando bem-sucedida
   */
  private async retry<T>(
    fn: (attempt: number) => Promise<T>,
    attempts = 3,
    delayMs = 1000,
  ): Promise<T> {
    const maxAttempts = Math.max(1, attempts);
    let lastError: unknown;

    for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
      try {
        return await fn(attempt);
      } catch (error) {
        lastError = error;
        this.logger.warn(
          `[Uazapi] Webhook configuration failed on attempt ${attempt}: ${
            (error as Error)?.message ?? 'unknown error'
          }`,
        );
        if (attempt === maxAttempts) {
          break;
        }
        await this.delay(delayMs * attempt);
      }
    }

    if (lastError instanceof Error) {
      throw lastError;
    }

    throw new Error('Webhook configuration failed after retries');
  }

  /**
   * Aguarda o numero de milissegundos informado.
   *
   * @param ms - Tempo de espera em milissegundos
   */
  private async delay(ms: number): Promise<void> {
    await new Promise((resolve) => setTimeout(resolve, ms));
  }

  /**
   * Extrai o token de instancia de um registro de resposta da Uazapi.
   *
   * @param data - Dados de resposta retornados na inicializacao da instancia
   * @returns Token da instancia ou null quando ausente
   */
  private extractInstanceToken(data: JsonRecord): string | null {
    const direct = getString(data, 'token');
    if (direct) {
      return direct;
    }

    const nested = getRecord(data, 'instance');
    const nestedToken = nested ? getString(nested, 'token') : undefined;
    return nestedToken ?? null;
  }
}
