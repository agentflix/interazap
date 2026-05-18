import { Injectable, computed, inject, signal } from '@angular/core';
import { Preferences } from '@capacitor/preferences';
import { PlatformService } from './platform.service';
import type { OfflineQueuedMessage } from '@core/models/offline-queue.model';
export type { OfflineQueuedMessage } from '@core/models/offline-queue.model';


const OFFLINE_QUEUE_STORAGE_KEY = 'chat_offline_queue_v1';


function isRecord(value: object | null): value is Record<string, string | number> {
  return value !== null;
}

function isOfflineQueuedMessage(value: object | null): value is OfflineQueuedMessage {
  if (!isRecord(value)) {
    return false;
  }

  return (
    typeof value['id'] === 'string' &&
    typeof value['calledId'] === 'string' &&
    typeof value['content'] === 'string' &&
    value['type'] === 'text' &&
    typeof value['clientMessageId'] === 'string' &&
    typeof value['createdAt'] === 'string' &&
    typeof value['attempts'] === 'number'
  );
}

/**
 * Fila offline persistida para mensagens do chat (FIFO).
 *
 * Em mobile usa `@capacitor/preferences`; em web usa `localStorage` para
 * manter compatibilidade com testes e ambiente de desenvolvimento.
 */
@Injectable({ providedIn: 'root' })
export class OfflineQueueService {
  private readonly platform = inject(PlatformService);

  private readonly queueSignal = signal<OfflineQueuedMessage[]>([]);
  readonly queue = this.queueSignal.asReadonly();
  readonly size = computed(() => this.queueSignal().length);

  private hydrated = false;

  async hydrate(): Promise<void> {
    if (this.hydrated) {
      return;
    }

    const raw = await this.readStoredQueue();
    this.queueSignal.set(this.parseQueue(raw));
    this.hydrated = true;
  }

  getSnapshot(): OfflineQueuedMessage[] {
    return this.queueSignal();
  }

  pendingCountForTicket(calledId: string | null): number {
    if (calledId === null || calledId.trim() === '') {
      return 0;
    }

    return this.queueSignal().filter((item) => item.calledId === calledId).length;
  }

  async enqueue(item: OfflineQueuedMessage): Promise<void> {
    await this.ensureHydrated();
    const next = [...this.queueSignal(), item];
    await this.persistQueue(next);
    this.queueSignal.set(next);
  }

  async remove(itemId: string): Promise<void> {
    await this.ensureHydrated();
    const next = this.queueSignal().filter((item) => item.id !== itemId);
    await this.persistQueue(next);
    this.queueSignal.set(next);
  }

  async incrementAttempts(itemId: string): Promise<void> {
    await this.ensureHydrated();
    const next = this.queueSignal().map((item) =>
      item.id === itemId ? { ...item, attempts: item.attempts + 1 } : item,
    );
    await this.persistQueue(next);
    this.queueSignal.set(next);
  }

  async clear(): Promise<void> {
    await this.persistQueue([]);
    this.queueSignal.set([]);
    this.hydrated = true;
  }

  private async ensureHydrated(): Promise<void> {
    if (!this.hydrated) {
      await this.hydrate();
    }
  }

  private parseQueue(raw: string | null): OfflineQueuedMessage[] {
    if (raw === null || raw.trim() === '') {
      return [];
    }

    try {
      const parsed = JSON.parse(raw);
      if (!Array.isArray(parsed)) {
        return [];
      }

      return parsed
        .filter((entry) => typeof entry === 'object')
        .filter((entry) => isOfflineQueuedMessage(entry))
        .map((entry) => ({ ...entry }));
    } catch {
      return [];
    }
  }

  private async persistQueue(queue: OfflineQueuedMessage[]): Promise<void> {
    const serialized = JSON.stringify(queue);
    if (this.platform.isMobile) {
      await this.writeToNativeStorage(serialized);
      return;
    }

    this.writeToWebStorage(serialized);
  }

  private async readStoredQueue(): Promise<string | null> {
    if (this.platform.isMobile) {
      return this.readFromNativeStorage();
    }

    return this.readFromWebStorage();
  }

  private async readFromNativeStorage(): Promise<string | null> {
    const { value } = await Preferences.get({ key: OFFLINE_QUEUE_STORAGE_KEY });
    return value ?? null;
  }

  private async writeToNativeStorage(value: string): Promise<void> {
    await Preferences.set({ key: OFFLINE_QUEUE_STORAGE_KEY, value });
  }

  private readFromWebStorage(): string | null {
    if (typeof window === 'undefined') {
      return null;
    }

    return localStorage.getItem(OFFLINE_QUEUE_STORAGE_KEY);
  }

  private writeToWebStorage(value: string): void {
    if (typeof window === 'undefined') {
      return;
    }

    localStorage.setItem(OFFLINE_QUEUE_STORAGE_KEY, value);
  }
}
