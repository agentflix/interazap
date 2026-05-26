import {
  Injectable,
  NestInterceptor,
  ExecutionContext,
  CallHandler,
  Logger,
} from '@nestjs/common';
import { Observable, tap } from 'rxjs';
import { Request } from 'express';
import { IdempotencyService } from '../services/idempotency';

/**
 * Representa a requisição Express estendida com propriedades de idempotência.
 */
interface IdempotentRequest extends Request {
  idempotencyKey?: string;
  idempotencyTtl?: number;
}

/**
 * Interceptor que marca o webhook como processado após execução bem-sucedida.
 *
 * Contexto: trabalha em conjunto com IdempotentWebhookGuard. O guard verifica duplicatas
 * e armazena a chave na requisição; este interceptor persiste o resultado no Redis após
 * o handler executar com sucesso para que requisições duplicadas futuras recebam a resposta cacheada.
 *
 * @example
 * ```typescript
 * @UseGuards(IdempotentWebhookGuard)
 * @UseInterceptors(IdempotentResponseInterceptor)
 * @Post('webhook')
 * @Idempotent({ prefix: 'billing' })
 * async handleWebhook(@Body() data: WebhookDto) {
 *   return { success: true };
 * }
 * ```
 */
@Injectable()
export class IdempotentResponseInterceptor implements NestInterceptor {
  private readonly logger = new Logger(IdempotentResponseInterceptor.name);

  /**
   * Inicializa o interceptor com o IdempotencyService utilizado para persistir resultados.
   *
   * @param idempotencyService - Serviço que armazena resultados de webhooks processados no Redis
   */
  constructor(private readonly idempotencyService: IdempotencyService) {}

  /**
   * Intercepta respostas bem-sucedidas do handler e marca a chave de idempotência como processada.
   *
   * Após o handler da rota ser executado com sucesso, armazena o corpo da resposta no Redis
   * sob a chave de idempotência para que requisições duplicadas futuras recebam o resultado cacheado.
   * Em caso de erro do handler, a chave intencionalmente não é marcada para não bloquear retentativas.
   *
   * @param context - Contexto de execução do NestJS (usado para ler a chave de idempotência da requisição)
   * @param next - Cadeia observable representando o handler da rota
   * @returns O observable passado sem alterações; o registro é fire-and-forget
   */
  intercept(context: ExecutionContext, next: CallHandler): Observable<unknown> {
    const request = context.switchToHttp().getRequest<IdempotentRequest>();
    const idempotencyKey = request.idempotencyKey;
    const ttl = request.idempotencyTtl;

    if (!idempotencyKey) {
      return next.handle();
    }

    return next.handle().pipe(
      tap({
        next: (response: unknown) => {
          void this.idempotencyService
            .markProcessed(idempotencyKey, response, { ttlSeconds: ttl })
            .then(() => {
              this.logger.debug(`Marked as processed: ${idempotencyKey}`);
            })
            .catch((error: Error) => {
              this.logger.error(
                `Failed to mark as processed: ${error.message}`,
              );
            });
        },
        error: (error: Error) => {
          // Don't mark as processed on error
          this.logger.warn(
            `Webhook failed, not marking as processed: ${idempotencyKey}`,
            error.message,
          );
        },
      }),
    );
  }
}
