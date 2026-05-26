import { Injectable, Logger } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { LongPollingStrategy } from './long-polling.strategy';
import { WebhookStrategy } from './webhook.strategy';
import type { PollingStrategy } from './polling-strategy.interface';

type PollingMode = 'long_polling' | 'webhook';

/**
 * Factory que resolve a estratégia ativa de recepção de atualizações do Telegram
 * com base na variável de ambiente `TELEGRAM_POLLING_MODE`.
 *
 * Contexto: módulo bot. Padrões:
 * - production  → `webhook`
 * - development → `long_polling`
 *
 * Suporta troca em tempo de execução via `switchStrategy()`.
 */
@Injectable()
export class PollingStrategyFactory {
  private readonly logger = new Logger(PollingStrategyFactory.name);

  /** Override definido em runtime por `switchStrategy()`. Tem precedência sobre a variável de ambiente. */
  private runtimeMode: PollingMode | null = null;

  constructor(
    private readonly configService: ConfigService,
    private readonly longPolling: LongPollingStrategy,
    private readonly webhook: WebhookStrategy,
  ) {}

  // ─── Public API ────────────────────────────────────────────

  /**
   * Retorna a instância da estratégia correspondente ao modo atual.
   *
   * Ordem de resolução:
   * 1. Override de runtime (definido por `switchStrategy`)
   * 2. Variável de ambiente `TELEGRAM_POLLING_MODE`
   * 3. Padrão baseado no ambiente (production → webhook, demais → long_polling)
   * @returns Instância da estratégia ativa
   */
  getStrategy(): PollingStrategy {
    const mode = this.resolveMode();

    this.logger.debug(`Resolved polling mode: ${mode}`);

    if (mode === 'long_polling') {
      return this.longPolling;
    }

    return this.webhook;
  }

  /**
   * Troca a estratégia ativa em tempo de execução (hot-swap).
   *
   * 1. Para a estratégia atualmente ativa
   * 2. Persiste o novo modo para chamadas subsequentes a `getStrategy()`
   * 3. Inicia a nova estratégia
   * @param botToken Token da Telegram Bot API
   * @param webhookToken Token único do bot no URL de webhook
   * @param newMode Novo modo: 'long_polling' ou 'webhook'
   */
  async switchStrategy(
    botToken: string,
    webhookToken: string,
    newMode: PollingMode,
  ): Promise<void> {
    const currentStrategy = this.getActiveStrategy();

    this.logger.log(
      `Switching strategy: ${currentStrategy?.name ?? 'none'} → ${newMode}`,
    );

    // Stop whichever strategy is currently running
    if (currentStrategy?.isActive()) {
      await currentStrategy.stop();
    }

    this.runtimeMode = newMode;

    const nextStrategy = this.getStrategy();
    await nextStrategy.start(botToken, webhookToken);

    this.logger.log(`Strategy switched to ${nextStrategy.name} ✓`);
  }

  // ─── Private ───────────────────────────────────────────────

  private resolveMode(): PollingMode {
    if (this.runtimeMode) {
      return this.runtimeMode;
    }

    const envMode = this.configService.get<string>('TELEGRAM_POLLING_MODE');

    if (envMode === 'long_polling' || envMode === 'webhook') {
      return envMode;
    }

    const isProduction =
      this.configService.get<string>('NODE_ENV') === 'production';

    return isProduction ? 'webhook' : 'long_polling';
  }

  /**
   * Retorna a estratégia atualmente ativa (em execução),
   * ou `null` se nenhuma estiver ativa.
   * @returns Estratégia ativa ou null
   */
  private getActiveStrategy(): PollingStrategy | null {
    if (this.longPolling.isActive()) {
      return this.longPolling;
    }
    if (this.webhook.isActive()) {
      return this.webhook;
    }
    return null;
  }
}
