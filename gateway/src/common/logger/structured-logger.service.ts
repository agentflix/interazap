import {
  Injectable,
  Logger,
  LoggerService as NestLoggerService,
} from '@nestjs/common';
import { StructuredLogEntry } from '../models/logger.model';

export type { StructuredLogEntry };

/**
 * Serviço de logging JSON estruturado do gateway.
 *
 * Fornece logging estruturado com suporte a trace ID para ambientes de produção.
 */
@Injectable()
export class StructuredLoggerService implements NestLoggerService {
  private readonly logger = new Logger(StructuredLoggerService.name);
  private context?: string;
  private traceId?: string;

  /**
   * Define o contexto (módulo/classe) associado a este logger.
   *
   * @param context - Nome do contexto de logging
   */
  setContext(context: string): void {
    this.context = context;
  }

  /**
   * Define o trace ID para rastreamento distribuído.
   *
   * @param traceId - Identificador único de rastreamento
   */
  setTraceId(traceId: string): void {
    this.traceId = traceId;
  }

  /**
   * Emite log de nível `info`.
   *
   * @param message - Mensagem do log
   * @param optionalParams - Parâmetros opcionais (contexto, metadata)
   */
  log(message: unknown, ...optionalParams: unknown[]): void {
    this.writeLog('info', message, optionalParams);
  }

  /**
   * Emite log de nível `error`.
   *
   * @param message - Mensagem do erro
   * @param optionalParams - Parâmetros opcionais (stack trace, contexto)
   */
  error(message: unknown, ...optionalParams: unknown[]): void {
    this.writeLog('error', message, optionalParams);
  }

  /**
   * Emite log de nível `warn`.
   *
   * @param message - Mensagem de aviso
   * @param optionalParams - Parâmetros opcionais
   */
  warn(message: unknown, ...optionalParams: unknown[]): void {
    this.writeLog('warn', message, optionalParams);
  }

  /**
   * Emite log de nível `debug`.
   *
   * @param message - Mensagem de debug
   * @param optionalParams - Parâmetros opcionais
   */
  debug(message: unknown, ...optionalParams: unknown[]): void {
    this.writeLog('debug', message, optionalParams);
  }

  /**
   * Emite log de nível `verbose`.
   *
   * @param message - Mensagem verbose
   * @param optionalParams - Parâmetros opcionais
   */
  verbose(message: unknown, ...optionalParams: unknown[]): void {
    this.writeLog('verbose', message, optionalParams);
  }

  /**
   * Emite log de nível `fatal`.
   *
   * @param message - Mensagem de falha crítica
   * @param optionalParams - Parâmetros opcionais
   */
  fatal(message: unknown, ...optionalParams: unknown[]): void {
    this.writeLog('fatal', message, optionalParams);
  }

  private writeLog(
    level: string,
    message: unknown,
    optionalParams: unknown[],
  ): void {
    const entry = this.buildLogEntry(level, message, optionalParams);

    // In production, output JSON
    if (process.env.NODE_ENV === 'production') {
      console.log(JSON.stringify(entry));
    } else {
      // In development, use NestJS default logger
      switch (level) {
        case 'error':
          this.logger.error(message, ...optionalParams);
          break;
        case 'warn':
          this.logger.warn(message, ...optionalParams);
          break;
        case 'debug':
          this.logger.debug?.(message, ...optionalParams);
          break;
        case 'verbose':
          this.logger.verbose?.(message, ...optionalParams);
          break;
        default:
          this.logger.log(message, ...optionalParams);
      }
    }
  }

  private buildLogEntry(
    level: string,
    message: unknown,
    optionalParams: unknown[],
  ): StructuredLogEntry {
    const entry: StructuredLogEntry = {
      timestamp: new Date().toISOString(),
      level,
      message: this.formatMessage(message),
    };

    if (this.context) {
      entry.context = this.context;
    }

    if (this.traceId) {
      entry.traceId = this.traceId;
    }

    // Extract context from optional params
    if (optionalParams.length > 0) {
      const lastParam = optionalParams[optionalParams.length - 1];

      if (typeof lastParam === 'string') {
        entry.context = lastParam;
      } else if (typeof lastParam === 'object' && lastParam !== null) {
        Object.assign(entry, lastParam);
      }
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
}
