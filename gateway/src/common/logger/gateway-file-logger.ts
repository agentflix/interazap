import { appendFile, existsSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';

/**
 * Logger de arquivo para persistência de logs de debug do gateway.
 *
 * Contexto: utilizado para inspecionar tráfego WebSocket e eventos do gateway
 * sem depender apenas do console. Escreve em `logs/gateway-events.log` de forma assíncrona.
 */
export class GatewayFileLogger {
  private static readonly logFilePath = join(
    process.cwd(),
    'logs',
    'gateway-events.log',
  );

  private static initialized = false;
  private static writeQueue: Promise<void> = Promise.resolve();
  private static readonly maxSerializedPayloadLength = 4_096;

  /**
   * @param context - Rótulo de contexto de logging (ex.: nome do controller ou serviço).
   */
  constructor(private readonly context: string) {
    GatewayFileLogger.ensureLogFile();
  }

  /**
   * Escreve uma entrada de nível INFO no arquivo de log.
   *
   * @param message - Mensagem legível da entrada.
   * @param payload - Objeto serializável opcional anexado à linha.
   */
  info(message: string, payload?: unknown): void {
    this.write('INFO', message, payload);
  }

  /**
   * Escreve uma entrada de nível DEBUG no arquivo de log.
   *
   * @param message - Mensagem legível da entrada.
   * @param payload - Objeto serializável opcional anexado à linha.
   */
  debug(message: string, payload?: unknown): void {
    this.write('DEBUG', message, payload);
  }

  /**
   * Escreve uma entrada de nível ERROR no arquivo de log.
   *
   * @param message - Mensagem legível da entrada.
   * @param payload - Objeto serializável opcional anexado à linha.
   */
  error(message: string, payload?: unknown): void {
    this.write('ERROR', message, payload);
  }

  /** Enfileira uma linha formatada para ser escrita de forma assíncrona no arquivo de log. */
  private write(level: string, message: string, payload?: unknown): void {
    GatewayFileLogger.appendLine(level, this.context, message, payload);
  }

  /**
   * Formata e enfileira uma única linha de log com timestamp para escrita em disco.
   *
   * @param level - Nível do log (INFO, DEBUG, ERROR).
   * @param context - Rótulo de contexto do log.
   * @param message - Texto da mensagem do log.
   * @param payload - Payload opcional para serializar e anexar.
   */
  private static appendLine(
    level: string,
    context: string,
    message: string,
    payload?: unknown,
  ): void {
    const serializedPayload =
      payload === undefined ? '' : ` ${GatewayFileLogger.serialize(payload)}`;
    const line = `${new Date().toISOString()} [${level}] [${context}] ${message}${serializedPayload}\n`;

    GatewayFileLogger.writeQueue = GatewayFileLogger.writeQueue
      .then(
        () =>
          new Promise<void>((resolve) => {
            appendFile(GatewayFileLogger.logFilePath, line, () => resolve());
          }),
      )
      .catch(() => undefined);
  }

  /**
   * Serializa um payload para JSON, truncando se exceder o tamanho máximo permitido.
   *
   * @param payload - Valor a ser serializado.
   * @returns String JSON ou um placeholder quando a serialização falha ou o payload é muito grande.
   */
  private static serialize(payload: unknown): string {
    try {
      const serialized = JSON.stringify(payload);
      if (serialized.length <= GatewayFileLogger.maxSerializedPayloadLength) {
        return serialized;
      }

      return `${serialized.slice(0, GatewayFileLogger.maxSerializedPayloadLength)}...[truncated]`;
    } catch {
      return '[unserializable-payload]';
    }
  }

  /**
   * Cria o diretório de logs e marca o logger como inicializado.
   * Idempotente — chamadas subsequentes retornam imediatamente.
   */
  private static ensureLogFile(): void {
    if (GatewayFileLogger.initialized) {
      return;
    }

    const directory = dirname(GatewayFileLogger.logFilePath);
    if (!existsSync(directory)) {
      mkdirSync(directory, { recursive: true });
    }

    GatewayFileLogger.initialized = true;
  }
}
