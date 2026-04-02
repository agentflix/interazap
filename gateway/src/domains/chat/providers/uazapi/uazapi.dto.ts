/**
 * DTOs de webhook da integracao Uazapi.
 */
import { IsOptional, IsString } from 'class-validator';
import { MessageType, NormalizedUazapiEvent } from '../../models/uazapi.model';

export type { MessageType, NormalizedUazapiEvent };

/**
 * Payload de webhook recebido da Uazapi antes da normalizacao.
 * Campos desconhecidos sao preservados via index signature.
 */
export class UazapiWebhookDto {
  @IsString()
  /** Tipo de evento conforme especificado pela Uazapi. */
  EventType!: 'messages' | 'messages_update' | 'connection' | 'presence';

  @IsOptional()
  @IsString()
  /** Telefone do contato (para webhooks de presence/composing). */
  number?: string;

  @IsOptional()
  /** Dados da mensagem recebida quando aplicavel. */
  message?: {
    id?: string;
    type?: MessageType;
    from?: string;
    to?: string;
    body?: string;
    timestamp?: number | string;
    // other fields are kept as-is
    [key: string]: unknown;
  };

  @IsOptional()
  /** Dados do chat associado ao evento. */
  chat?: Record<string, unknown>;

  @IsOptional()
  /** Dados da instancia associados ao evento. */
  instance?: Record<string, unknown>;

  @IsOptional()
  /** Nome da instancia no provedor. */
  instanceName?: string;

  @IsOptional()
  /** URL base configurada no webhook. */
  BaseUrl?: string;

  @IsOptional()
  /** Telefone do proprietario da instancia. */
  owner?: string;

  @IsOptional()
  /** Token de autenticacao da instancia. */
  token?: string;

  /** Campos dinamicos adicionais preservados do payload original. */
  [key: string]: unknown;
}

/**
 * Type guard que verifica se um WebhookEventDto e compativel com UazapiWebhookDto.
 *
 * Valida a presenca do campo discriminante `EventType` com valores conhecidos da Uazapi.
 */
export function isUazapiWebhookEvent(
  event: unknown,
): event is UazapiWebhookDto {
  if (typeof event !== 'object' || event === null) return false;
  const candidate = event as Record<string, unknown>;
  return (
    typeof candidate['EventType'] === 'string' &&
    ['messages', 'messages_update', 'connection', 'presence'].includes(
      candidate['EventType'],
    )
  );
}
