import { WebhookEventDto } from '../dto/webhook-event.dto';
import { UazapiProvider } from '../providers/uazapi/uazapi.provider';
import { NormalizedTemplateStatusPayload } from '../contracts/provider.interface';

export type StreamPayloadBase = {
  instance_id?: string | null;
};

export type UazapiStreamPayload = StreamPayloadBase &
  ReturnType<UazapiProvider['normalize']> & {
    tenant_id: string;
  };

export type ZapiStreamPayload = StreamPayloadBase & {
  provider: 'zapi';
  event_type: string;
  direction?: string;
  instance_webhook_token: string;
  tenant_id: string;
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
  };
  status?: {
    messageId?: string;
    status?: string;
    timestamp?: string | Date;
    error?: string;
    connected?: boolean;
  };
  connection?: {
    status?: string;
    connected?: boolean;
  };
  /**
   * Presente apenas para eventos `meta.template.status_updated`.
   * Propaga o status do template para o Backend consumir via stream.
   */
  template?: NormalizedTemplateStatusPayload;
  raw?: Record<string, unknown>;
};

export type FallbackStreamPayload = StreamPayloadBase & {
  provider: string;
  instance_webhook_token: string;
  tenant_id: string;
  payload: WebhookEventDto;
  event_type?: string;
  direction?: string;
  raw?: Record<string, unknown>;
  message?: WebhookEventDto['message'];
};

export type StreamPayload =
  | UazapiStreamPayload
  | ZapiStreamPayload
  | FallbackStreamPayload;

export type PayloadSemanticMetadata = {
  semanticType: 'create' | 'update' | 'connection' | 'presence' | 'unknown';
  changeKind:
    | 'new_message'
    | 'edited'
    | 'status'
    | 'connection'
    | 'presence'
    | 'unknown';
  messageReferenceId: string | null;
  editEventId: string | null;
  eventName: string | null;
};

export type IdempotencyDescriptor = {
  key: string;
  provider: string;
  eventType: string;
  normalizedEventType: string;
  token: string;
  discriminator: string;
  semantics: PayloadSemanticMetadata;
};
