export interface PendingUpdate<T = unknown> {
  /** Identificador único da operação */
  readonly id: string;
  /** Tipo da operação (contact, deal, message, etc.) */
  readonly type: string;
  /** ID da entidade sendo atualizada */
  readonly entityId: string | number;
  /** Estado anterior para rollback */
  readonly previousState: T;
  /** Novo estado aplicado otimisticamente */
  readonly optimisticState: T;
  /** Timestamp de criação */
  readonly createdAt: number;
  /** Status da operação */
  readonly status: 'pending' | 'confirmed' | 'rolledback';
}

export type ApplyCallback<T> = (state: T) => void;

export type RollbackCallback<T> = (previousState: T) => void;

export interface OptimisticOptions<T> {
  /** Tipo da operação para agrupamento */
  type: string;
  /** ID da entidade sendo atualizada */
  entityId: string | number;
  /** Estado anterior para rollback */
  previousState: T;
  /** Novo estado otimístico */
  optimisticState: T;
  /** Callback para aplicar estado na UI */
  onApply: ApplyCallback<T>;
  /** Callback para rollback em caso de erro */
  onRollback: RollbackCallback<T>;
  /** Timeout em ms para auto-rollback (default: 10000) */
  timeout?: number;
}
