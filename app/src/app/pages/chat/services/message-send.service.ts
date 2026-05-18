import { HttpErrorResponse } from '@angular/common/http';
import { DestroyRef, Injectable, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import {
  Subject,
  firstValueFrom,
  from,
  map,
  of,
  switchMap,
  type Observable,
  catchError,
  throwError,
} from 'rxjs';
import {
  CalledMessageService,
  type CalledMessage,
  type CalledMessageSendResponse,
} from 'src/app/core/services/called-message.service';
import { NetworkStatusService } from 'src/app/core/services/network-status.service';
import {
  OfflineQueueService,
  type OfflineQueuedMessage,
} from 'src/app/core/services/platform/offline-queue.service';
import type { MessageSendResult, OfflineQueueDeliveredEvent, OfflineQueueFailedEvent } from '@chat/models/message-send.model';
export type { MessageSendResult, MessageSendResultQueued, MessageSendResultSent, OfflineQueueDeliveredEvent, OfflineQueueFailedEvent } from '@chat/models/message-send.model';



/**
 * Responsável por envio de mensagens de texto com fallback para fila offline.
 */
@Injectable({ providedIn: 'root' })
export class MessageSendService {
  private readonly calledMessageService = inject(CalledMessageService);
  private readonly networkStatus = inject(NetworkStatusService);
  private readonly offlineQueue = inject(OfflineQueueService);
  private readonly destroyRef = inject(DestroyRef);

  private readonly queueDeliveredSubject = new Subject<OfflineQueueDeliveredEvent>();
  readonly queueDelivered$ = this.queueDeliveredSubject.asObservable();

  private readonly queueFailedSubject = new Subject<OfflineQueueFailedEvent>();
  readonly queueFailed$ = this.queueFailedSubject.asObservable();

  private readonly flushingSignal = signal(false);
  readonly isFlushing = this.flushingSignal.asReadonly();

  readonly pendingQueueSize = computed(() => this.offlineQueue.size());

  private initialized = false;

  async initialize(): Promise<void> {
    if (this.initialized) {
      return;
    }

    this.initialized = true;
    await this.offlineQueue.hydrate();
    this.networkStatus.startMonitoring();

    this.networkStatus.statusChanges$
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((online) => {
        if (online) {
          void this.flushQueue();
        }
      });

    if (this.networkStatus.online) {
      await this.flushQueue();
    }
  }

  pendingCountForTicket(calledId: string | null): number {
    return this.offlineQueue.pendingCountForTicket(calledId);
  }

  sendText(
    calledId: string,
    content: string,
    clientMessageId: string,
  ): Observable<MessageSendResult> {
    if (!this.networkStatus.online) {
      return from(this.queueMessage(calledId, content, clientMessageId)).pipe(
        map((queueId) => ({
          status: 'queued' as const,
          clientMessageId,
          queueId,
        })),
      );
    }

    return this.calledMessageService
      .send(calledId, content, 'text', undefined, undefined, clientMessageId)
      .pipe(
        map((response: CalledMessageSendResponse) => ({
          status: 'sent' as const,
          clientMessageId,
          message: response.data,
        })),
        catchError((error: HttpErrorResponse) => {
          if (!this.isOfflineError(error)) {
            return throwError(() => error);
          }

          this.networkStatus.markOffline();
          return from(this.queueMessage(calledId, content, clientMessageId)).pipe(
            map((queueId) => ({
              status: 'queued' as const,
              clientMessageId,
              queueId,
            })),
          );
        }),
      );
  }

  async flushQueue(): Promise<void> {
    if (this.flushingSignal() || !this.networkStatus.online) {
      return;
    }

    this.flushingSignal.set(true);

    try {
      while (this.networkStatus.online) {
        const next = this.offlineQueue.getSnapshot()[0];
        if (!next) {
          break;
        }

        try {
          const response = await firstValueFrom(
            this.calledMessageService.send(
              next.calledId,
              next.content,
              next.type,
              undefined,
              undefined,
              next.clientMessageId,
            ),
          );

          await this.offlineQueue.remove(next.id);
          this.queueDeliveredSubject.next({
            queueId: next.id,
            calledId: next.calledId,
            clientMessageId: next.clientMessageId,
            message: response.data,
          });
        } catch (error) {
          if (error instanceof HttpErrorResponse && this.isOfflineError(error)) {
            this.networkStatus.markOffline();
            break;
          }

          await this.offlineQueue.incrementAttempts(next.id);
          this.queueFailedSubject.next({
            queueId: next.id,
            calledId: next.calledId,
            clientMessageId: next.clientMessageId,
          });
          break;
        }
      }
    } finally {
      this.flushingSignal.set(false);
    }
  }

  private async queueMessage(
    calledId: string,
    content: string,
    clientMessageId: string,
  ): Promise<string> {
    const queueId = this.createQueueId();
    const queuedMessage: OfflineQueuedMessage = {
      id: queueId,
      calledId,
      content,
      type: 'text',
      clientMessageId,
      createdAt: new Date().toISOString(),
      attempts: 0,
    };

    await this.offlineQueue.enqueue(queuedMessage);
    return queueId;
  }

  private isOfflineError(error: HttpErrorResponse): boolean {
    return error.status === 0 || error.status === 408;
  }

  private createQueueId(): string {
    return `queue-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
  }
}
