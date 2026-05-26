import { BadRequestException, Injectable, PipeTransform } from '@nestjs/common';

/**
 * Valida e normaliza o token de instancia recebido em rotas de webhook.
 */
@Injectable()
export class InstanceTokenPipe implements PipeTransform<string, string> {
  /**
   * Sanitiza e valida o formato do token de instancia.
   */
  transform(value: string): string {
    if (typeof value !== 'string') {
      throw new BadRequestException('Invalid token format');
    }

    const token = value.trim();

    if (!token) {
      throw new BadRequestException('Invalid token format');
    }

    // Permite caracteres comuns de tokens de provedor e bloqueia entradas claramente invalidas.
    if (/[^A-Za-z0-9:_\-.~]/.test(token)) {
      throw new BadRequestException('Invalid token format');
    }

    return token;
  }
}
