import { DestroyRef, inject, Injectable, signal } from '@angular/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { map } from 'rxjs/operators';
import { environment } from '@env/environment';

export interface ChatRoutingQueue {
  id: string;
  tenant_id: string;
  instance_id: string | null;
  name: string;
  is_enabled: boolean;
  strategy: 'round_robin' | 'least_busy' | 'skill_based';
  max_open_tickets_per_agent: number | null;
  agents: ChatRoutingQueueAgent[];
  created_at: string;
  updated_at: string;
}

export interface ChatRoutingQueueAgent {
  id: string;
  queue_id: string;
  user_id: string;
  position: number;
  last_assigned_at: string | null;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

interface SingleEnvelope<T> {
  data: T;
}

/**
 * Service for managing chat routing queues (global and per-channel).
 *
 * Consumes the backend API for FEAT-052 (Rodízio Automático de Atendimentos — Fase 1).
 * Uses signals for local state; each load triggers a fresh GET.
 */
@Injectable({ providedIn: 'root' })
export class ChatRoutingQueueService {
  private readonly http = inject(HttpClient);
  private readonly destroyRef = inject(DestroyRef);
  private readonly baseUrl = `${environment.apiUrl}/chat/routing-queue`;
  private readonly channelsBaseUrl = `${environment.apiUrl}/chat/channels`;

  readonly queue = signal<ChatRoutingQueue | null>(null);
  readonly agents = signal<ChatRoutingQueueAgent[]>([]);
  readonly loading = signal<boolean>(false);
  readonly error = signal<string | null>(null);

  /** Loads the global routing queue and updates local state. */
  loadGlobal(): void {
    this._setLoading(true);
    this.http
      .get<SingleEnvelope<ChatRoutingQueue>>(`${this.baseUrl}/global`)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const data = response.data;
          this.queue.set(data);
          this.agents.set(data.agents ?? []);
          this._setLoading(false);
          this.error.set(null);
        },
        error: (err: unknown) => {
          this._setLoading(false);
          if (err instanceof HttpErrorResponse && err.status === 404) {
            this.queue.set(null);
            this.agents.set([]);
            this.error.set(null);
          } else {
            this.error.set('Erro ao carregar fila global de roteamento.');
          }
        },
      });
  }

  /**
   * Loads the routing queue for a specific channel and updates local state.
   * @param id Channel identifier.
   */
  loadForChannel(id: string): void {
    this._setLoading(true);
    this.http
      .get<SingleEnvelope<ChatRoutingQueue>>(`${this.channelsBaseUrl}/${id}/routing-queue`)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const data = response.data;
          this.queue.set(data);
          this.agents.set(data.agents ?? []);
          this._setLoading(false);
          this.error.set(null);
        },
        error: (err: unknown) => {
          this._setLoading(false);
          if (err instanceof HttpErrorResponse && err.status === 404) {
            this.queue.set(null);
            this.agents.set([]);
            this.error.set(null);
          } else {
            this.error.set('Erro ao carregar fila de roteamento do canal.');
          }
        },
      });
  }

  /**
   * Saves the routing queue. Creates (POST) if none exists, otherwise updates (PUT).
   * @param scope Whether to save globally or for a channel.
   * @param data Partial queue data to save.
   * @param channelId Required when scope is 'channel'.
   */
  save(
    scope: 'global' | 'channel',
    data: Partial<ChatRoutingQueue>,
    channelId?: string,
  ): void {
    const url = this._getQueueUrl(scope, channelId);
    const exists = this.queue() !== null;

    this._setLoading(true);

    const request = exists
      ? this.http.put<SingleEnvelope<ChatRoutingQueue>>(url, data)
      : this.http.post<SingleEnvelope<ChatRoutingQueue>>(url, data);

    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        const saved = response.data;
        this.queue.set(saved);
        this.agents.set(saved.agents ?? []);
        this._setLoading(false);
        this.error.set(null);
      },
      error: () => {
        this._setLoading(false);
        this.error.set('Erro ao salvar fila de roteamento.');
      },
    });
  }

  /**
   * Adds an agent to the queue.
   * @param scope Whether to target the global queue or a channel queue.
   * @param userId User identifier to add.
   * @param position Optional position in the queue.
   * @param channelId Required when scope is 'channel'.
   */
  addAgent(
    scope: 'global' | 'channel',
    userId: string,
    position?: number,
    channelId?: string,
  ): void {
    const url = `${this._getQueueUrl(scope, channelId)}/agents`;
    const payload = { user_id: userId, ...(position !== undefined ? { position } : {}) };

    this._setLoading(true);

    this.http
      .post<SingleEnvelope<ChatRoutingQueueAgent>>(url, payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const added = response.data;
          this.agents.update((current) => [...current, added]);
          this._setLoading(false);
          this.error.set(null);
        },
        error: () => {
          this._setLoading(false);
          this.error.set('Erro ao adicionar agente à fila.');
        },
      });
  }

  /**
   * Removes an agent from the queue.
   * @param scope Whether to target the global queue or a channel queue.
   * @param userId User identifier to remove.
   * @param channelId Required when scope is 'channel'.
   */
  removeAgent(
    scope: 'global' | 'channel',
    userId: string,
    channelId?: string,
  ): void {
    const url = `${this._getQueueUrl(scope, channelId)}/agents/${userId}`;

    this._setLoading(true);

    this.http
      .delete<void>(url)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.agents.update((current) => current.filter((a) => a.user_id !== userId));
          this._setLoading(false);
          this.error.set(null);
        },
        error: () => {
          this._setLoading(false);
          this.error.set('Erro ao remover agente da fila.');
        },
      });
  }

  /**
   * Reorders agents in the queue.
   * @param scope Whether to target the global queue or a channel queue.
   * @param agents Array of user positions.
   * @param channelId Required when scope is 'channel'.
   */
  reorder(
    scope: 'global' | 'channel',
    agents: { user_id: string; position: number }[],
    channelId?: string,
  ): void {
    const url = `${this._getQueueUrl(scope, channelId)}/agents/reorder`;

    this._setLoading(true);

    this.http
      .put<SingleEnvelope<ChatRoutingQueueAgent[]>>(url, { agents })
      .pipe(
        map((response) => response.data),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (updatedAgents) => {
          this.agents.set(updatedAgents);
          this._setLoading(false);
          this.error.set(null);
        },
        error: () => {
          this._setLoading(false);
          this.error.set('Erro ao reordenar agentes da fila.');
        },
      });
  }

  /** Builds the base queue URL depending on scope. */
  private _getQueueUrl(scope: 'global' | 'channel', channelId?: string): string {
    if (scope === 'global') {
      return `${this.baseUrl}/global`;
    }
    if (!channelId) {
      throw new Error('channelId is required when scope is channel');
    }
    return `${this.channelsBaseUrl}/${channelId}/routing-queue`;
  }

  /** Centralizes loading toggle to avoid forgetting error reset. */
  private _setLoading(value: boolean): void {
    this.loading.set(value);
    if (value) {
      this.error.set(null);
    }
  }
}
