import { Injectable, Logger } from '@nestjs/common';
import { RedisService } from '../../../../infrastructure/redis/redis.service';
import { MetaClient } from './meta.client';
import { MetaProvider } from './meta.provider';
import type { MetaTemplateCreatePayload } from './meta.dto';
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
    _instanceToken: string,

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
    _instanceToken: string,

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

  getStatus(_instanceToken: string): Promise<InstanceStatus> {
    // Instancias Meta Business API sao sempre "connected" pois usam webhooks
    return Promise.resolve({
      connected: true,
      loggedIn: true,
    });
  }

  /**
   * Desconecta instancia (nao aplicavel para Meta Business API).
   */

  disconnect(_instanceToken: string): Promise<void> {
    this.logger.debug('Disconnect called on Meta adapter - no-op');
    return Promise.resolve();
  }

  /**
   * Recupera QR Code (nao aplicavel para Meta Business API).
   */

  getQrCode(_instanceToken: string): Promise<string | null> {
    return Promise.resolve(null);
  }

  /**
   * Lista templates da conta Business.
   * Utiliza cache Redis com TTL de 15 minutos.
   *
   * @param instanceToken - Token da instancia (access token)
   * @param includeAll - Se true, retorna todos os status; se false, apenas APPROVED
   * @returns Lista de templates
   */
  async listTemplates(
    instanceToken: string,
    includeAll = false,
  ): Promise<MetaTemplate[]> {
    const cacheKey = includeAll
      ? `meta:templates:all:${instanceToken}`
      : `meta:templates:approved:${instanceToken}`;

    // Verifica cache primeiro
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

    // Busca na Meta API
    this.logger.debug(
      `Fetching templates from Meta API for instance ${instanceToken}`,
    );
    const templates = await this.client.getTemplates(instanceToken, {
      status: includeAll ? undefined : 'APPROVED',
    });

    // Armazena em cache por 15 minutos
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
    // Valida a quantidade de parametros do template
    const templates = await this.listTemplates(instanceToken);
    const template = templates.find((t) => t.name === request.templateName);

    if (!template) {
      return {
        success: false,
        error: `Template '${request.templateName}' not found`,
      };
    }

    // Valida a quantidade de parametros
    const bodyParams =
      template.components.find((c) => c.type === 'BODY')?.params ?? [];
    if ((request.templateParams?.length ?? 0) !== bodyParams.length) {
      return {
        success: false,
        error: `Template expects ${bodyParams.length} parameters, got ${request.templateParams?.length ?? 0}`,
      };
    }

    // Extrai phone_number_id do instanceToken (formato esperado: phoneNumberId:accessToken)
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
   * Invalida cache de templates para um token de instancia.
   *
   * @param instanceToken - Token da instancia
   */
  async invalidateTemplatesCache(instanceToken: string): Promise<void> {
    try {
      await this.redisService
        .getClient()
        .del(
          `meta:templates:approved:${instanceToken}`,
          `meta:templates:all:${instanceToken}`,
        );
    } catch (error) {
      this.logger.warn(
        `Failed to invalidate templates cache: ${error instanceof Error ? error.message : String(error)}`,
      );
    }
  }

  /**
   * Cria um novo template de mensagem na conta Business.
   *
   * @param wabaToken - Token no formato wabaId:accessToken
   * @param payload - Dados do template a criar
   * @returns ID e status do template criado
   */
  async createTemplate(
    wabaToken: string,
    payload: MetaTemplateCreatePayload,
  ): Promise<{ id: string; status: string }> {
    const { phoneNumberId: wabaId, accessToken } =
      this.parseWabaToken(wabaToken);

    const result = await this.client.createTemplate(
      wabaId,
      accessToken,
      payload,
    );
    await this.invalidateTemplatesCache(wabaToken);
    return result;
  }

  /**
   * Remove um template de mensagem da conta Business.
   *
   * @param wabaToken - Token no formato wabaId:accessToken
   * @param name - Nome do template a remover
   * @returns Sucesso da operacao
   */
  async deleteTemplate(
    wabaToken: string,
    name: string,
  ): Promise<{ success: boolean }> {
    const { phoneNumberId: wabaId, accessToken } =
      this.parseWabaToken(wabaToken);

    await this.client.deleteTemplate(wabaId, accessToken, name);
    await this.invalidateTemplatesCache(wabaToken);
    return { success: true };
  }

  /**
   * Decompoe o token WABA no formato 'wabaId:accessToken'.
   *
   * @param wabaToken - Token no formato 'wabaId:accessToken'
   * @returns Objeto com wabaId e accessToken separados
   * @throws Error quando o formato nao e valido
   */
  private parseWabaToken(wabaToken: string): {
    phoneNumberId: string;
    accessToken: string;
  } {
    const parts = wabaToken.split(':');
    if (parts.length !== 2 || !parts[0]) {
      throw new Error('Invalid instance token format');
    }
    return { phoneNumberId: parts[0], accessToken: parts[1] };
  }

  /**
   * Normaliza payload do webhook da Meta.
   * Metodo ASSINCRONO que resolve phone_number_id via HTTP para o Backend.
   *
   * Eventos `message_template_status_update` NÃO trazem phone_number_id;
   * são roteados pelo `entry.id` (WABA ID) via `MetaLookupService.resolveWabaId`.
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
    const entry = payload.entry?.[0];
    const change = entry?.changes?.[0];
    const field = change?.field ?? 'messages';

    // Ramo: template_status_update — sem phone_number_id, lookup por waba_id
    if (field === 'message_template_status_update') {
      const wabaId = entry?.id ?? '';
      if (!wabaId) {
        throw new Error(
          'waba_id (entry.id) not found in template_status_update payload',
        );
      }

      const wabaInstance = await this.lookupService.resolveWabaId(wabaId);
      if (!wabaInstance) {
        throw new Error(`Instance not found for waba_id: ${wabaId}`);
      }

      const normalized = this.provider.normalize(payload);

      return {
        tenantId: wabaInstance.tenantId,
        instanceId: wabaInstance.instanceId,
        instanceWebhookToken: webhookToken,
        provider: 'meta',
        eventType: normalized.event_type,
        direction: 'template_status',
        template: normalized.template,
        rawPayload: payload as unknown as Record<string, unknown>,
        idempotencyKey: this.buildTemplateIdempotencyKey(
          wabaId,
          normalized.template,
        ),
        receivedAt: new Date(),
      };
    }

    // Ramo padrao: messages / statuses — usa phone_number_id
    const phoneNumberId = change?.value?.metadata?.phone_number_id ?? '';

    if (!phoneNumberId) {
      throw new Error('phone_number_id not found in webhook payload');
    }

    // Resolve instancia via HTTP para o Backend
    const instance =
      await this.lookupService.resolvePhoneNumberId(phoneNumberId);

    if (!instance) {
      throw new Error(
        `Instance not found for phone_number_id: ${phoneNumberId}`,
      );
    }

    // Valida o token do webhook
    if (instance.webhookToken !== webhookToken) {
      throw new Error('Webhook token mismatch');
    }

    // Normaliza usando o provider
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
   * Constrói a chave de idempotência para um evento de template.
   * Usa external_id + status (event) para garantir que cada transição vire um evento único,
   * sem depender de timestamp (que mudaria entre tentativas de retry da Meta).
   */
  private buildTemplateIdempotencyKey(
    wabaId: string,
    template: NormalizedWebhookEvent['template'],
  ): string {
    const externalId = template?.external_id ?? 'unknown';
    const event = template?.event ?? 'unknown';
    return `meta:template:${wabaId}:${externalId}:${event}`;
  }

  /**
   * Decompoe o token de instancia no formato 'phoneNumberId:accessToken'.
   *
   * @param instanceToken - Token no formato 'phoneNumberId:accessToken'
   * @returns Objeto com phoneNumberId e accessToken separados
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
