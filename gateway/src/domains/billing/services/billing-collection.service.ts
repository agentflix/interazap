import { Injectable, Logger } from '@nestjs/common';
import { InternalApiClientService } from '../../../infrastructure/internal-api/internal-api-client.service';
import { GatewayCacheService } from '../../../infrastructure/cache/gateway-cache.service';
import { CacheStrategies } from '../../../infrastructure/cache/gateway-cache.types';
import { SendMessageService } from '../../chat/outbound/send-message.service';
import {
  BillingCollectionInstance,
  BillingCollectionSendResult,
  BillingCollectionTemplateVariables,
} from '../models/billing-collection.model';

/**
 * BillingCollectionService
 *
 * Responsável pelo envio de mensagens de cobrança via WhatsApp para tenants inadimplentes.
 * Resolve a instância de chat ativa do tenant, renderiza o template de cobrança e
 * delega o envio ao SendMessageService.
 */
@Injectable()
export class BillingCollectionService {
  private readonly logger = new Logger(BillingCollectionService.name);

  constructor(
    private readonly internalApiClient: InternalApiClientService,
    private readonly cacheService: GatewayCacheService,
    private readonly sendMessageService: SendMessageService,
  ) {}

  /**
   * Envia uma mensagem de cobrança via WhatsApp para o número informado.
   *
   * @param tenantId - Identificador do tenant destinatário
   * @param phone - Número de telefone do destinatário
   * @param templateId - Identificador do template de cobrança
   * @param variables - Variáveis para renderizar o template
   * @returns Resultado do envio com status, messageId e erro opcional
   */
  async send(
    tenantId: string,
    phone: string,
    templateId: string,
    variables: BillingCollectionTemplateVariables,
  ): Promise<BillingCollectionSendResult> {
    const instance = await this.resolveTenantInstance(tenantId);

    if (instance === null) {
      return {
        success: false,
        messageId: null,
        error: 'No active WhatsApp instance available for tenant.',
      };
    }

    const text = this.renderTemplate(templateId, variables);

    const result = await this.sendMessageService.send({
      tenantId,
      instanceId: instance.id,
      provider: instance.provider,
      instanceToken: instance.token,
      type: 'text',
      to: this.normalizePhone(phone),
      text,
      correlationId: `billing-collection:${tenantId}:${Date.now()}`,
    });

    if (result.success) {
      return {
        success: true,
        messageId: result.messageId ?? null,
        error: null,
      };
    }

    return {
      success: false,
      messageId: result.messageId ?? null,
      error: result.error ?? 'Message dispatch failed.',
    };
  }

  /**
   * Busca a instância WhatsApp ativa (conectada) do tenant via api/ HTTP.
   * Cache L2 Redis 60s (WARM strategy L2 TTL = 300s, mas para instâncias de cobrança usamos HOT).
   */
  private async resolveTenantInstance(
    tenantId: string,
  ): Promise<BillingCollectionInstance | null> {
    const cacheKey = `billing:collection-instance:${tenantId}`;

    try {
      const response = await this.cacheService.getOrFetch<{
        data: BillingCollectionInstance[];
      }>(
        cacheKey,
        async () => {
          const result = await this.internalApiClient.get<{
            data: BillingCollectionInstance[];
          }>(
            `/api/internal/chat/instances?tenant_id=${encodeURIComponent(tenantId)}&provider=uazapi&status=connected`,
            'billing_instances_list',
          );
          return result;
        },
        CacheStrategies.HOT,
        'billing_instances_list',
      );

      const instance = response.data?.[0] ?? null;
      if (!instance || !instance.token || instance.token.trim() === '') {
        this.logger.warn(`No connected instance token for tenant ${tenantId}`);
        return null;
      }

      return instance;
    } catch (error) {
      this.logger.warn(
        `Failed to resolve tenant instance: ${error instanceof Error ? error.message : String(error)}`,
      );
      return null;
    }
  }

  /**
   * Renderiza o template de cobrança com as variáveis fornecidas.
   *
   * @param templateId - Identificador do template
   * @param variables - Variáveis a serem substituídas no template
   * @returns Texto renderizado pronto para envio
   */
  private renderTemplate(
    templateId: string,
    variables: BillingCollectionTemplateVariables,
  ): string {
    const tenantName = this.valueAsString(variables.tenant_name, 'Cliente');
    const referenceMonth = this.valueAsString(variables.reference_month, '-');
    const amount = this.valueAsString(variables.amount, '-');
    const dueDate = this.valueAsString(variables.due_date, '-');
    const paymentUrl = this.valueAsString(variables.payment_url, '');
    const daysOverdue = this.valueAsString(variables.days_overdue, '0');

    return this.interpolate(this.templateById(templateId), {
      tenant_name: tenantName,
      reference_month: referenceMonth,
      amount,
      due_date: dueDate,
      payment_url: paymentUrl,
      days_overdue: daysOverdue,
    });
  }

  /**
   * Retorna o texto do template correspondente ao identificador fornecido.
   * Caso o template não exista, retorna o template padrão `friendly_reminder`.
   *
   * @param templateId - Identificador do template de cobrança
   * @returns String do template com placeholders `{{variavel}}`
   */
  private templateById(templateId: string): string {
    const templates: Record<string, string> = {
      friendly_reminder:
        'Olá, {{tenant_name}}! Sua fatura {{reference_month}} no valor de R$ {{amount}} venceu em {{due_date}}. Pague em: {{payment_url}}',
      urgent_reminder:
        'Atenção, {{tenant_name}}: sua fatura {{reference_month}} está em atraso há {{days_overdue}} dias. Evite bloqueio e regularize agora: {{payment_url}}',
      lockout_warning:
        'Último aviso: seu acesso será bloqueado em breve por inadimplência. Regularize sua fatura em {{payment_url}}.',
      lockout_notice:
        'Sua conta foi bloqueada por inadimplência. Após pagamento, o acesso é restabelecido automaticamente: {{payment_url}}',
      purge_warning:
        'Aviso crítico: sua conta está em fase de exclusão por inadimplência. Regularize imediatamente: {{payment_url}}',
    };

    return templates[templateId] ?? templates.friendly_reminder;
  }

  /**
   * Substitui os placeholders `{{chave}}` do template pelos valores fornecidos.
   *
   * @param template - String do template com placeholders
   * @param values - Mapa de chave para valor a ser substituído
   * @returns Template com todos os placeholders preenchidos
   */
  private interpolate(
    template: string,
    values: Record<string, string>,
  ): string {
    return template.replace(/{{\s*([a-z_]+)\s*}}/g, (_full, key: string) => {
      return values[key] ?? '';
    });
  }

  /**
   * Remove todos os caracteres não numéricos do número de telefone.
   *
   * @param phone - Número de telefone no formato original
   * @returns Número de telefone apenas com dígitos
   */
  private normalizePhone(phone: string): string {
    return phone.replace(/\D/g, '');
  }

  /**
   * Converte um valor de variável de template para string, usando fallback se necessário.
   *
   * @param value - Valor da variável (pode ser string, number, boolean, null ou undefined)
   * @param fallback - Valor de fallback caso value seja null ou undefined
   * @returns Representação em string do valor
   */
  private valueAsString(
    value: string | number | boolean | null | undefined,
    fallback: string,
  ): string {
    if (value === null || value === undefined) {
      return fallback;
    }

    return String(value);
  }
}
