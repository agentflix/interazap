/**
 * Modelos de infraestrutura de banco de dados do gateway.
 * Define tipos utilizados pelo serviço de conexão com o PostgreSQL.
 */

/**
 * Resultado de uma query ao banco de dados.
 */
export type DatabaseQueryResult<T> = {
  /** Linhas retornadas pela query */
  rows: T[];
  /** Número de linhas afetadas/retornadas */
  rowCount: number | null;
};

/**
 * Interface que representa um executor de queries compatível com pg.Pool.
 * Permite injetar implementações alternativas (ex: pool customizado ou mock em testes).
 */
export type QueryExecutor = {
  /**
   * Executa uma query SQL com parâmetros opcionais.
   *
   * @param text - Texto da query SQL parametrizada
   * @param params - Array de parâmetros para a query
   * @returns Resultado com linhas e contagem
   */
  query<T>(
    text: string,
    params?: ReadonlyArray<unknown>,
  ): Promise<DatabaseQueryResult<T>>;
  /**
   * Encerra o pool de conexões.
   */
  end(): Promise<void>;
  /**
   * Registra um listener de eventos no pool.
   *
   * @param event - Nome do evento
   * @param listener - Callback invocado no evento
   */
  on(event: 'error', listener: (err: Error) => void): void;
};
