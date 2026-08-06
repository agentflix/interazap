/**
 * Serviço de configuração tipada do webhook Meta (WABA).
 *
 * Contexto: valida `META_APP_SECRET` e `META_VERIFY_TOKEN` no `onModuleInit`,
 * seguindo o padrão fail-closed de `OpenAIConfigService`. Sem essas variáveis,
 * o fluxo Meta não deve operar: o controller nunca valida HMAC ou faz handshake
 * com chave vazia.
 */

import { Injectable, Logger, OnModuleInit } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { MetaConfiguration } from '../../../../core/config/configuration';

@Injectable()
export class MetaConfigService implements OnModuleInit {
  private readonly logger = new Logger(MetaConfigService.name);
  private readonly config: MetaConfiguration;

  constructor(private readonly configService: ConfigService) {
    this.config =
      this.configService.get<MetaConfiguration>('meta') ??
      ({} as MetaConfiguration);
  }

  onModuleInit(): void {
    this.validateConfiguration();
  }

  /**
   * Valida a configuração obrigatória do webhook Meta.
   * Fail-closed: configuração ausente loga erro de startup e impede operação.
   */
  private validateConfiguration(): void {
    const missing: string[] = [];
    if (!this.config.appSecret) {
      missing.push('META_APP_SECRET');
    }
    if (!this.config.verifyToken) {
      missing.push('META_VERIFY_TOKEN');
    }

    if (missing.length > 0) {
      this.logger.error(
        `Meta webhook is NOT configured: ${missing.join(', ')} missing. Webhook Meta será rejeitado (fail-closed).`,
      );
      return;
    }

    this.logger.log('Meta webhook configuration valid (appSecret + verifyToken set)');
  }

  /**
   * Verifica se o webhook Meta está corretamente configurado.
   * @returns `true` quando appSecret e verifyToken estão presentes
   */
  isConfigured(): boolean {
    return Boolean(this.config.appSecret && this.config.verifyToken);
  }
}
