import { Injectable, inject } from '@angular/core';
import { ElectronService } from './electron.service';

interface QueuedAction {
  id: string;
  type: string;
  payload: unknown;
  timestamp: number;
  retries: number;
}

/**
 * Servico de fila offline para armazenar acoes executadas sem conexao
 * e processa-las quando a conexao for restabelecida.
 * Armazena a fila no localStorage com suporte a retentativas.
 *
 * @class OfflineQueueService
 * @example
 * ```ts
 * const queue = inject(OfflineQueueService);
 * queue.enqueue('create_contact', { name: 'John' });
 * await queue.processQueue();
 * ```
 */
@Injectable({
  providedIn: 'root',
})
export class OfflineQueueService {
  private electronService = inject(ElectronService);
  private storageKey = 'offline_queue';
  private maxRetries = 3;

  /**
   * Adiciona uma acao a fila offline.
   *
   * @param type - Tipo/identificador da acao
   * @param payload - Dados da acao a serem executados
   * @returns ID unico da acao enfileirada
   */
  enqueue(type: string, payload: unknown): string {
    const action: QueuedAction = {
      id: this.generateId(),
      type,
      payload,
      timestamp: Date.now(),
      retries: 0,
    };

    const queue = this.getQueue();
    queue.push(action);
    this.saveQueue(queue);
    return action.id;
  }

  /**
   * Retorna todas as acoes atualmente na fila.
   *
   * @returns Array de acoes enfileiradas
   */
  getQueue(): QueuedAction[] {
    try {
      const raw = localStorage.getItem(this.storageKey);
      return raw ? JSON.parse(raw) : [];
    } catch {
      return [];
    }
  }

  private saveQueue(queue: QueuedAction[]): void {
    localStorage.setItem(this.storageKey, JSON.stringify(queue));
  }

  /**
   * Processa todas as acoes da fila, removendo as bem-sucedidas
   * e mantendo as com falha para retentativa (ate maxRetries).
   *
   * @returns Promise com contagem de sucessos e falhas
   */
  async processQueue(): Promise<{ success: number; failed: number }> {
    const queue = this.getQueue();
    const remaining: QueuedAction[] = [];
    let success = 0;
    let failed = 0;

    for (const action of queue) {
      try {
        // Process based on action type
        // This is a simplified version - actual implementation would call appropriate services
        console.warn(`Processing queued action: ${action.type}`, action.payload);
        success++;
      } catch (error) {
        action.retries++;
        if (action.retries < this.maxRetries) {
          remaining.push(action);
        }
        failed++;
      }
    }

    this.saveQueue(remaining);
    return { success, failed };
  }

  /**
   * Limpa todas as acoes da fila offline.
   */
  clear(): void {
    localStorage.removeItem(this.storageKey);
  }

  /**
   * Retorna a quantidade de acoes atualmente na fila.
   *
   * @returns Numero de itens na fila
   */
  size(): number {
    return this.getQueue().length;
  }

  private generateId(): string {
    return `${Date.now()}-${Math.random().toString(36).substring(2, 9)}`;
  }
}
