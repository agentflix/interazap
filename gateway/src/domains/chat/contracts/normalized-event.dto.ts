/**
 * DTOs de evento normalizado do dominio Chat.
 * Utilizados para validacao e transporte de payloads entre camadas.
 */
import {
  IsString,
  IsOptional,
  IsEnum,
  IsBoolean,
  ValidateNested,
  IsObject,
  IsDateString,
} from 'class-validator';
import { Type } from 'class-transformer';
import type {
  EventDirection,
  MessageStatus,
  MessageType,
  ProviderType,
} from '../models/webhook-event.model';

/**
 * Payload de mensagem normalizada extraida de um evento de webhook.
 */
export class MessagePayloadDto {
  @IsString()
  /** Identificador unico da mensagem. */
  id!: string;

  @IsString()
  /** Remetente da mensagem. */
  from!: string;

  @IsString()
  /** Destinatario da mensagem. */
  to!: string;

  @IsEnum([
    'text',
    'image',
    'video',
    'audio',
    'document',
    'sticker',
    'location',
    'contact',
  ])
  /** Tipo do conteudo da mensagem. */
  type!: MessageType;

  @IsOptional()
  @IsString()
  /** Conteudo textual da mensagem quando aplicavel. */
  text?: string;

  @IsOptional()
  @IsString()
  /** Legenda de midia quando aplicavel. */
  caption?: string;

  @IsOptional()
  @IsString()
  /** URL de midia quando aplicavel. */
  mediaUrl?: string;

  @IsOptional()
  @IsString()
  /** MIME type da midia quando aplicavel. */
  mimeType?: string;

  @IsOptional()
  @IsString()
  /** Nome do arquivo de midia quando aplicavel. */
  fileName?: string;

  @IsOptional()
  @IsBoolean()
  /** Indica se a mensagem foi enviada pela propria instancia. */
  isFromMe?: boolean;

  @IsOptional()
  @IsBoolean()
  /** Indica se a mensagem pertence a um grupo. */
  isGroup?: boolean;

  @IsOptional()
  @IsString()
  /** ID da mensagem citada quando aplicavel. */
  quotedMessageId?: string;

  @IsDateString()
  /** Timestamp ISO da mensagem. */
  timestamp!: string;
}

/**
 * Payload de status de entrega normalizado.
 */
export class StatusPayloadDto {
  @IsString()
  /** Identificador da mensagem cujo status foi atualizado. */
  messageId!: string;

  @IsEnum(['sent', 'delivered', 'read', 'failed'])
  /** Status de entrega normalizado. */
  status!: MessageStatus;

  @IsDateString()
  /** Timestamp ISO do evento de status. */
  timestamp!: string;

  @IsOptional()
  @IsString()
  /** Mensagem de erro quando o status for 'failed'. */
  error?: string;
}

/**
 * Payload de evento de conexao ou desconexao de instancia.
 */
export class ConnectionPayloadDto {
  @IsString()
  /** Status textual da conexao (ex: connected, disconnected, connecting). */
  status!: string;

  @IsBoolean()
  /** Indica se a instancia esta conectada. */
  connected!: boolean;

  @IsOptional()
  @IsString()
  /** QR code para pareamento quando disponivel. */
  qrCode?: string;

  @IsOptional()
  @IsString()
  /** Codigo de pareamento por telefone quando disponivel. */
  pairCode?: string;
}

/**
 * DTO completo de evento de webhook normalizado para validacao de entrada.
 */
export class NormalizedWebhookEventDto {
  @IsString()
  /** Identificador do tenant dono da instancia. */
  tenantId!: string;

  @IsString()
  /** Identificador da instancia de chat. */
  instanceId!: string;

  @IsString()
  /** Token do webhook vinculado a instancia. */
  instanceWebhookToken!: string;

  @IsEnum(['uazapi', 'zapi'])
  /** Provedor de mensageria de origem. */
  provider!: ProviderType;

  @IsString()
  /** Tipo do evento conforme o contrato normalizado. */
  eventType!: string;

  @IsEnum(['inbound', 'outbound', 'status', 'connection'])
  /** Direcao do evento normalizado. */
  direction!: EventDirection;

  @IsOptional()
  @ValidateNested()
  @Type(() => MessagePayloadDto)
  /** Payload de mensagem quando o evento for de mensagem. */
  message?: MessagePayloadDto;

  @IsOptional()
  @ValidateNested()
  @Type(() => StatusPayloadDto)
  /** Payload de status quando o evento for de atualizacao de status. */
  status?: StatusPayloadDto;

  @IsOptional()
  @ValidateNested()
  @Type(() => ConnectionPayloadDto)
  /** Payload de conexao quando o evento for de conexao. */
  connection?: ConnectionPayloadDto;

  @IsObject()
  /** Payload bruto original recebido do provedor. */
  rawPayload!: Record<string, unknown>;

  @IsString()
  /** Chave de idempotencia gerada para deduplicacao do evento. */
  idempotencyKey!: string;

  @IsDateString()
  /** Timestamp ISO de recebimento do evento no gateway. */
  receivedAt!: string;
}
