import {
  Injectable,
  CanActivate,
  ExecutionContext,
  Logger,
} from '@nestjs/common';
import { Reflector } from '@nestjs/core';
import { Request, Response } from 'express';
import { IdempotencyService } from '../services/idempotency';
import { IdempotentOptions } from '../models/idempotency.model';

export type { IdempotentOptions };

/**
 * Chaves de metadados para o decorator de idempotência.
 */
export const IDEMPOTENT_KEY = 'idempotent';
export const IDEMPOTENT_TTL_KEY = 'idempotent_ttl';
export const IDEMPOTENT_PREFIX_KEY = 'idempotent_prefix';

/**
 * Decorator para marcar uma rota como idempotente.
 * Previne o processamento duplicado de webhooks com a mesma chave de idempotência.
 *
 * @example
 * ```typescript
 * @Post('webhook')
 * @Idempotent({ prefix: 'billing', ttlSeconds: 86400 })
 * async handleWebhook(@Body() data: WebhookDto) {
 *   // Processado apenas uma vez por chave de idempotência
 * }
 * ```
 */
export function Idempotent(options: IdempotentOptions = {}): MethodDecorator {
  return (
    _target: object,
    _key: string | symbol,
    descriptor: PropertyDescriptor,
  ) => {
    Reflect.defineMetadata(IDEMPOTENT_KEY, true, descriptor.value as object);
    Reflect.defineMetadata(
      IDEMPOTENT_TTL_KEY,
      options.ttlSeconds || 86400,
      descriptor.value as object,
    );
    Reflect.defineMetadata(
      IDEMPOTENT_PREFIX_KEY,
      options.prefix || 'webhook',
      descriptor.value as object,
    );
    return descriptor;
  };
}

/**
 * Guard para processamento idempotente de webhooks.
 *
 * Intercepta requisições marcadas com o decorator @Idempotent()
 * e verifica se o webhook já foi processado anteriormente.
 */
@Injectable()
export class IdempotentWebhookGuard implements CanActivate {
  private readonly logger = new Logger(IdempotentWebhookGuard.name);
  private readonly defaultHeaderName = 'x-idempotency-key';
  private readonly webhookIdHeaders = [
    'x-webhook-id',
    'x-event-id',
    'x-request-id',
    'x-correlation-id',
  ];

  /**
   * Initializes the guard with Reflector for metadata reading and IdempotencyService for cache checks.
   *
   * @param reflector - NestJS service for reading route metadata (e.g. @Idempotent decorators)
   * @param idempotencyService - Service that checks/marks idempotency keys in Redis
   */
  constructor(
    private readonly reflector: Reflector,
    private readonly idempotencyService: IdempotencyService,
  ) {}

  /**
   * Determines whether the route handler may execute.
   *
   * Returns `true` immediately for non-idempotent routes or requests without an idempotency key.
   * When a key is present, checks Redis for a prior result; if found, writes the cached
   * response to the HTTP reply and returns `false` to short-circuit the handler.
   *
   * @param context - NestJS execution context providing access to request, response, and route handler
   * @returns `true` to allow handler execution, `false` to short-circuit with cached response
   */
  async canActivate(context: ExecutionContext): Promise<boolean> {
    const handler = context.getHandler();

    // Check if route is marked as idempotent
    const isIdempotent = this.reflector.get<boolean>(IDEMPOTENT_KEY, handler);
    if (!isIdempotent) {
      return true;
    }

    const request = context.switchToHttp().getRequest<Request>();
    const response = context.switchToHttp().getResponse<Response>();

    // Extract idempotency key
    const idempotencyKey = this.extractIdempotencyKey(request);
    if (!idempotencyKey) {
      this.logger.warn('No idempotency key found in request');
      return true; // Allow processing if no key found
    }

    const prefix =
      this.reflector.get<string>(IDEMPOTENT_PREFIX_KEY, handler) || 'webhook';
    const ttl =
      this.reflector.get<number>(IDEMPOTENT_TTL_KEY, handler) || 86400;

    const fullKey = `${prefix}:${idempotencyKey}`;

    // Check if already processed
    const result = await this.idempotencyService.check(fullKey, { prefix });

    if (result.isDuplicate) {
      this.logger.debug(`Duplicate webhook detected: ${fullKey}`);

      // Set response headers and return cached response
      response.setHeader('X-Idempotency-Cached', 'true');
      response.setHeader('X-Idempotency-Key', idempotencyKey);

      if (result.cachedResult) {
        response.status(200).json(result.cachedResult);
      } else {
        response.status(200).json({
          success: true,
          message: 'Already processed',
          idempotencyKey,
        });
      }

      return false; // Prevent handler execution
    }

    // Store key for later marking
    (
      request as Request & { idempotencyKey?: string; idempotencyTtl?: number }
    ).idempotencyKey = fullKey;
    (
      request as Request & { idempotencyKey?: string; idempotencyTtl?: number }
    ).idempotencyTtl = ttl;

    // Add header to indicate processing
    response.setHeader('X-Idempotency-Processed', 'true');
    response.setHeader('X-Idempotency-Key', idempotencyKey);

    return true;
  }

  /**
   * Extrai a chave de idempotência dos headers ou body da requisição.
   * Tenta headers comuns de webhook antes de inspecionar o body.
   *
   * @param request - Objeto de requisição Express
   * @returns Chave de idempotência ou null se não encontrada
   */
  private extractIdempotencyKey(request: Request): string | null {
    // Try default header first
    const defaultKey = request.headers[this.defaultHeaderName] as string;
    if (defaultKey) {
      return defaultKey;
    }

    // Try common webhook ID headers
    for (const header of this.webhookIdHeaders) {
      const value = request.headers[header] as string;
      if (value) {
        return value;
      }
    }

    // Try to extract from body (common webhook patterns)
    const body = request.body as Record<string, unknown>;
    if (body) {
      // Common webhook ID field names
      const idFields = [
        'id',
        'eventId',
        'event_id',
        'webhookId',
        'webhook_id',
        'messageId',
        'message_id',
        'requestId',
        'request_id',
      ];

      for (const field of idFields) {
        const fieldValue = body[field];
        if (fieldValue && typeof fieldValue === 'string') {
          return fieldValue;
        }
      }

      // Nested event object
      const eventObj = body.event as Record<string, unknown> | undefined;
      if (eventObj?.id && typeof eventObj.id === 'string') {
        return eventObj.id;
      }

      // Nested data object
      const dataObj = body.data as Record<string, unknown> | undefined;
      if (dataObj?.id && typeof dataObj.id === 'string') {
        return dataObj.id;
      }
    }

    return null;
  }
}
