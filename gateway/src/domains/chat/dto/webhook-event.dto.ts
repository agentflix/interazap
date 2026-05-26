import {
  IsNotEmpty,
  IsObject,
  IsOptional,
  IsString,
  registerDecorator,
  ValidateNested,
  ValidationOptions,
} from 'class-validator';
import { Expose, Transform, Type } from 'class-transformer';

/**
 * Valida que um valor, quando serializado como JSON, não ultrapasse `maxBytes`.
 *
 * @param maxBytes - Tamanho máximo em bytes (padrão: 102400 = 100 KB)
 * @param validationOptions - Opções padrão do class-validator
 */
function MaxJsonSize(maxBytes: number, validationOptions?: ValidationOptions) {
  return function (object: object, propertyName: string): void {
    registerDecorator({
      name: 'maxJsonSize',
      target: object.constructor,
      propertyName,
      options: {
        message: `Payload field '${propertyName}' exceeds the maximum allowed size of ${maxBytes} bytes`,
        ...validationOptions,
      },
      constraints: [maxBytes],
      validator: {
        validate(value: unknown): boolean {
          if (value === undefined || value === null) return true;
          try {
            return JSON.stringify(value).length <= maxBytes;
          } catch {
            return false;
          }
        },
      },
    });
  };
}

/**
 * Sub-objeto de mensagem normalizado presente nos webhooks recebidos.
 */
class WebhookMessageDto {
  @IsOptional()
  @IsString()
  id?: string;

  @IsOptional()
  @IsString()
  from?: string;

  @IsOptional()
  @IsString()
  to?: string;

  @IsOptional()
  @IsString()
  body?: string;

  @IsOptional()
  @IsString()
  type?: string;

  @IsOptional()
  timestamp?: string | number;
}

/**
 * Sub-objeto de instancia normalizado presente nos webhooks recebidos.
 */
class WebhookInstanceDto {
  @IsOptional()
  @IsString()
  name?: string;

  @IsOptional()
  @IsString()
  status?: string;

  @IsOptional()
  @IsString()
  token?: string;

  @IsOptional()
  @IsString()
  qrcode?: string;

  @IsOptional()
  @IsString()
  paircode?: string;
}

/**
 * Sub-objeto de status de conexao normalizado presente nos webhooks recebidos.
 */
class WebhookStatusDto {
  @IsOptional()
  connected?: boolean;

  @IsOptional()
  loggedIn?: boolean;

  @IsOptional()
  @IsString()
  status?: string;
}

/**
 * DTO canonico de evento de webhook consumido pelo ChatWebhookController.
 * Trata formatos Z-API (camelCase) e Uazapi (PascalCase) via decoradores Transform,
 * normalizando-os em estrutura consistente. O payload bruto original e preservado
 * no campo `raw` para processamento posterior.
 */
export class WebhookEventDto {
  @IsOptional()
  @IsString()
  EventType?: string;

  @IsOptional()
  @IsNotEmpty({ message: 'event_type must not be empty when provided' })
  @IsString()
  @Transform(({ obj, value }) => {
    if (typeof value === 'string' && value.length > 0) {
      return value;
    }
    if (!obj || typeof obj !== 'object') {
      return undefined;
    }
    const candidates = [
      (obj as Record<string, unknown>).EventType,
      (obj as Record<string, unknown>).eventType,
      (obj as Record<string, unknown>).event,
      (obj as Record<string, unknown>).type,
    ];
    return candidates.find(
      (candidate) => typeof candidate === 'string' && candidate.length > 0,
    );
  })
  event_type?: string;

  @IsOptional()
  @IsString()
  direction?: string;

  @IsOptional()
  @ValidateNested()
  @Type(() => WebhookMessageDto)
  message?: WebhookMessageDto;

  @IsOptional()
  @ValidateNested()
  @Type(() => WebhookInstanceDto)
  instance?: WebhookInstanceDto;

  @IsOptional()
  @ValidateNested()
  @Type(() => WebhookStatusDto)
  status?: WebhookStatusDto;

  @IsOptional()
  @IsString()
  owner?: string;

  @IsOptional()
  @IsString()
  BaseUrl?: string;

  @IsOptional()
  @IsObject()
  chat?: Record<string, unknown>;

  @IsOptional()
  @MaxJsonSize(102400)
  @Expose()
  @Transform(({ obj }) => {
    if (obj && typeof obj === 'object') {
      // Preserva o payload original completo como raw
      return { ...obj } as Record<string, unknown>;
    }
    return undefined;
  })
  raw?: Record<string, unknown>;
}
