import { Injectable, LoggerService as NestLoggerService } from '@nestjs/common';
import { AsyncLocalStorage } from 'node:async_hooks';
import { StructuredLogEntry } from '../models/logger.model';

export type { StructuredLogEntry };

/** Shape of the trace store propagated via AsyncLocalStorage. */
interface TraceStore {
  traceId: string;
  spanId: string;
}

/**
 * Serviço de logging JSON estruturado do gateway.
 *
 * Usa AsyncLocalStorage para propagar traceId/spanId automaticamente
 * em toda a cadeia assíncrona de uma requisição, sem precisar
 * passar o ID manualmente entre camadas.
 */
@Injectable()
export class StructuredLoggerService implements NestLoggerService {
  private static readonly SERVICE_NAME = 'telegram-gateway';
  private static readonly asyncStore = new AsyncLocalStorage<TraceStore>();

  private context?: string;

  /** Regex patterns for sensitive data masking. */
  private static readonly SENSITIVE_PATTERNS: ReadonlyArray<{
    pattern: RegExp;
    replacement: string;
  }> = [
    // Telegram bot tokens: "123456789:ABCdef..." → "123456789:***"
    { pattern: /(\d{8,10}):[A-Za-z0-9_-]{30,}/g, replacement: '$1:***' },
    // Bearer tokens in headers
    { pattern: /(Bearer\s+)[^\s]{8,}/gi, replacement: '$1***' },
    // Generic API keys / secrets (key=VALUE or key="VALUE")
    // Must come AFTER more specific patterns to avoid greedy overlap
    {
      pattern:
        /(api[_-]?key|secret|password)\s*[=:]\s*["']?[^\s"',]{8,}["']?/gi,
      replacement: '$1=***',
    },
  ];

  // ─── Static helpers for AsyncLocalStorage ───────────────────────────

  /**
   * Execute `fn` within a new trace context.
   * Call from interceptor/middleware at request start.
   */
  static runWithTrace<T>(traceId: string, spanId: string, fn: () => T): T {
    return this.asyncStore.run({ traceId, spanId }, fn);
  }

  /** Retrieve the current traceId from the async context (if any). */
  static getTraceId(): string | undefined {
    return this.asyncStore.getStore()?.traceId;
  }

  /** Retrieve the current spanId from the async context (if any). */
  static getSpanId(): string | undefined {
    return this.asyncStore.getStore()?.spanId;
  }

  // ─── Instance API (NestLoggerService) ───────────────────────────────

  setContext(context: string): void {
    this.context = context;
  }

  log(message: unknown, ...optionalParams: unknown[]): void {
    this.writeLog('info', message, optionalParams);
  }

  error(message: unknown, ...optionalParams: unknown[]): void {
    this.writeLog('error', message, optionalParams);
  }

  warn(message: unknown, ...optionalParams: unknown[]): void {
    this.writeLog('warn', message, optionalParams);
  }

  debug(message: unknown, ...optionalParams: unknown[]): void {
    this.writeLog('debug', message, optionalParams);
  }

  verbose(message: unknown, ...optionalParams: unknown[]): void {
    this.writeLog('verbose', message, optionalParams);
  }

  fatal(message: unknown, ...optionalParams: unknown[]): void {
    this.writeLog('fatal', message, optionalParams);
  }

  // ─── Private ────────────────────────────────────────────────────────

  private _writing = false;

  private writeLog(
    level: string,
    message: unknown,
    optionalParams: unknown[],
  ): void {
    // Guard against re-entrancy: NestJS Logger delegates back to this service
    // when useLogger() is active, causing infinite recursion without this check.
    if (this._writing) return;
    this._writing = true;
    try {
      const entry = this.buildLogEntry(level, message, optionalParams);
      process.stdout.write(JSON.stringify(entry) + '\n');
    } finally {
      this._writing = false;
    }
  }

  private buildLogEntry(
    level: string,
    message: unknown,
    optionalParams: unknown[],
  ): StructuredLogEntry {
    const store = StructuredLoggerService.asyncStore.getStore();

    const entry: StructuredLogEntry = {
      timestamp: new Date().toISOString(),
      level,
      service: StructuredLoggerService.SERVICE_NAME,
      traceId: store?.traceId ?? 'no-trace',
      spanId: store?.spanId ?? 'no-span',
      message: StructuredLoggerService.maskSensitiveData(
        this.formatMessage(message),
      ),
    };

    if (this.context) {
      entry.context = this.context;
    }

    // Extract context / extra fields from optional params
    if (optionalParams.length > 0) {
      const lastParam = optionalParams[optionalParams.length - 1];

      if (typeof lastParam === 'string') {
        entry.context = lastParam;
      } else if (typeof lastParam === 'object' && lastParam !== null) {
        Object.assign(entry, lastParam);
      }
    }

    // Attach stack trace for errors
    if (message instanceof Error && message.stack) {
      entry.stack = message.stack;
    } else if (
      level === 'error' &&
      optionalParams.length > 0 &&
      typeof optionalParams[0] === 'string'
    ) {
      entry.stack = optionalParams[0];
    }

    return entry;
  }

  private formatMessage(message: unknown): string {
    if (typeof message === 'string') {
      return message;
    }

    if (message instanceof Error) {
      return message.message;
    }

    try {
      return JSON.stringify(message);
    } catch {
      return String(message);
    }
  }

  /**
   * Mask sensitive data in log messages (tokens, API keys, secrets).
   */
  static maskSensitiveData(text: string): string {
    let masked = text;
    for (const { pattern, replacement } of this.SENSITIVE_PATTERNS) {
      // Reset lastIndex for global regexes
      pattern.lastIndex = 0;
      masked = masked.replace(pattern, replacement);
    }
    return masked;
  }
}
