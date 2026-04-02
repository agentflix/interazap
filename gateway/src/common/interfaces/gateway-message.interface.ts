/**
 * Gateway Message Interface
 *
 * Envelope padrão para mensagens entre Laravel e Gateway via Redis Streams.
 * Todas as mensagens devem seguir esta estrutura para garantir rastreabilidade
 * e consistência na comunicação.
 */

/**
 * Domínios suportados pelo Gateway
 */
export type GatewayDomain = 'ai' | 'whatsapp' | 'payment' | 'webhook';

/**
 * Envelope padrão para mensagens do Gateway
 *
 * @template T - Tipo do payload específico da ação
 */
export interface GatewayMessage<T = unknown> {
  /**
   * UUID para correlacionar request/response.
   * Gerado pelo Laravel e usado para rastrear a mensagem em todo o fluxo.
   */
  correlationId: string;

  /**
   * ISO 8601 timestamp de quando a mensagem foi criada.
   * @example "2026-01-28T10:30:00.000Z"
   */
  timestamp: string;

  /**
   * Domínio da mensagem - determina qual consumer vai processar.
   */
  domain: GatewayDomain;

  /**
   * Ação a executar dentro do domínio.
   * @example "completion", "send-message", "create-invoice"
   */
  action: string;

  /**
   * Provider a usar para executar a ação.
   * @example "openai", "gemini", "uazapi", "zapi", "asaas"
   */
  provider: string;

  /**
   * Dados específicos da ação - estrutura varia conforme domain/action.
   */
  payload: T;

  /**
   * Metadados opcionais para contexto adicional.
   * Sempre incluir tenant_id para isolamento multi-tenant.
   */
  metadata?: GatewayMessageMetadata;
}

/**
 * Metadados opcionais da mensagem
 */
export interface GatewayMessageMetadata {
  /**
   * ID do tenant para isolamento multi-tenant
   */
  tenantId?: string;

  /**
   * ID da instância (WhatsApp, etc.)
   */
  instanceId?: string;

  /**
   * ID do ticket/conversa
   */
  ticketId?: string;

  /**
   * Informações de retry
   */
  retryCount?: number;
  maxRetries?: number;

  /**
   * Dados adicionais específicos do contexto
   */
  [key: string]: unknown;
}

/**
 * Factory para criar GatewayMessage com defaults
 */
export function createGatewayMessage<T>(
  partial: Omit<GatewayMessage<T>, 'timestamp'> &
    Partial<Pick<GatewayMessage<T>, 'timestamp'>>,
): GatewayMessage<T> {
  return {
    ...partial,
    timestamp: partial.timestamp ?? new Date().toISOString(),
  };
}
