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
 * Guard that validates the `X-Telegram-Bot-Api-Secret-Token` header
 * sent by Telegram on every webhook request.
 *
 * Uses `crypto.timingSafeEqual` for constant-time comparison to
 * prevent timing-based side-channel attacks.
 */
@Injectable()
export class WebhookHmacSignatureGuard implements CanActivate {
  private readonly logger = new Logger(WebhookHmacSignatureGuard.name);

  constructor(private readonly configService: ConfigService) {}

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
   * Constant-time string comparison using `crypto.timingSafeEqual`.
   * Pads the shorter buffer to avoid length-based information leaks.
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
