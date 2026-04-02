import { Injectable, Logger, OnModuleDestroy } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { Pool } from 'pg';
import { DatabaseQueryResult, QueryExecutor } from '../models/database.model';

export type { DatabaseQueryResult };

/**
 * Serviço de acesso ao banco de dados PostgreSQL do gateway.
 *
 * Gerencia um pool de conexões pg para execução eficiente de queries,
 * com configuração de timeout e tamanho máximo via variáveis de ambiente.
 */
@Injectable()
export class DatabaseService implements OnModuleDestroy {
  private readonly logger = new Logger(DatabaseService.name);
  private readonly pool: QueryExecutor;

  /**
   * Initializes the PostgreSQL connection pool from DATABASE_URL and
   * environment-based pool sizing settings (PG_POOL_MAX, *_TIMEOUT_MS).
   *
   * @param configService - NestJS ConfigService for environment variable access
   */
  constructor(private readonly configService: ConfigService) {
    const connectionString =
      this.configService.get<string>('DATABASE_URL') ??
      'postgres://interazap:secret@localhost:5432/interazap';

    this.pool = this.createPool(connectionString);

    this.pool.on('error', (err: Error) => {
      this.logger.error(`Database pool error: ${err.message}`, err.stack);
    });
  }

  /**
   * Gracefully closes all connections in the pool when the module is destroyed.
   */
  async onModuleDestroy(): Promise<void> {
    await this.pool.end();
  }

  /**
   * Executa uma query SQL no banco de dados.
   *
   * @param text - Texto da query SQL parametrizada
   * @param params - Parâmetros para a query (padrão: array vazio)
   * @returns Resultado com linhas e contagem de linhas afetadas
   */
  query<T = Record<string, unknown>>(
    text: string,
    params: ReadonlyArray<unknown> = [],
  ): Promise<DatabaseQueryResult<T>> {
    return this.pool.query<T>(text, params);
  }

  /**
   * Parses an environment variable as an integer and clamps it to [min, max].
   * Returns the fallback value when parsing fails or the result is non-finite.
   *
   * @param value   - Raw string value from the environment
   * @param fallback - Default value when parsing fails
   * @param min     - Minimum allowed value (inclusive)
   * @param max     - Maximum allowed value (inclusive)
   */
  private parseIntegerInRange(
    value: string | undefined,
    fallback: number,
    min: number,
    max: number,
  ): number {
    const parsed = Number(value);
    if (!Number.isFinite(parsed) || !Number.isInteger(parsed)) {
      return fallback;
    }

    return Math.min(max, Math.max(min, parsed));
  }

  /**
   * Creates and configures a pg Pool instance with environment-driven settings.
   *
   * @param connectionString - PostgreSQL connection string
   * @returns Configured QueryExecutor (pg Pool)
   */
  private createPool(connectionString: string): QueryExecutor {
    const maxPoolSize = this.parseIntegerInRange(
      this.configService.get<string>('PG_POOL_MAX'),
      25,
      20,
      30,
    );
    const connectionTimeoutMillis = this.parseIntegerInRange(
      this.configService.get<string>('PG_CONNECTION_TIMEOUT_MS'),
      3000,
      500,
      10000,
    );
    const idleTimeoutMillis = this.parseIntegerInRange(
      this.configService.get<string>('PG_IDLE_TIMEOUT_MS'),
      10000,
      1000,
      30000,
    );

    const PoolConstructor = Pool as unknown as {
      new (config: {
        connectionString: string;
        max: number;
        connectionTimeoutMillis: number;
        idleTimeoutMillis: number;
      }): QueryExecutor;
    };

    return new PoolConstructor({
      connectionString,
      max: maxPoolSize,
      connectionTimeoutMillis,
      idleTimeoutMillis,
    });
  }
}
