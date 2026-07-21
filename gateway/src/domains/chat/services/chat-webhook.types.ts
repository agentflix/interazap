/**
 * Tipos internos do dominio Chat para payloads de stream e idempotencia de webhook.
 */
import { WebhookEventDto } from '../dto/webhook-event.dto';
import { UazapiProvider } from '../providers/uazapi/uazapi.provider';
import {
  MessageDeliveryError,
  MessageWindow,
  NormalizedTemplateStatusPayload,
} from '../contracts/provider.interface';

/** Base compartilhada por todos os payloads de stream do chat. */
export type StreamPayloadBase = {
  /** Identificador da instancia de chat, nullable quando ainda nao resolvido. */
  instance_id?: string | null;
};

/** Payload de stream normalizado para eventos da Uazapi com contexto de tenant. */
export type UazapiStreamPayload = StreamPayloadBase &
  ReturnType<UazapiProvider['normalize']> & {
    /** Identificador do tenant dono da instancia. */
    tenant_id: string;
  };

/** Payload de stream normalizado para eventos da Z-API. */
export type ZapiStreamPayload = StreamPayloadBase & {
  /** Identificador canônico do provedor. */
  provider: 'zapi';
  /** Tipo do evento normalizado. */
  event_type: string;
  /** Direcao do evento (incoming/outgoing/status/connection). */
  direction?: string;
  /** Token do webhook da instancia. */
  instance_webhook_token: string;
  /** Identificador do tenant dono da instancia. */
  tenant_id: string;
  /** Dados da mensagem normalizada quando o evento for de mensagem. */
  message?: {
    id?: string;
    from?: string;
    to?: string;
    type?: string;
    text?: string;
    body?: string;
    caption?: string;
    mediaUrl?: string;
    mimeType?: string;
    fileName?: string;
    timestamp?: string | Date;
    fromMe?: boolean;
    isGroup?: boolean;
    senderPhoto?: string;
    status?: string;
    /** ID (wamid) da mensagem citada — vindo de `messages[].context.id` (Meta). */
    quotedMessageId?: string;
    /** Dados do anúncio Click-to-WhatsApp (CTWA) quando a mensagem se originou de um. */
    referral?: {
      source_id?: string;
      source_type?: string;
      headline?: string;
      ctwa_clid?: string;
    };
  };
  /** Dados de status de entrega quando o evento for de atualizacao de status. */
  status?: {
    messageId?: string;
    status?: string;
    timestamp?: string | Date;
    error?: string;
    connected?: boolean;
    /** Janela de atendimento (24h/72h) capturada de `conversation` (Meta). */
    window?: MessageWindow;
    /** Erros de entrega reportados pela Meta quando `status === 'failed'`. */
    errors?: MessageDeliveryError[];
  };
  /** Dados de conexao quando o evento for de conexao/desconexao. */
  connection?: {
    status?: string;
    connected?: boolean;
  };
  /**
   * Presente apenas para eventos `meta.template.status_updated`.
   * Propaga o status do template para o Backend consumir via stream.
   */
  template?: NormalizedTemplateStatusPayload;
  /** Payload bruto original preservado para rastreabilidade. */
  raw?: Record<string, unknown>;
};

/** Payload de fallback para provedores sem normalizador dedicado. */
export type FallbackStreamPayload = StreamPayloadBase & {
  /** Nome do provedor de origem. */
  provider: string;
  /** Token do webhook da instancia. */
  instance_webhook_token: string;
  /** Identificador do tenant dono da instancia. */
  tenant_id: string;
  /** DTO do evento de webhook original sem normalizacao. */
  payload: WebhookEventDto;
  /** Tipo do evento quando disponivel. */
  event_type?: string;
  /** Direcao do evento quando disponivel. */
  direction?: string;
  /** Payload bruto preservado para rastreabilidade. */
  raw?: Record<string, unknown>;
  /** Sub-objeto de mensagem do DTO original. */
  message?: WebhookEventDto['message'];
};

/** Uniao discriminada de todos os tipos de payload de stream suportados. */
export type StreamPayload =
  | UazapiStreamPayload
  | ZapiStreamPayload
  | FallbackStreamPayload;

/** Metadados semanticos calculados a partir de um payload de stream. */
export type PayloadSemanticMetadata = {
  /** Classificacao semantica de alto nivel do evento. */
  semanticType: 'create' | 'update' | 'connection' | 'presence' | 'unknown';
  /** Tipo de mudanca granular do evento. */
  changeKind:
    | 'new_message'
    | 'edited'
    | 'status'
    | 'connection'
    | 'presence'
    | 'unknown';
  /** ID da mensagem de referencia quando aplicavel. */
  messageReferenceId: string | null;
  /** ID do evento de edicao quando o changeKind for 'edited'. */
  editEventId: string | null;
  /** Nome do evento WebSocket a ser emitido. */
  eventName: string | null;
};

/** Descritor completo de idempotencia calculado para um evento de webhook. */
export type IdempotencyDescriptor = {
  /** Chave de idempotencia composta e normalizada. */
  key: string;
  /** Nome do provedor de origem. */
  provider: string;
  /** Tipo do evento original. */
  eventType: string;
  /** Tipo do evento em letras minusculas para comparacao. */
  normalizedEventType: string;
  /** Token do webhook da instancia. */
  token: string;
  /** Discriminador especifico do evento para unicidade da chave. */
  discriminator: string;
  /** Metadados semanticos do evento. */
  semantics: PayloadSemanticMetadata;
};
