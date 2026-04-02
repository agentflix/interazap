import { IsIn, IsOptional, IsString } from 'class-validator';
import { Transform } from 'class-transformer';

/**
 * Canonical outbound message DTO consumed by the Chat Outbound API.
 * Supports both UAZAPI and ZAPI providers with text and media types.
 * Field names are normalized from snake_case aliases via Transform decorators.
 */
export class OutboundMessageDto {
  @IsString()
  @IsIn(['uazapi', 'zapi'])
  provider!: 'uazapi' | 'zapi';

  @IsString()
  @Transform(({ value, obj }) => {
    if (typeof value === 'string') return value;
    if (!obj || typeof obj !== 'object') return undefined;
    const fallback = (obj as Record<string, unknown>).instance_token;
    return typeof fallback === 'string' ? fallback : undefined;
  })
  instanceToken!: string;

  @IsString()
  @Transform(({ value, obj }) => {
    if (typeof value === 'string') return value;
    if (!obj || typeof obj !== 'object') return undefined;
    const fallback = (obj as Record<string, unknown>).tenant_id;
    return typeof fallback === 'string' ? fallback : undefined;
  })
  tenantId!: string;

  @IsString()
  @Transform(({ value, obj }) => {
    if (typeof value === 'string') return value;
    if (!obj || typeof obj !== 'object') return undefined;
    const fallback = (obj as Record<string, unknown>).instance_id;
    return typeof fallback === 'string' ? fallback : undefined;
  })
  instanceId!: string;

  @IsString()
  @IsIn(['text', 'media'])
  type!: 'text' | 'media';

  @IsString()
  to!: string;

  @IsOptional()
  @IsString()
  text?: string;

  @IsOptional()
  @IsString()
  @Transform(({ value, obj }) => {
    if (typeof value === 'string') return value;
    if (!obj || typeof obj !== 'object') return undefined;
    const fallback = (obj as Record<string, unknown>).media_type;
    return typeof fallback === 'string' ? fallback : undefined;
  })
  mediaType?: 'image' | 'video' | 'audio' | 'document';

  @IsOptional()
  @IsString()
  @Transform(({ value, obj }) => {
    if (typeof value === 'string') return value;
    if (!obj || typeof obj !== 'object') return undefined;
    const fallback = (obj as Record<string, unknown>).media_url;
    return typeof fallback === 'string' ? fallback : undefined;
  })
  mediaUrl?: string;

  @IsOptional()
  @IsString()
  caption?: string;

  @IsOptional()
  @IsString()
  @Transform(({ value, obj }) => {
    if (typeof value === 'string') return value;
    if (!obj || typeof obj !== 'object') return undefined;
    const fallback = (obj as Record<string, unknown>).file_name;
    return typeof fallback === 'string' ? fallback : undefined;
  })
  fileName?: string;

  @IsOptional()
  @IsString()
  @Transform(({ value, obj }) => {
    if (typeof value === 'string') return value;
    if (!obj || typeof obj !== 'object') return undefined;
    const fallback = (obj as Record<string, unknown>).correlation_id;
    return typeof fallback === 'string' ? fallback : undefined;
  })
  correlationId?: string;
}
