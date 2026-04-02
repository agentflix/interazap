/**
 * Modelos do dominio Chat/Uazapi do gateway.
 * Tipos normalizados de webhook e metadados de integracao HTTP.
 */

/**
 * Tipo de mensagem recebido no payload da Uazapi.
 */
export type MessageType = string;

/**
 * Evento normalizado de webhook produzido a partir da Uazapi.
 */
export type NormalizedUazapiEvent = {
  /** Provedor de origem do evento. */
  provider: 'uazapi';
  /** Tipo do evento conforme contrato normalizado. */
  event_type: 'messages' | 'messages_update' | 'connection' | 'presence';
  /** Token do webhook associado a instancia. */
  instance_webhook_token: string;
  /** Dono da instancia informado pelo provedor, quando houver. */
  owner?: string;
  /** URL base informada no webhook, quando disponivel. */
  base_url?: string;
  /** Direcao semantica da mensagem no provedor. */
  direction?: 'incoming' | 'outgoing';
  /** Informacoes de chat preservadas do payload original. */
  chat?: Record<string, unknown>;
  /** Presença do contato: composing | recording | paused. */
  presence?: 'composing' | 'recording' | 'paused';
  /** true para composing/recording, false para paused. */
  is_typing?: boolean;
  /** Telefone do contato (para resolução do ticket). */
  number?: string;
  /** Conteudo de mensagem extraido do payload. */
  message?: {
    id?: string;
    from?: string;
    to?: string;
    chatid?: string;
    body?: string;
    type?: MessageType;
    timestamp?: number | string;
    fromMe?: boolean;
    media?: Record<string, unknown> | undefined;
    mediaUrl?: string;
    mimeType?: string;
    fileName?: string;
    senderPhoto?: string;
  };
  /** Payload bruto original para rastreabilidade. */
  raw: Record<string, unknown>;
};

/**
 * Cabecalhos HTTP utilizados nas chamadas para a Uazapi.
 */
export type Headers = Record<string, string>;

/**
 * Metadados da configuracao de webhook na Uazapi.
 */
export type WebhookMetadata = {
  /** URL efetiva configurada para callback. */
  url: string;
  /** Eventos habilitados no provedor. */
  events: string[];
  /** Tipos de mensagem excluidos da entrega. */
  excludeMessages: string[];
  /** Resposta original retornada pela API de configuracao. */
  response: unknown;
  /** Timestamp ISO da configuracao realizada. */
  configuredAt: string;
};
