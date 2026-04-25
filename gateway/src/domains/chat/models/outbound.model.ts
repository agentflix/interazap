/**
 * Modelos do dominio Chat/Outbound do gateway.
 * Tipos de mensagens de saida, resultados de envio e fila de retry local.
 */

/**
 * Payload de mensagem de saida encaminhada para o provedor.
 */
export interface OutboundMessage {
  /** Tenant dono da mensagem. */
  tenantId: string;
  /** Instancia de chat utilizada no envio. */
  instanceId: string;
  /** Provedor de mensageria usado no envio. */
  provider: 'uazapi' | 'zapi' | 'meta' | 'telegram';
  /** Token da instancia no provedor. */
  instanceToken: string;
  /** Tipo da operacao de envio. */
  type: 'text' | 'media';
  /** Destinatario da mensagem. */
  to: string;
  /** Conteudo textual da mensagem. */
  text?: string;
  /** Tipo de midia quando o envio for de arquivo. */
  mediaType?: 'image' | 'video' | 'audio' | 'document';
  /** URL da midia a ser enviada. */
  mediaUrl?: string;
  /** Legenda da midia. */
  caption?: string;
  /** Nome original do arquivo quando aplicavel. */
  fileName?: string;
  /** Correlation ID para rastreabilidade ponta a ponta. */
  correlationId?: string;
}

/**
 * Resultado normalizado de tentativa de envio outbound.
 */
export interface SendResult {
  /** Indica se o envio foi concluido com sucesso. */
  success: boolean;
  /** ID da mensagem gerado pelo provedor quando disponivel. */
  messageId?: string;
  /** Erro retornado quando o envio falha. */
  error?: string;
  /** Quantidade de tentativas realizadas. */
  attempts: number;
  /** Tempo total de processamento em milissegundos. */
  processingTimeMs: number;
  /** Indica se a mensagem foi enfileirada para nova tentativa. */
  queued?: boolean;
}

/**
 * Mensagem pendente na fila local de retry do circuito.
 */
export interface PendingMessage {
  /** Mensagem original que sera reenviada. */
  message: OutboundMessage;
  /** Momento em que o item entrou na fila local. */
  addedAt: number;
  /** Quantidade de reenvios ja realizados para este item. */
  retryCount: number;
}
