import {
  CanActivate,
  ExecutionContext,
  Injectable,
  UnauthorizedException,
  Logger,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { Request } from 'express';

/**
 * InternalApiKeyGuard
 *
 * Guard de autenticação por API key para endpoints internos.
 * Verifica o cabeçalho `x-api-key` contra a chave configurada em `INTERNAL_API_KEY`.
 * Impede acesso não autorizado a rotas que não devem ser expostas publicamente.
 */
@Injectable()
export class InternalApiKeyGuard implements CanActivate {
  private readonly logger = new Logger(InternalApiKeyGuard.name);

  constructor(private readonly configService: ConfigService) {}

  /**
   * Verifica a API key presente no cabeçalho `x-api-key` da requisição HTTP.
   *
   * @param context - Contexto de execução NestJS (HTTP)
   * @returns `true` se a API key for válida
   * @throws UnauthorizedException se a chave estiver ausente, inválida ou não configurada
   */
  canActivate(context: ExecutionContext): boolean {
    const request = context.switchToHttp().getRequest<Request>();
    const apiKey = request.headers['x-api-key'];
    const expectedKey = this.configService.get<string>('internal.apiKey');

    if (!expectedKey) {
      this.logger.error('INTERNAL_API_KEY not configured');
      throw new UnauthorizedException('Internal API not properly configured');
    }

    if (!apiKey || apiKey !== expectedKey) {
      this.logger.warn('Invalid or missing API key for internal endpoint', {
        ip: request.ip,
        path: request.path,
      });
      throw new UnauthorizedException('Invalid API key');
    }

    return true;
  }
}
