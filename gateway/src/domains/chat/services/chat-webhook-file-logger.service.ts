import {
  Injectable,
  Logger,
  OnModuleDestroy,
  OnModuleInit,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import {
  appendFile,
  mkdir,
  readdir,
  readFile,
  stat,
  unlink,
  writeFile,
} from 'node:fs/promises';
import { join } from 'node:path';
import { Counter, Gauge, register } from 'prom-client';
import { QueueEntry } from '../models/webhook-logger.model';

/**
 * Persistencia assicrona de logs de webhook em arquivo com fila em memoria.
 */
@Injectable()
export class ChatWebhookFileLoggerService
  implements OnModuleInit, OnModuleDestroy
{
  private readonly logger = new Logger(ChatWebhookFileLoggerService.name);
  private readonly logsDirectory: string;
  private readonly retentionDays: number;

  private readonly writeQueue: QueueEntry[] = [];
  private isFlushing = false;
  private purgeInterval: ReturnType<typeof setInterval> | null = null;

  private readonly queueSizeGauge: Gauge<string>;
  private readonly writeFailuresCounter: Counter<string>;
  private readonly filesPurgedCounter: Counter<string>;

  constructor(private readonly configService: ConfigService) {
    this.logsDirectory =
      this.configService.get<string>('WEBHOOK_LOG_DIRECTORY') ??
      join(process.cwd(), 'logs', 'webhooks');
    this.retentionDays = Number(
      this.configService.get<string | number>('WEBHOOK_LOG_RETENTION_DAYS') ??
        '15',
    );

    this.queueSizeGauge = this.getOrCreateGauge(
      'gateway_webhook_log_queue_size',
      'Current queued webhook log writes',
    );
    this.writeFailuresCounter = this.getOrCreateCounter(
      'gateway_webhook_log_write_failures_total',
      'Total webhook file log write failures',
    );
    this.filesPurgedCounter = this.getOrCreateCounter(
      'gateway_webhook_log_files_purged_total',
      'Total purged webhook log files',
    );
  }

  /**
   * Inicializa diretorio de logs e agenda rotina de limpeza periodica.
   */
  async onModuleInit(): Promise<void> {
    await mkdir(this.logsDirectory, { recursive: true });
    await this.purgeExpiredFiles();
    this.purgeInterval = setInterval(
      () => {
        this.purgeExpiredFiles().catch((err: unknown) =>
          this.logger.error(
            'purgeExpiredFiles failed',
            (err as Error)?.stack ?? String(err),
          ),
        );
      },
      60 * 60 * 1000,
    );
  }

  /**
   * Finaliza o service limpando intervalos e descarregando fila pendente.
   */
  async onModuleDestroy(): Promise<void> {
    if (this.purgeInterval) {
      clearInterval(this.purgeInterval);
      this.purgeInterval = null;
    }

    await this.flushQueue();
  }

  /**
   * Enfileira um payload de webhook para escrita em arquivo sem bloquear o fluxo.
   * Campos sensíveis (tokens, chaves de API) são mascarados antes da persistência.
   */
  logWebhook(payload: Record<string, unknown>): void {
    const line = this.serializeLine(this.maskSensitiveFields(payload));
    const targetFile = join(
      this.logsDirectory,
      `webhook-${this.currentDateSlug()}.log`,
    );

    this.writeQueue.push({ line, targetFile });
    this.queueSizeGauge.set(this.writeQueue.length);

    if (!this.isFlushing) {
      queueMicrotask(() => {
        void this.flushQueue();
      });
    }
  }

  /**
   * Retorna cópia rasa do payload com campos sensíveis substituídos por '***'.
   *
   * @param payload - Payload original do webhook
   * @returns Payload seguro para persistência em log
   */
  private maskSensitiveFields(
    payload: Record<string, unknown>,
  ): Record<string, unknown> {
    const SENSITIVE_KEYS = new Set([
      'instance_webhook_token',
      'token',
      'apiKey',
      'api_key',
      'webhook_token',
      'secret',
    ]);

    const masked: Record<string, unknown> = { ...payload };
    for (const key of SENSITIVE_KEYS) {
      if (masked[key] !== undefined && masked[key] !== null) {
        masked[key] = '***';
      }
    }
    return masked;
  }

  /**
   * Descarrega todos os itens pendentes na fila de escrita de forma sequencial.
   */
  private async flushQueue(): Promise<void> {
    if (this.isFlushing) {
      return;
    }

    this.isFlushing = true;

    try {
      while (this.writeQueue.length > 0) {
        const entry = this.writeQueue.shift();
        this.queueSizeGauge.set(this.writeQueue.length);
        if (!entry) {
          continue;
        }

        await this.appendSafely(entry.targetFile, entry.line);
      }
    } finally {
      this.isFlushing = false;
      this.queueSizeGauge.set(this.writeQueue.length);
    }
  }

  /**
   * Tenta escrever no arquivo de destino e redireciona para fallback em caso de falha.
   *
   * @param targetFile - Caminho do arquivo de destino
   * @param line - Linha serializada a ser gravada
   */
  private async appendSafely(targetFile: string, line: string): Promise<void> {
    try {
      await appendFile(targetFile, line, { encoding: 'utf-8' });
    } catch (error) {
      this.writeFailuresCounter.inc();
      this.logger.error(
        `Failed to append webhook log: ${(error as Error).message}`,
      );
      await this.writeToFallbackFile(line);
    }
  }

  /**
   * Grava a linha no arquivo de fallback ignorando erros.
   *
   * @param line - Linha serializada a ser gravada no fallback
   */
  private async writeToFallbackFile(line: string): Promise<void> {
    const fallbackFile = join(this.logsDirectory, 'webhook-fallback.log');

    try {
      await appendFile(fallbackFile, line, { encoding: 'utf-8' });
    } catch {
      // Ignore fallback failures to avoid blocking webhook flow
    }
  }

  /**
   * Remove arquivos de log com data de modificacao alem do periodo de retencao.
   */
  private async purgeExpiredFiles(): Promise<void> {
    try {
      const now = Date.now();
      const retentionMs = this.retentionDays * 24 * 60 * 60 * 1000;
      const files = await readdir(this.logsDirectory);
      let purgedCount = 0;

      for (const file of files) {
        if (!file.startsWith('webhook-') || !file.endsWith('.log')) {
          continue;
        }

        const fullPath = join(this.logsDirectory, file);
        const fileStat = await stat(fullPath);
        if (now - fileStat.mtimeMs <= retentionMs) {
          continue;
        }

        await unlink(fullPath);
        purgedCount += 1;
      }

      if (purgedCount > 0) {
        this.filesPurgedCounter.inc(purgedCount);
      }
    } catch (error) {
      this.logger.warn(`Webhook log purge failed: ${(error as Error).message}`);
    }
  }

  /**
   * Retorna o slug de data atual no formato YYYY-MM-DD.
   *
   * @returns String de data no formato ISO truncado
   */
  private currentDateSlug(): string {
    return new Date().toISOString().slice(0, 10);
  }

  /**
   * Serializa o payload do webhook em uma linha de log com timestamp.
   *
   * @param payload - Payload a ser serializado
   * @returns Linha formatada com timestamp e JSON terminando em newline
   */
  private serializeLine(payload: Record<string, unknown>): string {
    const serializedPayload = JSON.stringify(payload);
    return `${new Date().toISOString()} ${serializedPayload}\n`;
  }

  /**
   * Retorna um Gauge existente ou cria um novo evitando duplicatas de registro.
   *
   * @param name - Nome da metrica
   * @param help - Descricao da metrica
   * @returns Instancia do Gauge
   */
  private getOrCreateGauge(name: string, help: string): Gauge<string> {
    const existing = register.getSingleMetric(name);
    if (existing instanceof Gauge) {
      return existing;
    }

    return new Gauge({
      name,
      help,
      registers: [register],
    });
  }

  /**
   * Retorna um Counter existente ou cria um novo evitando duplicatas de registro.
   *
   * @param name - Nome da metrica
   * @param help - Descricao da metrica
   * @returns Instancia do Counter
   */
  private getOrCreateCounter(name: string, help: string): Counter<string> {
    const existing = register.getSingleMetric(name);
    if (existing instanceof Counter) {
      return existing;
    }

    return new Counter({
      name,
      help,
      registers: [register],
    });
  }

  /**
   * Cria arquivo de log de teste para cenarios de validacao automatizada.
   */
  async seedLogForTesting(date: string, payload: string): Promise<void> {
    await mkdir(this.logsDirectory, { recursive: true });
    const target = join(this.logsDirectory, `webhook-${date}.log`);
    await writeFile(target, payload, { encoding: 'utf-8' });
  }

  /**
   * Le o conteudo do arquivo de log de teste correspondente a data informada.
   */
  async readLogForTesting(date: string): Promise<string> {
    const target = join(this.logsDirectory, `webhook-${date}.log`);
    return readFile(target, { encoding: 'utf-8' });
  }
}
