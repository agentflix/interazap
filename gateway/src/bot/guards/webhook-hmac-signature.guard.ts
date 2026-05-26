import {
  CanActivate,
  ExecutionContext,
  Injectable,
  Logger,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { timingSafeEqual } from 'node:crypto';
import { Request } from 'express';

/**
 * Guard que valida o header `X-Telegram-Bot-Api-Secret-Token`
 * enviado pelo Telegram em cada requisição de webhook.
 *
 * Contexto: módulo bot. Utiliza `crypto.timingSafeEqual` para comparação
 * em tempo constante, prevenindo ataques de timing side-channel.
 */
@Injectable()
export class WebhookHmacSignatureGuard implements CanActivate {
  private readonly logger = new Logger(WebhookHmacSignatureGuard.name);

  constructor(private readonly configService: ConfigService) {}

  /**
   * Valida o token secreto do webhook na requisição HTTP.
   * @param context Contexto de execução do NestJS
   * @returns true se o token for válido, false caso contrário
   */
  canActivate(context: ExecutionContext): boolean {
    const request = context.switchToHttp().getRequest<Request>();
    const secretToken = request.headers['x-telegram-bot-api-secret-token'] as
      | string
      | undefined;
    const expectedSecret = this.configService.get<string>(
      'TELEGRAM_WEBHOOK_SECRET',
    );

    if (!secretToken || !expectedSecret) {
      this.logger.warn(
        'Webhook request rejected: missing secret token or expected secret not configured',
      );
      return false;
    }

    const isValid = this.timingSafeEqual(secretToken, expectedSecret);

    if (!isValid) {
      this.logger.warn('Webhook request rejected: secret token mismatch');
    }

    return isValid;
  }

  /**
   * Comparação de strings em tempo constante usando `crypto.timingSafeEqual`.
   * Aplica padding no buffer mais curto para evitar vazamento de informação por tamanho.
   * @param a Primeira string a comparar
   * @param b Segunda string a comparar
   * @returns true se as strings forem idênticas
   */
  private timingSafeEqual(a: string, b: string): boolean {
    const bufA = Buffer.from(a, 'utf-8');
    const bufB = Buffer.from(b, 'utf-8');

    // If lengths differ, comparison must still run in constant time.
    // Pad the shorter buffer and always return false.
    if (bufA.length !== bufB.length) {
      const maxLen = Math.max(bufA.length, bufB.length);
      const paddedA = Buffer.alloc(maxLen);
      const paddedB = Buffer.alloc(maxLen);
      bufA.copy(paddedA);
      bufB.copy(paddedB);

      // Run timingSafeEqual anyway to avoid timing leaks on length difference
      timingSafeEqual(paddedA, paddedB);
      return false;
    }

    return timingSafeEqual(bufA, bufB);
  }
}
