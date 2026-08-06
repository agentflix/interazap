import { Injectable, Logger } from '@nestjs/common';
import { createHash } from 'crypto';
import { RedisService } from '../../../../infrastructure/redis/redis.service';
import { MetaClient } from './meta.client';
import { MetaProvider, NormalizedMetaEvent } from './meta.provider';
import type {
  MetaTemplateCreatePayload,
  InstanceLookupResult,
} from './meta.dto';
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
   * Envia mensagem de texto livre via Meta Cloud API.
   * Só é aceita pela Meta enquanto a janela de atendimento (24h/72h) estiver
   * aberta — fora dela, a Meta rejeita com o código 131047 (re-engagement),
   * tratado pelo `MetaClient.sendText` com mensagem clara indicando o uso de template.
   *
   * @param instanceToken - Token no formato 'phoneNumberId:accessToken'
   * @param request - Dados da mensagem de texto
   * @returns Resultado normalizado do envio
   */
  sendText(
    instanceToken: string,
    request: SendTextRequest,
  ): Promise<SendMessageResult> {
    const { phoneNumberId, accessToken } =
      this.parseInstanceToken(instanceToken);

    if (!accessToken) {
      return Promise.resolve({
        success: false,
        error: 'Invalid instance token format',
      });
    }

    return this.client.sendText(phoneNumberId, accessToken, {
      to: request.to,
      body: request.text,
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
    const { phoneNumberId, accessToken } =
      this.parseInstanceToken(instanceToken);

    if (!accessToken) {
      this.logger.warn(
        `listTemplates called with invalid token format for phone_number_id ${phoneNumberId || '(empty)'}`,
      );
      return [];
    }

    // Cache keyed por hash do token — o access token nunca aparece em chave Redis
    const cacheKey = this.templatesCacheKey(includeAll, instanceToken);

    // Verifica cache primeiro
    try {
      const cached = await this.redisService.getClient().get(cacheKey);
      if (cached) {
        this.logger.debug(
          `Templates cache hit for phone_number_id ${phoneNumberId}`,
        );
        return JSON.parse(cached) as MetaTemplate[];
      }
    } catch (error) {
      this.logger.warn(
        `Failed to read templates cache: ${error instanceof Error ? error.message : String(error)}`,
      );
    }

    // Busca na Meta API — o Graph recebe apenas o access token, nunca o token composto
    this.logger.debug(
      `Fetching templates from Meta API for phone_number_id ${phoneNumberId}`,
    );
    const templates = await this.client.getTemplates(accessToken, {
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
          this.templatesCacheKey(false, instanceToken),
          this.templatesCacheKey(true, instanceToken),
        );
    } catch (error) {
      this.logger.warn(
        `Failed to invalidate templates cache: ${error instanceof Error ? error.message : String(error)}`,
      );
    }
  }

  /**
   * Compoe a chave de cache de templates com hash do token composto.
   * Nunca usa o access token literal na chave Redis.
   */
  private templatesCacheKey(
    includeAll: boolean,
    instanceToken: string,
  ): string {
    const identifier = createHash('sha256')
      .update(instanceToken)
      .digest('hex')
      .slice(0, 32);

    return includeAll
      ? `meta:templates:all:${identifier}`
      : `meta:templates:approved:${identifier}`;
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
   * Normaliza o payload bruto do webhook da Meta, processando o **lote completo**
   * (`entry[] -> changes[] -> messages[]|statuses[]`), produzindo UM evento por
   * mensagem/status. A instância é resolvida **apenas** por `phone_number_id`
   * (via `MetaLookupService.resolvePhoneNumberId`) — ou por `entry.id` (WABA ID)
   * via `resolveWabaId` no ramo `message_template_status_update`. Não há
   * validação de "webhook token" contra o `phone_number_id`: o campo
   * `instanceWebhookToken` do evento vem diretamente da instância resolvida.
   *
   * Cada `change` do lote é processado de forma independente — falha ao
   * resolver uma instância (phone_number_id/waba_id desconhecido) apenas
   * descarta aquele evento com um log de warning, sem abortar os demais.
   *
   * @param rawPayload - Payload bruto do webhook (lote completo da Meta)
   * @returns Lista de eventos normalizados, um por mensagem/status/template do lote
   */
  async normalizeWebhookBatch(
    rawPayload: unknown,
  ): Promise<NormalizedWebhookEvent[]> {
    const payload = rawPayload as MetaWebhookPayload;
    const results: NormalizedWebhookEvent[] = [];

    for (const entry of payload.entry ?? []) {
      for (const change of entry.changes ?? []) {
        const field = change.field ?? 'messages';
        // Payload sintetico contendo apenas esta entry/change, para que o
        // MetaProvider normalize exatamente o subconjunto deste change
        // (preserva o metodo unico normalizeAll como fonte de verdade da
        // extracao de mensagens/status/template).
        const singleChangePayload: MetaWebhookPayload = {
          object: payload.object,
          entry: [{ id: entry.id, time: entry.time, changes: [change] }],
        };

        if (field === 'message_template_status_update') {
          const events = await this.buildTemplateEvents(
            entry.id ?? '',
            singleChangePayload,
          );
          results.push(...events);
          continue;
        }

        const phoneNumberId = change.value?.metadata?.phone_number_id ?? '';
        if (!phoneNumberId) {
          this.logger.warn(
            'phone_number_id not found in webhook change — event discarded',
          );
          continue;
        }

        const instance =
          await this.lookupService.resolvePhoneNumberId(phoneNumberId);
        if (!instance) {
          this.logger.warn(
            `Instance not found for phone_number_id: ${phoneNumberId} — event discarded`,
          );
          continue;
        }

        const normalizedEvents =
          this.provider.normalizeAll(singleChangePayload);
        for (const normalized of normalizedEvents) {
          results.push(
            this.buildNormalizedWebhookEvent(
              normalized,
              instance,
              singleChangePayload,
            ),
          );
        }
      }
    }

    return results;
  }

  /**
   * Normaliza payload do webhook da Meta.
   * Preservado por compatibilidade — delega para o primeiro evento de
   * `normalizeWebhookBatch()`. Especificações existentes (`meta.adapter.spec.ts`)
   * dependem deste método para o caminho de evento único.
   *
   * @param _webhookToken - Não utilizado; mantido apenas para satisfazer o contrato `MetaWhatsAppProvider`
   * @param rawPayload - Payload bruto do webhook
   * @returns Primeiro evento normalizado do lote
   */
  async normalizeWebhook(
    _webhookToken: string,
    rawPayload: unknown,
  ): Promise<NormalizedWebhookEvent> {
    const events = await this.normalizeWebhookBatch(rawPayload);
    const [first] = events;

    if (!first) {
      throw new Error('No resolvable event produced from Meta webhook payload');
    }

    return first;
  }

  /**
   * Constroi os eventos normalizados para o ramo `message_template_status_update`,
   * resolvendo a instância pelo `entry.id` (WABA ID).
   */
  private async buildTemplateEvents(
    wabaId: string,
    singleChangePayload: MetaWebhookPayload,
  ): Promise<NormalizedWebhookEvent[]> {
    if (!wabaId) {
      this.logger.warn(
        'waba_id (entry.id) not found in template_status_update payload — event discarded',
      );
      return [];
    }

    const wabaInstance = await this.lookupService.resolveWabaId(wabaId);
    if (!wabaInstance) {
      this.logger.warn(
        `Instance not found for waba_id: ${wabaId} — event discarded`,
      );
      return [];
    }

    const [normalized] = this.provider.normalizeAll(singleChangePayload);
    if (!normalized) {
      return [];
    }

    return [
      {
        tenantId: wabaInstance.tenantId,
        instanceId: wabaInstance.instanceId,
        // WabaLookupResult não expõe webhookToken (apenas InstanceLookupResult,
        // usado no lookup por phone_number_id, o faz) — divergência conhecida.
        instanceWebhookToken: '',
        provider: 'meta',
        eventType: normalized.event_type,
        direction: 'template_status',
        template: normalized.template,
        rawPayload: singleChangePayload as unknown as Record<string, unknown>,
        idempotencyKey: this.buildTemplateIdempotencyKey(
          wabaId,
          normalized.template,
        ),
        receivedAt: new Date(),
      },
    ];
  }

  /**
   * Constroi o `NormalizedWebhookEvent` final a partir de um `NormalizedMetaEvent`
   * (mensagem ou status) e da instância já resolvida por `phone_number_id`.
   */
  private buildNormalizedWebhookEvent(
    normalized: NormalizedMetaEvent,
    instance: InstanceLookupResult,
    singleChangePayload: MetaWebhookPayload,
  ): NormalizedWebhookEvent {
    return {
      tenantId: instance.tenantId,
      instanceId: instance.instanceId,
      instanceWebhookToken: instance.webhookToken,
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
            window: normalized.status.window,
            errors: normalized.status.errors,
          }
        : undefined,
      rawPayload: singleChangePayload as unknown as Record<string, unknown>,
      idempotencyKey: this.buildMessageIdempotencyKey(
        instance.instanceId,
        normalized,
      ),
      receivedAt: new Date(),
    };
  }

  /**
   * Constroi a chave de idempotência determinística para mensagens/status,
   * sem depender de timestamp — a reentrega da Meta deve produzir a mesma chave.
   * `meta:{instanceId}:{wamid}` para mensagens; `meta:{instanceId}:{statusId}:{status}` para status.
   */
  private buildMessageIdempotencyKey(
    instanceId: string,
    normalized: NormalizedMetaEvent,
  ): string {
    if (normalized.status) {
      return `meta:${instanceId}:${normalized.status.messageId}:${normalized.status.status}`;
    }

    if (normalized.message?.id) {
      return `meta:${instanceId}:${normalized.message.id}`;
    }

    return `meta:${instanceId}:${normalized.event_type}:${normalized.phone_number_id}`;
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
