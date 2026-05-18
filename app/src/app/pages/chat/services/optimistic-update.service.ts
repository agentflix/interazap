import { Injectable, signal, computed } from '@angular/core';
import type { OptimisticOptions, PendingUpdate, RollbackCallback } from '@chat/models/optimistic-update.model';
export type { ApplyCallback, OptimisticOptions, PendingUpdate, RollbackCallback } from '@chat/models/optimistic-update.model';


/**
 * Representa uma operação de optimistic update pendente.
 */

/**
 * Callback para aplicar o estado otimístico na UI.
 */

/**
 * Callback para reverter ao estado anterior.
 */

/**
 * Opções para criar uma operação otimística.
 */

/**
 * Serviço para gerenciar optimistic updates com suporte a rollback automático.
 *
 * Permite aplicar atualizações de UI imediatamente enquanto aguarda confirmação
 * do servidor, revertendo automaticamente em caso de erro ou timeout.
 *
 * @example
 * ```typescript
 * const updateId = this.optimisticUpdate.apply({
 *   type: 'contact',
 *   entityId: contact.id,
 *   previousState: { name: 'João' },
 *   optimisticState: { name: 'João Silva' },
 *   onApply: (state) => this.contactSignal.set({ ...contact, ...state }),
 *   onRollback: (prev) => this.contactSignal.set({ ...contact, ...prev }),
 * });
 *
 * try {
 *   await this.contactService.update(contact.id, { name: 'João Silva' });
 *   this.optimisticUpdate.confirm(updateId);
 * } catch (error) {
 *   this.optimisticUpdate.rollback(updateId);
 * }
 * ```
 *
 * @class OptimisticUpdateService
 * @description Service para gerenciar optimistic updates com rollback.
 */
@Injectable({ providedIn: 'root' })
export class OptimisticUpdateService {
  /**
   * Mapa de operações pendentes indexadas por ID.
   * @private
   */
  private readonly pendingUpdates = signal<Map<string, PendingUpdate>>(new Map());

  /**
   * Mapa de callbacks de rollback indexados por ID.
   * @private
   */
  private readonly rollbackCallbacks = new Map<string, RollbackCallback<unknown>>();

  /**
   * Mapa de timeouts para auto-rollback.
   * @private
   */
  private readonly timeoutHandles = new Map<string, ReturnType<typeof setTimeout>>();

  /**
   * Número de operações pendentes.
   */
  readonly pendingCount = computed(() => this.pendingUpdates().size);

  /**
   * Indica se há operações pendentes.
   */
  readonly hasPending = computed(() => this.pendingUpdates().size > 0);

  /**
   * Lista de operações pendentes do tipo especificado.
   */
  getPendingByType(type: string): PendingUpdate[] {
    return Array.from(this.pendingUpdates().values()).filter((u) => u.type === type);
  }

  /**
   * Verifica se há operação pendente para uma entidade específica.
   */
  hasPendingForEntity(type: string, entityId: string | number): boolean {
    return Array.from(this.pendingUpdates().values()).some(
      (u) => u.type === type && u.entityId === entityId && u.status === 'pending',
    );
  }

  /**
   * Aplica uma atualização otimística e retorna o ID da operação.
   *
   * @param options Configurações da operação otimística
   * @returns ID único da operação para confirmar ou reverter
   */
  apply<T>(options: OptimisticOptions<T>): string {
    const id = this.generateId();
    const update: PendingUpdate<T> = {
      id,
      type: options.type,
      entityId: options.entityId,
      previousState: options.previousState,
      optimisticState: options.optimisticState,
      createdAt: Date.now(),
      status: 'pending',
    };

    // Armazena callback de rollback
    this.rollbackCallbacks.set(id, options.onRollback as RollbackCallback<unknown>);

    // Adiciona ao mapa de pendentes
    this.pendingUpdates.update((map) => {
      const newMap = new Map(map);
      newMap.set(id, update as PendingUpdate);
      return newMap;
    });

    // Aplica estado otimístico na UI
    options.onApply(options.optimisticState);

    // Configura timeout para auto-rollback
    const timeout = options.timeout ?? 10000;
    const handle = setTimeout(() => {
      this.rollback(id, 'Timeout: operação não confirmada');
    }, timeout);
    this.timeoutHandles.set(id, handle);

    return id;
  }

  /**
   * Confirma que a operação foi bem-sucedida no servidor.
   * Remove a operação da lista de pendentes.
   *
   * @param id ID da operação retornado por apply()
   */
  confirm(id: string): void {
    const update = this.pendingUpdates().get(id);
    if (!update || update.status !== 'pending') {
      return;
    }

    // Limpa timeout
    this.clearTimeout(id);

    // Atualiza status
    this.pendingUpdates.update((map) => {
      const newMap = new Map(map);
      newMap.delete(id);
      return newMap;
    });

    // Remove callback
    this.rollbackCallbacks.delete(id);
  }

  /**
   * Reverte a operação ao estado anterior.
   *
   * @param id ID da operação retornado por apply()
   * @param _reason Motivo do rollback (para logging)
   */
  rollback(id: string, _reason?: string): void {
    const update = this.pendingUpdates().get(id);
    if (!update || update.status !== 'pending') {
      return;
    }

    // Limpa timeout
    this.clearTimeout(id);

    // Executa callback de rollback
    const callback = this.rollbackCallbacks.get(id);
    if (callback) {
      callback(update.previousState);
    }

    // Atualiza status e remove
    this.pendingUpdates.update((map) => {
      const newMap = new Map(map);
      newMap.delete(id);
      return newMap;
    });

    // Remove callback
    this.rollbackCallbacks.delete(id);
  }

  /**
   * Reverte todas as operações pendentes de um tipo específico.
   *
   * @param type Tipo das operações a reverter
   */
  rollbackByType(type: string): void {
    const updates = this.getPendingByType(type);
    updates.forEach((update) => this.rollback(update.id, `Rollback by type: ${type}`));
  }

  /**
   * Reverte todas as operações pendentes para uma entidade específica.
   *
   * @param type Tipo da operação
   * @param entityId ID da entidade
   */
  rollbackByEntity(type: string, entityId: string | number): void {
    const updates = Array.from(this.pendingUpdates().values()).filter(
      (u) => u.type === type && u.entityId === entityId && u.status === 'pending',
    );
    updates.forEach((update) => this.rollback(update.id, `Rollback by entity: ${entityId}`));
  }

  /**
   * Limpa todas as operações pendentes (use com cuidado).
   */
  clear(): void {
    this.timeoutHandles.forEach((handle) => clearTimeout(handle));
    this.timeoutHandles.clear();
    this.rollbackCallbacks.clear();
    this.pendingUpdates.set(new Map());
  }

  /**
   * Gera um ID único para a operação.
   * @private
   */
  private generateId(): string {
    return `opt_${Date.now()}_${Math.random().toString(36).substring(2, 9)}`;
  }

  /**
   * Limpa o timeout de uma operação.
   * @private
   */
  private clearTimeout(id: string): void {
    const handle = this.timeoutHandles.get(id);
    if (handle) {
      clearTimeout(handle);
      this.timeoutHandles.delete(id);
    }
  }
}
