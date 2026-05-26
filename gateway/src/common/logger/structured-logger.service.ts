import { Injectable, LoggerService as NestLoggerService } from '@nestjs/common';
import { AsyncLocalStorage } from 'node:async_hooks';
import { StructuredLogEntry } from '../models/logger.model';

export type { StructuredLogEntry };

/**
 * Representa o contexto de rastreamento propagado via AsyncLocalStorage por requisição.
 */
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

  /** Padrões regex para mascaramento de dados sensíveis em mensagens de log. */
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

  // ─── Helpers estáticos para AsyncLocalStorage ────────────────────────

  /**
   * Executa `fn` dentro de um novo contexto de rastreamento.
   * Deve ser chamado pelo interceptor/middleware no início de cada requisição.
   *
   * @param traceId - ID de rastreamento distribuído
   * @param spanId - ID do span dentro do trace
   * @param fn - Função a ser executada no contexto de rastreamento
   * @returns Resultado da execução de `fn`
   */
  static runWithTrace<T>(traceId: string, spanId: string, fn: () => T): T {
    return this.asyncStore.run({ traceId, spanId }, fn);
  }

  /** Obtém o traceId do contexto assíncrono atual, se disponível. */
  static getTraceId(): string | undefined {
    return this.asyncStore.getStore()?.traceId;
  }

  /** Obtém o spanId do contexto assíncrono atual, se disponível. */
  static getSpanId(): string | undefined {
    return this.asyncStore.getStore()?.spanId;
  }

  // ─── API de instância (NestLoggerService) ────────────────────────────

  /** Define o contexto (módulo/classe) que será incluído em todas as entradas de log desta instância. */
  setContext(context: string): void {
    this.context = context;
  }

  /** Registra uma mensagem no nível INFO. */
  log(message: unknown, ...optionalParams: unknown[]): void {
    this.writeLog('info', message, optionalParams);
  }

  /** Registra uma mensagem no nível ERROR. */
  error(message: unknown, ...optionalParams: unknown[]): void {
    this.writeLog('error', message, optionalParams);
  }

  /** Registra uma mensagem no nível WARN. */
  warn(message: unknown, ...optionalParams: unknown[]): void {
    this.writeLog('warn', message, optionalParams);
  }

  /** Registra uma mensagem no nível DEBUG. */
  debug(message: unknown, ...optionalParams: unknown[]): void {
    this.writeLog('debug', message, optionalParams);
  }

  /** Registra uma mensagem no nível VERBOSE. */
  verbose(message: unknown, ...optionalParams: unknown[]): void {
    this.writeLog('verbose', message, optionalParams);
  }

  /** Registra uma mensagem no nível FATAL. */
  fatal(message: unknown, ...optionalParams: unknown[]): void {
    this.writeLog('fatal', message, optionalParams);
  }

  // ─── Private ────────────────────────────────────────────────────────

  private _writing = false;

  /**
   * Serializa e emite uma entrada de log JSON para stdout, protegendo contra re-entrada recursiva.
   *
   * @param level - Nível do log (info, warn, error, debug, verbose, fatal)
   * @param message - Mensagem ou objeto a registrar
   * @param optionalParams - Parâmetros opcionais (contexto, campos extras ou stack trace)
   */
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

  /**
   * Constrói o objeto StructuredLogEntry enriquecido com traceId, spanId e contexto.
   *
   * @param level - Nível do log
   * @param message - Mensagem ou objeto a registrar
   * @param optionalParams - Parâmetros opcionais (contexto string ou objeto com campos extras)
   * @returns Entrada de log estruturada pronta para serialização JSON
   */
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

  /**
   * Converte um valor desconhecido em string para uso como mensagem de log.
   * Erros são representados pela propriedade `message`; demais objetos são serializados em JSON.
   *
   * @param message - Valor a ser formatado
   * @returns Representação string do valor
   */
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
   * Mascara dados sensíveis em mensagens de log (tokens, API keys, secrets).
   *
   * @param text - Texto da mensagem de log a ser mascarado
   * @returns Texto com dados sensíveis substituídos por '***'
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
