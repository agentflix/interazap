import { Injectable, Logger } from '@nestjs/common';
import { RedisService } from '../../../../infrastructure/redis/redis.service';
import { MetaClient } from './meta.client';
import { MetaProvider } from './meta.provider';
import { MetaLookupService } from '../../http/meta-lookup.service';
import {
  MetaWhatsAppProvider,
  MetaTemplate,
  SendTemplateRequest,
  MetaWebhookPayload,
  NormalizedWebhookEvent,
} from '../../contracts/meta-provider.interface';
import {
  InstanceStatus,
  SendMediaRequest,
  SendMessageResult,
  SendTextRequest,
} from '../../contracts/provider.interface';

/**
 * Adaptador do provider Meta WhatsApp Business API.
 * Implementa a interface MetaWhatsAppProvider para envio e normalizacao de webhooks.
 */
@Injectable()
export class MetaAdapter implements MetaWhatsAppProvider {
  readonly name = 'meta' as const;

  private readonly logger = new Logger(MetaAdapter.name);
  private readonly templatesCacheTtlSeconds = 900; // 15 minutes

  constructor(
    private readonly client: MetaClient,
    private readonly redisService: RedisService,
    private readonly lookupService: MetaLookupService,
    private readonly provider: MetaProvider,
  ) {}

  /**
   * Envia mensagem de texto (nao suportado pela Meta fora da janela 24h).
   * Lanca erro indicando que deve usar sendTemplate.
   */
  sendText(
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    _instanceToken: string,
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    _request: SendTextRequest,
  ): Promise<SendMessageResult> {
    this.logger.warn(
      `sendText called on Meta adapter - this should use sendTemplate for Meta.`,
    );
    return Promise.resolve({
      success: false,
      error:
        'Meta provider requires sendTemplate for outbound messages. Use sendTemplate instead.',
    });
  }

  /**
   * Envia mensagem de midia (stub - nao implementado para Meta).
   */
  sendMedia(
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    _instanceToken: string,
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    _request: SendMediaRequest,
  ): Promise<SendMessageResult> {
    this.logger.warn('sendMedia is not implemented for Meta provider');
    return Promise.resolve({
      success: false,
      error: 'Not implemented for Meta provider',
    });
  }

  /**
   * Consulta status de conexao (sempre conectado para Meta Business API).
   */
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  getStatus(_instanceToken: string): Promise<InstanceStatus> {
    // Meta Business API instances are always "connected" as they use webhooks
    return Promise.resolve({
      connected: true,
      loggedIn: true,
    });
  }

  /**
   * Desconecta instancia (nao aplicavel para Meta Business API).
   */
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  disconnect(_instanceToken: string): Promise<void> {
    this.logger.debug('Disconnect called on Meta adapter - no-op');
    return Promise.resolve();
  }

  /**
   * Recupera QR Code (nao aplicavel para Meta Business API).
   */
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  getQrCode(_instanceToken: string): Promise<string | null> {
    return Promise.resolve(null);
  }

  /**
   * Lista templates aprovados da conta Business.
   * Utiliza cache Redis com TTL de 15 minutos.
   *
   * @param instanceToken - Token da instancia (access token)
   * @returns Lista de templates APPROVED
   */
  async listTemplates(instanceToken: string): Promise<MetaTemplate[]> {
    const cacheKey = `meta:templates:${instanceToken}`;

    // Check cache first
    try {
      const cached = await this.redisService.getClient().get(cacheKey);
      if (cached) {
        this.logger.debug(`Templates cache hit for instance ${instanceToken}`);
        return JSON.parse(cached) as MetaTemplate[];
      }
    } catch (error) {
      this.logger.warn(
        `Failed to read templates cache: ${error instanceof Error ? error.message : String(error)}`,
      );
    }

    // Fetch from Meta API - only APPROVED templates
    this.logger.debug(
      `Fetching templates from Meta API for instance ${instanceToken}`,
    );
    const templates = await this.client.getTemplates(instanceToken, {
      status: 'APPROVED',
    });

    // Cache for 15 minutes
    try {
      await this.redisService
        .getClient()
        .setex(
          cacheKey,
          this.templatesCacheTtlSeconds,
          JSON.stringify(templates),
        );
    } catch (error) {
      this.logger.warn(
        `Failed to cache templates: ${error instanceof Error ? error.message : String(error)}`,
      );
    }

    return templates;
  }

  /**
   * Envia mensagem via template aprovado.
   * Valida o numero de parametros antes de enviar.
   *
   * @param instanceToken - Token da instancia (access token)
   * @param request - Dados do template
   * @returns Resultado do envio
   */
  async sendTemplate(
    instanceToken: string,
    request: SendTemplateRequest,
  ): Promise<SendMessageResult> {
    // Validate template parameters count
    const templates = await this.listTemplates(instanceToken);
    const template = templates.find((t) => t.name === request.templateName);

    if (!template) {
      return {
        success: false,
        error: `Template '${request.templateName}' not found`,
      };
    }

    // Validate parameter count
    const bodyParams =
      template.components.find((c) => c.type === 'BODY')?.params ?? [];
    if ((request.templateParams?.length ?? 0) !== bodyParams.length) {
      return {
        success: false,
        error: `Template expects ${bodyParams.length} parameters, got ${request.templateParams?.length ?? 0}`,
      };
    }

    // Extract phone_number_id from instanceToken (expected format: phoneNumberId:accessToken)
    const { phoneNumberId, accessToken } =
      this.parseInstanceToken(instanceToken);

    if (!accessToken) {
      return {
        success: false,
        error: 'Invalid instance token format',
      };
    }

    return this.client.sendTemplate(phoneNumberId, accessToken, request);
  }

  /**
   * Normaliza payload do webhook da Meta.
   * Metodo ASSINCRONO que resolve phone_number_id via HTTP para o Backend.
   *
   * @param webhookToken - Token do webhook da instancia
   * @param rawPayload - Payload bruto do webhook
   * @returns Evento normalizado
   */
  async normalizeWebhook(
    webhookToken: string,
    rawPayload: unknown,
  ): Promise<NormalizedWebhookEvent> {
    const payload = rawPayload as MetaWebhookPayload;

    // Extract phone_number_id from payload
    const phoneNumberId =
      payload.entry[0]?.changes[0]?.value?.metadata?.phone_number_id ?? '';

    if (!phoneNumberId) {
      throw new Error('phone_number_id not found in webhook payload');
    }

    // Resolve instance via HTTP to Backend
    const instance =
      await this.lookupService.resolvePhoneNumberId(phoneNumberId);

    if (!instance) {
      throw new Error(
        `Instance not found for phone_number_id: ${phoneNumberId}`,
      );
    }

    // Validate webhook token
    if (instance.webhookToken !== webhookToken) {
      throw new Error('Webhook token mismatch');
    }

    // Normalize using the provider
    const normalized = this.provider.normalize(payload);

    return {
      tenantId: instance.tenantId,
      instanceId: instance.instanceId,
      instanceWebhookToken: webhookToken,
      provider: 'meta',
      eventType: normalized.event_type,
      direction: normalized.direction,
      message: normalized.message,
      status: normalized.status
        ? {
            messageId: normalized.status.messageId,
            status: normalized.status.status as
              | 'sent'
              | 'delivered'
              | 'read'
              | 'failed',
            timestamp: normalized.status.timestamp,
          }
        : undefined,
      rawPayload: payload as unknown as Record<string, unknown>,
      idempotencyKey: `${webhookToken}:${payload.entry[0]?.id ?? 'unknown'}:${Date.now()}`,
      receivedAt: new Date(),
    };
  }

  /**
   * Parse instance token to extract phone_number_id and access_token.
   * Expected format: phoneNumberId:accessToken
   */
  private parseInstanceToken(instanceToken: string): {
    phoneNumberId: string;
    accessToken: string;
  } {
    const parts = instanceToken.split(':');
    if (parts.length !== 2) {
      return { phoneNumberId: instanceToken, accessToken: '' };
    }
    return { phoneNumberId: parts[0], accessToken: parts[1] };
  }
}
