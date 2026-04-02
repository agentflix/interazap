import { appendFile, existsSync, mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';

/**
 * Persist debug logs to a file so we can inspect WebSocket traffic without attaching to the console.
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
   * @param context - Logging context label (e.g. controller or service name).
   */
  constructor(private readonly context: string) {
    GatewayFileLogger.ensureLogFile();
  }

  /**
   * Writes an INFO-level entry to the log file.
   *
   * @param message - Human-readable message.
   * @param payload - Optional serializable object appended to the line.
   */
  info(message: string, payload?: unknown): void {
    this.write('INFO', message, payload);
  }

  /**
   * Writes a DEBUG-level entry to the log file.
   *
   * @param message - Human-readable message.
   * @param payload - Optional serializable object appended to the line.
   */
  debug(message: string, payload?: unknown): void {
    this.write('DEBUG', message, payload);
  }

  /**
   * Writes an ERROR-level entry to the log file.
   *
   * @param message - Human-readable message.
   * @param payload - Optional serializable object appended to the line.
   */
  error(message: string, payload?: unknown): void {
    this.write('ERROR', message, payload);
  }

  /** Queues a formatted line to be written asynchronously to the log file. */
  private write(level: string, message: string, payload?: unknown): void {
    GatewayFileLogger.appendLine(level, this.context, message, payload);
  }

  /**
   * Formats and queues a single timestamped log line to disk.
   *
   * @param level - Log level string (INFO, DEBUG, ERROR).
   * @param context - Logging context label.
   * @param message - Log message text.
   * @param payload - Optional payload to serialize and append.
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
   * Serializes a payload to JSON, truncating it if it exceeds maxSerializedPayloadLength.
   *
   * @param payload - Value to serialize.
   * @returns JSON string or a placeholder when serialization fails or the payload is too large.
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
   * Creates the logs directory and marks the logger as initialized.
   * Idempotent — subsequent calls return immediately.
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
